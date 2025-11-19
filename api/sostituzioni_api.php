<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../algorithm/SostitutoFinder.php';
require_once '../includes/SostituzioniManager.php';

header('Content-Type: application/json');

$sostituzioniManager = new SostituzioniManager($db);
$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'registra_assenza':
            $response = $sostituzioniManager->creaAssenza(
                intval($_POST['docente_id']),
                $_POST['data_inizio'],
                $_POST['data_fine'],
                $_POST['motivo'],
                $_POST['note'] ?? ''
            );
            break;
            
        case 'get_lezioni_da_sostituire':
            $lezioni = $sostituzioniManager->getLezioniDaSostituire(
                intval($_GET['docente_id']),
                $_GET['data_inizio'],
                $_GET['data_fine']
            );
            $response = ['success' => true, 'data' => $lezioni];
            break;
            
        case 'get_sostituti_disponibili':
            $finder = new SostitutoFinder($db);
            $sostituti = $finder->trovaSostituti(intval($_GET['lezione_id']), $_GET);
            $response = ['success' => true, 'data' => $sostituti];
            break;
            
        case 'assegna_sostituto':
            $result = $sostituzioniManager->applicaSostituzione(
                intval($_POST['lezione_id']),
                intval($_POST['docente_sostituto_id'])
            );
            $response = $result;
            break;
            
        case 'conferma_sostituzione':
            $result = $sostituzioniManager->confermaSostituzione(intval($_POST['sostituzione_id']));
            $response = $result;
            break;
            
        case 'annulla_sostituzione':
            $result = $sostituzioniManager->annullaSostituzione(intval($_POST['sostituzione_id']));
            $response = $result;
            break;
            
        case 'get_sostituzioni':
            $filtro_data = $_GET['filtro_data'] ?? date('Y-m-d');
            $filtro_stato = $_GET['filtro_stato'] ?? 'tutti';
            $sostituzioni = $sostituzioniManager->getSostituzioniAttive($filtro_data, $filtro_stato);
            $response = ['success' => true, 'data' => $sostituzioni];
            break;
            
        default:
            $response['message'] = 'Azione non riconosciuta';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Errore: ' . $e->getMessage();
    error_log("API Sostituzioni Error: " . $e->getMessage());
}

echo json_encode($response);