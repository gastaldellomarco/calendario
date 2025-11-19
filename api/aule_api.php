<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        getSedi();
        break;
    case 'POST':
        createSede($input);
        break;
    case 'PUT':
        updateSede($input);
        break;
    case 'DELETE':
        deleteSede($input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getSedi() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM sedi ORDER BY nome");
        $sedi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Aggiungi statistiche per ogni sede
        foreach ($sedi as &$sede) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM aule WHERE sede_id = ? AND attiva = 1");
            $stmt->execute([$sede['id']]);
            $sede['stat_aule'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM docenti WHERE sede_principale_id = ? AND stato = 'attivo'");
            $stmt->execute([$sede['id']]);
            $sede['stat_docenti'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM classi WHERE sede_id = ? AND stato = 'attiva'");
            $stmt->execute([$sede['id']]);
            $sede['stat_classi'] = $stmt->fetchColumn();
        }
        
        echo json_encode(['success' => true, 'data' => $sedi]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function createSede($data) {
    global $pdo;
    
    $required = ['nome', 'codice', 'citta'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    try {
        // Verifica codice univoco
        $stmt = $pdo->prepare("SELECT id FROM sedi WHERE codice = ?");
        $stmt->execute([$data['codice']]);
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Sede code already exists']);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO sedi (nome, codice, indirizzo, citta, cap, provincia, telefono, email, attiva) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['nome'],
            $data['codice'],
            $data['indirizzo'] ?? null,
            $data['citta'],
            $data['cap'] ?? null,
            $data['provincia'] ?? null,
            $data['telefono'] ?? null,
            $data['email'] ?? null,
            $data['attiva'] ?? 1
        ]);
        
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateSede($data) {
    global $pdo;
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing sede ID']);
        return;
    }
    
    try {
        $fields = [];
        $params = [];
        
        $updatable = ['nome', 'codice', 'indirizzo', 'citta', 'cap', 'provincia', 'telefono', 'email', 'attiva'];
        foreach ($updatable as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        $params[] = $data['id'];
        $sql = "UPDATE sedi SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteSede($data) {
    global $pdo;
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing sede ID']);
        return;
    }
    
    try {
        // Verifica utilizzo in altre tabelle
        $tables = [
            'aule' => 'sede_id',
            'docenti' => 'sede_principale_id', 
            'classi' => 'sede_id',
            'calendario_lezioni' => 'sede_id',
            'giorni_chiusura' => 'sede_id'
        ];
        
        foreach ($tables as $table => $field) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
            $stmt->execute([$data['id']]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode(['error' => "Cannot delete sede: used in $table"]);
                return;
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM sedi WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>