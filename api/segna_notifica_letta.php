<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$notifica_id = intval($input['notifica_id'] ?? 0);

if ($notifica_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID notifica non valido']);
    exit;
}

try {
    // Verifica che la notifica appartenga all'utente
    $sql_check = "SELECT id FROM notifiche WHERE id = ? AND utente_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$notifica_id, $_SESSION['user_id']]);
    
    if (!$stmt_check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Notifica non trovata']);
        exit;
    }
    
    $sql = "UPDATE notifiche SET letta = 1, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$notifica_id]);
    
    echo json_encode(['success' => $success]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}