<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/NotificheManager.php';
require_once '../algorithm/SuggerimentiRisoluzione.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

// Solo admin e preside possono gestire conflitti
if (!in_array(getLoggedUserRole(), ['amministratore', 'preside'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permessi insufficienti']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_conflitti':
            getConflitti();
            break;
            
        case 'get_conflitto_detail':
            getConflittoDetail();
            break;
            
        case 'get_suggerimenti_risoluzione':
            getSuggerimentiRisoluzione();
            break;
            
        case 'risolvi_conflitto':
            risolviConflitto();
            break;
            
        case 'ignora_conflitto':
            ignoraConflitto();
            break;
            
        case 'riapri_conflitto':
            riapriConflitto();
            break;
            
        case 'delete':
            deleteConflitto();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Errore API conflitti: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}

function getConflitti() {
    global $pdo;
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = intval($_GET['per_page'] ?? 20);
    $offset = ($page - 1) * $per_page;
    
    $where_conditions = [];
    $params = [];
    
    // Filtri
    if (!empty($_GET['tipo'])) {
        $where_conditions[] = "tipo = ?";
        $params[] = $_GET['tipo'];
    }
    
    if (!empty($_GET['gravita'])) {
        $where_conditions[] = "gravita = ?";
        $params[] = $_GET['gravita'];
    }
    
    if (isset($_GET['risolto'])) {
        $where_conditions[] = "risolto = ?";
        $params[] = $_GET['risolto'] === 'true' ? 1 : 0;
    }
    
    if (!empty($_GET['data_da'])) {
        $where_conditions[] = "data_conflitto >= ?";
        $params[] = $_GET['data_da'];
    }
    
    if (!empty($_GET['data_a'])) {
        $where_conditions[] = "data_conflitto <= ?";
        $params[] = $_GET['data_a'];
    }
    
    $where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Query principale
    $sql = "SELECT c.*, 
                   d.cognome as docente_cognome, d.nome as docente_nome,
                   cl.nome as classe_nome,
                   a.nome as aula_nome
            FROM conflitti_orario c
            LEFT JOIN docenti d ON c.docente_id = d.id
            LEFT JOIN classi cl ON c.classe_id = cl.id
            LEFT JOIN aule a ON c.aula_id = a.id
            $where_sql
            ORDER BY 
                CASE gravita 
                    WHEN 'critico' THEN 1
                    WHEN 'error' THEN 2
                    WHEN 'warning' THEN 3
                END,
                data_conflitto DESC
            LIMIT $offset, $per_page";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conflitti = $stmt->fetchAll();
    
    // Conteggio totale
    $sql_count = "SELECT COUNT(*) FROM conflitti_orario $where_sql";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total = $stmt_count->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => $conflitti,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

function getConflittoDetail() {
    global $pdo;
    
    $conflitto_id = intval($_GET['id'] ?? 0);
    
    if ($conflitto_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID conflitto non valido']);
        return;
    }
    
    $sql = "SELECT c.*, 
                   d.cognome as docente_cognome, d.nome as docente_nome,
                   cl.nome as classe_nome,
                   a.nome as aula_nome,
                   l.data_lezione, l.slot_id,
                   os.ora_inizio, os.ora_fine
            FROM conflitti_orario c
            LEFT JOIN docenti d ON c.docente_id = d.id
            LEFT JOIN classi cl ON c.classe_id = cl.id
            LEFT JOIN aule a ON c.aula_id = a.id
            LEFT JOIN calendario_lezioni l ON c.lezione_id = l.id
            LEFT JOIN orari_slot os ON l.slot_id = os.id
            WHERE c.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conflitto_id]);
    $conflitto = $stmt->fetch();
    
    if (!$conflitto) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Conflitto non trovato']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $conflitto
    ]);
}

function getSuggerimentiRisoluzione() {
    global $pdo;
    
    $conflitto_id = intval($_GET['conflitto_id'] ?? 0);
    
    if ($conflitto_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID conflitto non valido']);
        return;
    }
    
    // Ottieni dettagli conflitto
    $sql = "SELECT c.*, l.id as lezione_id, l.materia_id, l.data_lezione, l.slot_id, l.sede_id
            FROM conflitti_orario c
            LEFT JOIN calendario_lezioni l ON c.lezione_id = l.id
            WHERE c.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conflitto_id]);
    $conflitto = $stmt->fetch();
    
    if (!$conflitto) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Conflitto non trovato']);
        return;
    }
    
    $suggerimentiManager = new SuggerimentiRisoluzione($pdo);
    $suggerimenti = [];
    
    if ($conflitto['lezione_id']) {
        switch ($conflitto['tipo']) {
            case 'doppia_assegnazione_docente':
            case 'doppia_aula':
            case 'vincolo_docente':
                $suggerimenti['slot_alternativi'] = $suggerimentiManager->suggerisciSlotAlternativi($conflitto['lezione_id'], 5);
                $suggerimenti['docenti_alternativi'] = $suggerimentiManager->suggerisciDocentiAlternativi(
                    $conflitto['materia_id'], 
                    $conflitto['data_lezione'], 
                    $conflitto['slot_id']
                );
                break;
                
            case 'aula_non_adeguata':
                $suggerimenti['aule_alternative'] = $suggerimentiManager->suggerisciAuleAlternative(
                    null, 
                    $conflitto['data_lezione'], 
                    $conflitto['slot_id'], 
                    $conflitto['sede_id']
                );
                break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $suggerimenti
    ]);
}

function risolviConflitto() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conflitto_id = intval($input['conflitto_id'] ?? 0);
    $soluzione = $input['soluzione'] ?? [];
    
    if ($conflitto_id <= 0 || empty($soluzione)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dati insufficienti']);
        return;
    }
    
    // Inizia transazione
    $pdo->beginTransaction();
    
    try {
        // Ottieni dettagli conflitto
        $sql_conflitto = "SELECT * FROM conflitti_orario WHERE id = ?";
        $stmt = $pdo->prepare($sql_conflitto);
        $stmt->execute([$conflitto_id]);
        $conflitto = $stmt->fetch();
        
        if (!$conflitto) {
            throw new Exception('Conflitto non trovato');
        }
        
        // Applica soluzione in base al tipo
        $success = false;
        
        switch ($soluzione['tipo']) {
            case 'cambia_slot':
                $success = applicaCambioSlot($conflitto, $soluzione);
                break;
                
            case 'cambia_docente':
                $success = applicaCambioDocente($conflitto, $soluzione);
                break;
                
            case 'cambia_aula':
                $success = applicaCambioAula($conflitto, $soluzione);
                break;
                
            default:
                throw new Exception('Tipo di soluzione non supportato');
        }
        
        if ($success) {
            // Aggiorna conflitto come risolto
            $sql_risolvi = "UPDATE conflitti_orario 
                           SET risolto = 1, 
                               risolto_da = ?, 
                               data_risoluzione = NOW(),
                               metodo_risoluzione = 'manual'
                           WHERE id = ?";
            
            $stmt = $pdo->prepare($sql_risolvi);
            $stmt->execute([$_SESSION['user_id'], $conflitto_id]);
            
            $pdo->commit();
            
            // Invia notifica
            $notificheManager = new NotificheManager($pdo);
            $notificheManager->creaNotifica(
                $_SESSION['user_id'],
                'info',
                'Conflitto risolto',
                "Il conflitto #{$conflitto_id} è stato risolto con successo",
                'media',
                'conflitti_orario',
                $conflitto_id
            );
            
            echo json_encode(['success' => true, 'message' => 'Conflitto risolto con successo']);
        } else {
            throw new Exception('Impossibile applicare la soluzione');
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function applicaCambioSlot($conflitto, $soluzione) {
    global $pdo;
    
    if (!$conflitto['lezione_id']) {
        return false;
    }
    
    $sql = "UPDATE calendario_lezioni 
            SET slot_id = ?, modificato_manualmente = 1, updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$soluzione['slot_id'], $conflitto['lezione_id']]);
}

function applicaCambioDocente($conflitto, $soluzione) {
    global $pdo;
    
    if (!$conflitto['lezione_id']) {
        return false;
    }
    
    $sql = "UPDATE calendario_lezioni 
            SET docente_id = ?, modificato_manualmente = 1, updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$soluzione['docente_id'], $conflitto['lezione_id']]);
}

function applicaCambioAula($conflitto, $soluzione) {
    global $pdo;
    
    if (!$conflitto['lezione_id']) {
        return false;
    }
    
    $sql = "UPDATE calendario_lezioni 
            SET aula_id = ?, modificato_manualmente = 1, updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$soluzione['aula_id'], $conflitto['lezione_id']]);
}

function ignoraConflitto() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conflitto_id = intval($input['conflitto_id'] ?? 0);
    
    if ($conflitto_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID conflitto non valido']);
        return;
    }
    
    $sql = "UPDATE conflitti_orario 
            SET risolto = 1, 
                risolto_da = ?, 
                data_risoluzione = NOW(),
                metodo_risoluzione = 'ignorato'
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$_SESSION['user_id'], $conflitto_id]);
    
    echo json_encode(['success' => $success]);
}

function riapriConflitto() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conflitto_id = intval($input['conflitto_id'] ?? 0);
    
    if ($conflitto_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID conflitto non valido']);
        return;
    }
    
    $sql = "UPDATE conflitti_orario 
            SET risolto = 0, 
                risolto_da = NULL, 
                data_risoluzione = NULL,
                metodo_risoluzione = NULL
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$conflitto_id]);
    
    echo json_encode(['success' => $success]);
}

function deleteConflitto() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conflitto_id = intval($input['conflitto_id'] ?? 0);
    
    if ($conflitto_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID conflitto non valido']);
        return;
    }
    
    $sql = "DELETE FROM conflitti_orario WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$conflitto_id]);
    
    echo json_encode(['success' => $success]);
}