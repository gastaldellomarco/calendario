<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../algorithm/CalendarioGenerator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'message' => 'Solo richieste POST sono supportate'
    ]);
    exit;
}

$action = $_GET['action'] ?? 'start';
$anno_scolastico_id = $_POST['anno_scolastico_id'] ?? null;

if (!$anno_scolastico_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Parametro mancante',
        'message' => 'Anno scolastico non specificato'
    ]);
    exit;
}

try {
    $generator = new CalendarioGenerator($db);
    
    switch ($action) {
        case 'start':
            $opzioni = [
                'strategia' => $_POST['strategia'] ?? 'bilanciato',
                'max_tentativi' => $_POST['max_tentativi'] ?? 3,
                'considera_preferenze' => isset($_POST['considera_preferenze'])
            ];
            
            $result = $generator->generaCalendario($anno_scolastico_id, $opzioni);
            
            if ($result['success']) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Calendario generato con successo',
                    'statistiche' => $result['statistiche'],
                    'log' => array_slice($result['log'], -10) // Ultimi 10 log entries
                ]);
            } else {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => 'Errore durante la generazione del calendario',
                    'log' => array_slice($result['log'], -10)
                ]);
            }
            break;
            
        case 'status':
            // Per implementazioni asincrone - restituisce stato generazione
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'status' => 'completed', // In implementazione reale, leggere da sessione/DB
                'progress' => 100,
                'message' => 'Generazione completata'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Azione non valida',
                'message' => 'L\'azione ' . htmlspecialchars($action) . ' non è supportata'
            ]);
    }
    
} catch (Exception $e) {
    error_log("Errore generazione calendario: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server',
        'message' => $e->getMessage(),
        'type' => get_class($e)
    ]);
}
?>