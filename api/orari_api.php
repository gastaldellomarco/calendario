<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        getSlots();
        break;
    case 'POST':
        createSlot($input);
        break;
    case 'PUT':
        updateSlot($input);
        break;
    case 'DELETE':
        deleteSlot($input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getSlots() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM orari_slot ORDER BY numero_slot");
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $slots]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function createSlot($data) {
    global $pdo;
    
    if (!isset($data['numero_slot']) || !isset($data['ora_inizio']) || !isset($data['ora_fine']) || !isset($data['tipo'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    try {
        // Verifica sovrapposizioni
        $stmt = $pdo->prepare("SELECT id FROM orari_slot WHERE (? BETWEEN ora_inizio AND ora_fine) OR (? BETWEEN ora_inizio AND ora_fine)");
        $stmt->execute([$data['ora_inizio'], $data['ora_fine']]);
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Slot overlaps with existing slot']);
            return;
        }
        
        // Calcola durata
        $start = new DateTime($data['ora_inizio']);
        $end = new DateTime($data['ora_fine']);
        $duration = $end->diff($start)->h * 60 + $end->diff($start)->i;
        
        $stmt = $pdo->prepare("INSERT INTO orari_slot (numero_slot, ora_inizio, ora_fine, tipo, durata_minuti, attivo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['numero_slot'],
            $data['ora_inizio'],
            $data['ora_fine'],
            $data['tipo'],
            $duration,
            $data['attivo'] ?? 1
        ]);
        
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateSlot($data) {
    global $pdo;
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing slot ID']);
        return;
    }
    
    try {
        // Verifica se lo slot è usato
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM calendario_lezioni WHERE slot_id = ?");
        $stmt->execute([$data['id']]);
        $used = $stmt->fetchColumn();
        
        if ($used > 0) {
            // Log della modifica per tracciamento
            error_log("Slot orario modificato mentre in uso: " . $data['id']);
        }
        
        $fields = [];
        $params = [];
        
        if (isset($data['numero_slot'])) {
            $fields[] = "numero_slot = ?";
            $params[] = $data['numero_slot'];
        }
        if (isset($data['ora_inizio'])) {
            $fields[] = "ora_inizio = ?";
            $params[] = $data['ora_inizio'];
        }
        if (isset($data['ora_fine'])) {
            $fields[] = "ora_fine = ?";
            $params[] = $data['ora_fine'];
        }
        if (isset($data['tipo'])) {
            $fields[] = "tipo = ?";
            $params[] = $data['tipo'];
        }
        if (isset($data['attivo'])) {
            $fields[] = "attivo = ?";
            $params[] = $data['attivo'];
        }
        
        // Ricalcola durata se ore cambiate
        if (isset($data['ora_inizio']) && isset($data['ora_fine'])) {
            $start = new DateTime($data['ora_inizio']);
            $end = new DateTime($data['ora_fine']);
            $duration = $end->diff($start)->h * 60 + $end->diff($start)->i;
            $fields[] = "durata_minuti = ?";
            $params[] = $duration;
        }
        
        $params[] = $data['id'];
        $sql = "UPDATE orari_slot SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteSlot($data) {
    global $pdo;
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing slot ID']);
        return;
    }
    
    try {
        // Verifica utilizzo
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM calendario_lezioni WHERE slot_id = ?");
        $stmt->execute([$data['id']]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Cannot delete slot: used in existing lessons']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM orari_slot WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>