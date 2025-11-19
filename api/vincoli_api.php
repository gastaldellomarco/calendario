<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verifica permessi
if (!in_array($_SESSION['ruolo'], ['preside', 'vice_preside', 'segreteria', 'amministratore'])) {
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getPDOConnection();
    
    switch ($action) {
        case 'create':
            handleCreateVincolo($pdo);
            break;
        case 'delete':
            handleDeleteVincolo($pdo);
            break;
        case 'list':
            handleListVincoli($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
} catch (Exception $e) {
    error_log("Errore vincoli_api: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}

function handleCreateVincolo($pdo) {
    $data = $_POST;
    
    // Validazione
    $errors = validateVincoloData($data);
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Dati non validi', 'errors' => $errors]);
        return;
    }
    
    $sql = "INSERT INTO vincoli_docenti (docente_id, tipo, giorno_settimana, ora_inizio, ora_fine, sede_id, motivo, attivo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $data['docente_id'],
        $data['tipo'],
        $data['giorno_settimana'],
        $data['ora_inizio'] ?: null,
        $data['ora_fine'] ?: null,
        $data['sede_id'] ?: null,
        $data['motivo'] ?: null,
        $data['attivo'] ?? 1
    ];
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $vincolo_id = $pdo->lastInsertId();
        logActivity($pdo, 'creazione', 'vincoli_docenti', $vincolo_id, "Creato vincolo per docente ID: {$data['docente_id']}");
        echo json_encode(['success' => true, 'message' => 'Vincolo creato con successo', 'id' => $vincolo_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio del vincolo']);
    }
}

function handleDeleteVincolo($pdo) {
    $id = (int)$_POST['id'];
    
    $stmt = $pdo->prepare("DELETE FROM vincoli_docenti WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        logActivity($pdo, 'eliminazione', 'vincoli_docenti', $id, "Eliminato vincolo docente");
        echo json_encode(['success' => true, 'message' => 'Vincolo eliminato con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione del vincolo']);
    }
}

function handleListVincoli($pdo) {
    $docente_id = (int)$_GET['docente_id'];
    
    $sql = "SELECT v.*, s.nome as sede_nome 
            FROM vincoli_docenti v 
            LEFT JOIN sedi s ON v.sede_id = s.id 
            WHERE v.docente_id = ? 
            ORDER BY v.giorno_settimana, v.ora_inizio";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$docente_id]);
    $vincoli = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $vincoli]);
}

function validateVincoloData($data) {
    $errors = [];
    
    if (empty($data['docente_id'])) {
        $errors[] = 'ID docente mancante';
    }
    
    if (empty($data['tipo'])) {
        $errors[] = 'Il tipo di vincolo è obbligatorio';
    }
    
    if (empty($data['giorno_settimana'])) {
        $errors[] = 'Il giorno della settimana è obbligatorio';
    }
    
    if ($data['tipo'] === 'doppia_sede' && empty($data['sede_id'])) {
        $errors[] = 'La sede è obbligatoria per vincoli di doppia sede';
    }
    
    return $errors;
}

function logActivity($pdo, $tipo, $tabella, $record_id, $descrizione) {
    $sql = "INSERT INTO log_attivita (tipo, azione, descrizione, tabella, record_id, utente, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $utente = $_SESSION['username'] ?? 'sistema';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tipo, $tipo, $descrizione, $tabella, $record_id, $utente, $ip_address]);
}
?>