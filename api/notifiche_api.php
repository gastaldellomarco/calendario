<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_notifiche':
            getNotifiche();
            break;
            
        case 'mark_as_read':
            markAsRead();
            break;
            
        case 'mark_all_as_read':
            markAllAsRead();
            break;
            
        case 'delete':
            deleteNotifica();
            break;
            
        case 'delete_read':
            deleteReadNotifiche();
            break;
            
        case 'create':
            createNotifica();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}

function getNotifiche() {
    global $pdo;
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = intval($_GET['per_page'] ?? 10);
    $offset = ($page - 1) * $per_page;
    
    $where_conditions = ["utente_id = :user_id"];
    $params = [':user_id' => $_SESSION['user_id']];
    
    // Filtri
    if (isset($_GET['letta'])) {
        $where_conditions[] = "letta = :letta";
        $params[':letta'] = $_GET['letta'] === 'true' ? 1 : 0;
    }
    
    if (!empty($_GET['tipo'])) {
        $where_conditions[] = "tipo = :tipo";
        $params[':tipo'] = $_GET['tipo'];
    }
    
    if (!empty($_GET['priorita'])) {
        $where_conditions[] = "priorita = :priorita";
        $params[':priorita'] = $_GET['priorita'];
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // Query principale
    $sql = "SELECT * FROM notifiche WHERE $where_sql ORDER BY created_at DESC LIMIT $offset, $per_page";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifiche = $stmt->fetchAll();
    
    // Conteggio totale
    $sql_count = "SELECT COUNT(*) FROM notifiche WHERE $where_sql";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total = $stmt_count->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => $notifiche,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

function markAsRead() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $notifica_id = intval($input['notifica_id'] ?? 0);
    
    if ($notifica_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID notifica non valido']);
        return;
    }
    
    // Verifica che la notifica appartenga all'utente
    $sql_check = "SELECT id FROM notifiche WHERE id = ? AND utente_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$notifica_id, $_SESSION['user_id']]);
    
    if (!$stmt_check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Notifica non trovata']);
        return;
    }
    
    $sql = "UPDATE notifiche SET letta = 1, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$notifica_id]);
    
    echo json_encode(['success' => $success]);
}

function markAllAsRead() {
    global $pdo;
    
    $sql = "UPDATE notifiche SET letta = 1, updated_at = NOW() WHERE utente_id = ? AND letta = 0";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => $success]);
}

function deleteNotifica() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $notifica_id = intval($input['notifica_id'] ?? 0);
    
    if ($notifica_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID notifica non valido']);
        return;
    }
    
    // Verifica che la notifica appartenga all'utente
    $sql_check = "SELECT id FROM notifiche WHERE id = ? AND utente_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$notifica_id, $_SESSION['user_id']]);
    
    if (!$stmt_check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Notifica non trovata']);
        return;
    }
    
    $sql = "DELETE FROM notifiche WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$notifica_id]);
    
    echo json_encode(['success' => $success]);
}

function deleteReadNotifiche() {
    global $pdo;
    
    $sql = "DELETE FROM notifiche WHERE utente_id = ? AND letta = 1";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => $success]);
}

function createNotifica() {
    global $pdo;
    
    // Solo admin può creare notifiche manualmente
    if (!in_array(getLoggedUserRole(), ['amministratore', 'preside'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['utente_id', 'tipo', 'titolo', 'messaggio'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Campo $field obbligatorio"]);
            return;
        }
    }
    
    $sql = "INSERT INTO notifiche (utente_id, tipo, priorita, titolo, messaggio, riferimento_tabella, riferimento_id, azione_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $input['utente_id'],
        $input['tipo'],
        $input['priorita'] ?? 'media',
        $input['titolo'],
        $input['messaggio'],
        $input['riferimento_tabella'] ?? null,
        $input['riferimento_id'] ?? null,
        $input['azione_url'] ?? null
    ]);
    
    echo json_encode(['success' => $success, 'id' => $pdo->lastInsertId()]);
}