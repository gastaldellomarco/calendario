<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

// DB helper: usa prepared statements con gestione errori centralizzata
function dbQueryAll(string $sql, array $params = []) {
    global $db;
    try {
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            $err = $db->errorInfo();
            throw new Exception('DB prepare error: ' . json_encode($err));
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        throw new Exception('dbQueryAll error: ' . $e->getMessage());
    }
}

function dbQueryOne(string $sql, array $params = []) {
    global $db;
    try {
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            $err = $db->errorInfo();
            throw new Exception('DB prepare error: ' . json_encode($err));
        }
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        throw new Exception('dbQueryOne error: ' . $e->getMessage());
    }
}

function dbExecute(string $sql, array $params = []) {
    global $db;
    try {
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            $err = $db->errorInfo();
            throw new Exception('DB prepare error: ' . json_encode($err));
        }
        $ok = $stmt->execute($params);
        if ($ok === false) {
            $err = $stmt->errorInfo();
            throw new Exception('DB execute error: ' . json_encode($err));
        }
        return $ok;
    } catch (Exception $e) {
        throw new Exception('dbExecute error: ' . $e->getMessage());
    }
}

// Verifica metodo
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Array per la risposta
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    switch ($action) {
        case 'get_lezioni':
            $response = getLezioni();
            break;

        case 'get_lezione':
            $response = getLezione();
            break;

        case 'create_lezione':
            if ($method === 'POST') {
                $response = createLezione();
            } else {
                throw new Exception('Metodo non consentito');
            }
            break;

        case 'update_lezione':
            if ($method === 'POST') {
                $response = updateLezione();
            } else {
                throw new Exception('Metodo non consentito');
            }
            break;

        case 'delete_lezione':
            if ($method === 'POST') {
                $response = deleteLezione();
            } else {
                throw new Exception('Metodo non consentito');
            }
            break;

        case 'move_lezione':
            if ($method === 'POST') {
                $response = moveLezione();
            } else {
                throw new Exception('Metodo non consentito');
            }
            break;

        case 'check_disponibilita':
            $response = checkDisponibilita();
            break;

        case 'get_conflitti_lezione':
            $response = getConflittiLezione();
            break;

        default:
            throw new Exception('Azione non valida');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ];
}

echo json_encode($response);
exit;

// Funzioni API

/**
 * Restituisce le lezioni per un intervallo di date e filtri opzionali
 *
 * Filtri (GET): data_inizio (Y-m-d), data_fine (Y-m-d), sede_id, classe_id, docente_id, slot_id
 *
 * @return array Associative response: success, message, data (array di lezioni)
 */
function getLezioni() {
    global $db;

    $filters = [
        'data_inizio' => $_GET['data_inizio'] ?? date('Y-m-d'),
        'data_fine' => $_GET['data_fine'] ?? date('Y-m-d'),
        'sede_id' => $_GET['sede_id'] ?? '',
        'classe_id' => $_GET['classe_id'] ?? '',
        'docente_id' => $_GET['docente_id'] ?? '',
        'slot_id' => $_GET['slot_id'] ?? ''
    ];

    $where = ["cl.data_lezione BETWEEN ? AND ?"];
    $params = [$filters['data_inizio'], $filters['data_fine']];

    if ($filters['sede_id']) { $where[] = "cl.sede_id = ?"; $params[] = $filters['sede_id']; }
    if ($filters['classe_id']) { $where[] = "cl.classe_id = ?"; $params[] = $filters['classe_id']; }
    if ($filters['docente_id']) { $where[] = "cl.docente_id = ?"; $params[] = $filters['docente_id']; }
    if ($filters['slot_id']) { $where[] = "cl.slot_id = ?"; $params[] = $filters['slot_id']; }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                cl.*, 
                c.nome as classe_nome, 
                m.nome as materia_nome, 
                m.codice as materia_codice, 
                CONCAT(d.cognome, ' ', d.nome) as docente_nome, 
                a.nome as aula_nome, 
                s.nome as sede_nome, 
                os.ora_inizio, 
                os.ora_fine, 
                os.numero_slot 
            FROM calendario_lezioni cl 
            JOIN classi c ON cl.classe_id = c.id 
            JOIN materie m ON cl.materia_id = m.id 
            JOIN docenti d ON cl.docente_id = d.id 
            LEFT JOIN aule a ON cl.aula_id = a.id 
            JOIN sedi s ON cl.sede_id = s.id 
            JOIN orari_slot os ON cl.slot_id = os.id 
            WHERE " . $where_sql . " 
            ORDER BY cl.data_lezione, os.ora_inizio";

    try {
        $lezioni = dbQueryAll($sql, $params);
        return [ 'success' => true, 'message' => 'Lezioni caricate', 'data' => $lezioni ];
    } catch (Exception $e) {
        error_log('getLezioni error: ' . $e->getMessage());
        throw new Exception('Errore caricamento lezioni');
    }
}

/**
 * Restituisce i dettagli di una singola lezione
 *
 * Parametri (GET): lezione_id
 *
 * @return array Associative response: success, message, data (lezione)
 */
function getLezione() {
    $lezione_id = $_GET['lezione_id'] ?? 0;
    if (!$lezione_id) throw new Exception('ID lezione non specificato');

    $sql = "SELECT 
            cl.*, 
            c.nome as classe_nome, 
            m.nome as materia_nome, 
            m.id as materia_id, 
            CONCAT(d.cognome, ' ', d.nome) as docente_nome, 
            d.id as docente_id, 
            a.nome as aula_nome, 
            a.id as aula_id, 
            s.nome as sede_nome, 
            os.ora_inizio, 
            os.ora_fine, 
            os.numero_slot, 
            cmd.ore_settimanali, 
            cmd.ore_effettuate 
        FROM calendario_lezioni cl 
        JOIN classi c ON cl.classe_id = c.id 
        JOIN materie m ON cl.materia_id = m.id 
        JOIN docenti d ON cl.docente_id = d.id 
        LEFT JOIN aule a ON cl.aula_id = a.id 
        JOIN sedi s ON cl.sede_id = s.id 
        JOIN orari_slot os ON cl.slot_id = os.id 
        LEFT JOIN classi_materie_docenti cmd ON cl.assegnazione_id = cmd.id 
        WHERE cl.id = ?";

    try {
        $lezione = dbQueryOne($sql, [$lezione_id]);
        if (!$lezione) throw new Exception('Lezione non trovata');
        return [ 'success' => true, 'message' => 'Lezione caricata', 'data' => $lezione ];
    } catch (Exception $e) {
        error_log('getLezione error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Crea una nuova lezione (payload JSON POST)
 *
 * Body JSON richiede i campi: data_lezione, slot_id, classe_id, materia_id, docente_id, sede_id
 *
 * @return array Associative response: success, message, data (lezione_id)
 */
function createLezione() {
    global $db;
    $data = json_decode(file_get_contents('php://input'), true);
    $required = ['data_lezione', 'slot_id', 'classe_id', 'materia_id', 'docente_id', 'sede_id'];
    foreach ($required as $field) if (empty($data[$field])) throw new Exception("Campo obbligatorio mancante: $field");

    $conflitti = checkConflitti($data);
    if (!empty($conflitti)) return [ 'success' => false, 'message' => 'Conflitti rilevati', 'data' => ['conflitti' => $conflitti] ];

    try {
        $assegnazione = dbQueryOne("SELECT id FROM classi_materie_docenti WHERE classe_id = ? AND materia_id = ? AND docente_id = ? AND attivo = 1", [$data['classe_id'], $data['materia_id'], $data['docente_id']]);
        $assegnazione_id = $assegnazione['id'] ?? null;
    } catch (Exception $e) { error_log('createLezione assegnazione: ' . $e->getMessage()); $assegnazione_id = null; }

    $sql = "INSERT INTO calendario_lezioni (data_lezione, slot_id, classe_id, materia_id, docente_id, aula_id, sede_id, assegnazione_id, stato, modalita, argomento, note, ore_effettive, modificato_da, modificato_manualmente, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
    try {
        dbExecute($sql, [ $data['data_lezione'], $data['slot_id'], $data['classe_id'], $data['materia_id'], $data['docente_id'], $data['aula_id'] ?? null, $data['sede_id'], $assegnazione_id, $data['stato'] ?? 'pianificata', $data['modalita'] ?? 'presenza', $data['argomento'] ?? '', $data['note'] ?? '', $data['ore_effettive'] ?? 1.00, $_SESSION['user_id'] ]);
        $lezione_id = $db->lastInsertId();
        logAttivita('creazione','lezione_creata',"Creata lezione ID: $lezione_id",'calendario_lezioni',$lezione_id);
        return [ 'success' => true, 'message' => 'Lezione creata con successo', 'data' => ['lezione_id' => $lezione_id] ];
    } catch (Exception $e) {
        error_log('createLezione error: ' . $e->getMessage());
        throw new Exception('Impossibile creare la lezione');
    }
}

/**
 * Aggiorna una lezione esistente (payload JSON POST)
 *
 * Body JSON: lezione_id + campi da aggiornare (slot_id, classe_id, materia_id, docente_id, aula_id, stato, modalita, argomento, note, ore_effettive)
 *
 * @return array Associative response: success, message, data (lezione_id)
 */
function updateLezione() {
    global $db;
    $data = json_decode(file_get_contents('php://input'), true);
    $lezione_id = $data['lezione_id'] ?? 0; if (!$lezione_id) throw new Exception('ID lezione non specificato');

    $lezione_esistente = dbQueryOne("SELECT * FROM calendario_lezioni WHERE id = ?", [$lezione_id]);
    if (!$lezione_esistente) throw new Exception('Lezione non trovata');

    $conflitti = checkConflitti($data, $lezione_id);
    if (!empty($conflitti)) return [ 'success' => false, 'message' => 'Conflitti rilevati', 'data' => ['conflitti' => $conflitti] ];

    $campi_aggiornabili = ['slot_id','classe_id','materia_id','docente_id','aula_id','sede_id','stato','modalita','argomento','note','ore_effettive'];
    $set_parts = []; $params = [];
    foreach ($campi_aggiornabili as $campo) if (isset($data[$campo])) { $set_parts[] = "$campo = ?"; $params[] = $data[$campo]; }

    if (isset($data['classe_id']) || isset($data['materia_id']) || isset($data['docente_id'])) {
        $classe_id = $data['classe_id'] ?? $lezione_esistente['classe_id'];
        $materia_id = $data['materia_id'] ?? $lezione_esistente['materia_id'];
        $docente_id = $data['docente_id'] ?? $lezione_esistente['docente_id'];
        try { $assegnazione = dbQueryOne("SELECT id FROM classi_materie_docenti WHERE classe_id = ? AND materia_id = ? AND docente_id = ? AND attivo = 1", [$classe_id,$materia_id,$docente_id]); $assegnazione_id = $assegnazione['id'] ?? null; } catch (Exception $e) { error_log('updateLezione assegnazione: '.$e->getMessage()); $assegnazione_id = null; }
        $set_parts[] = "assegnazione_id = ?"; $params[] = $assegnazione_id;
    }

    $set_parts[] = "modificato_da = ?"; $params[] = $_SESSION['user_id'];
    $set_parts[] = "modificato_manualmente = 1"; $set_parts[] = "updated_at = NOW()"; $set_parts[] = "versione = versione + 1";
    $params[] = $lezione_id; $set_sql = implode(', ', $set_parts);

    try {
        dbExecute("UPDATE calendario_lezioni SET $set_sql WHERE id = ?", $params);
        logAttivita('modifica','lezione_modificata',"Modificata lezione ID: $lezione_id",'calendario_lezioni',$lezione_id);
        return [ 'success' => true, 'message' => 'Lezione aggiornata con successo', 'data' => ['lezione_id' => $lezione_id] ];
    } catch (Exception $e) {
        error_log('updateLezione error: ' . $e->getMessage());
        throw new Exception('Errore nell\'aggiornamento della lezione');
    }
}

/**
 * Elimina una lezione (payload JSON POST)
 *
 * Body JSON: lezione_id
 *
 * @return array Associative response: success, message
 */
function deleteLezione() {
    global $db; $data = json_decode(file_get_contents('php://input'), true); $lezione_id = $data['lezione_id'] ?? 0; if (!$lezione_id) throw new Exception('ID lezione non specificato');
    $lezione = dbQueryOne("SELECT * FROM calendario_lezioni WHERE id = ?", [$lezione_id]); if (!$lezione) throw new Exception('Lezione non trovata');
    try { dbExecute("DELETE FROM calendario_lezioni WHERE id = ?", [$lezione_id]); logAttivita('eliminazione','lezione_eliminata',"Eliminata lezione ID: $lezione_id",'calendario_lezioni',$lezione_id); return [ 'success' => true, 'message' => 'Lezione eliminata con successo', 'data' => [] ]; } catch (Exception $e) { error_log('deleteLezione error: '.$e->getMessage()); throw new Exception('Impossibile eliminare la lezione'); }
}

/**
 * Sposta una lezione in una nuova data/slot (payload JSON POST)
 *
 * Body JSON: lezione_id, nuova_data (Y-m-d), nuovo_slot
 *
 * @return array Associative response: success, message, data (lezione_id)
 */
function moveLezione() {
    global $db; $data = json_decode(file_get_contents('php://input'), true); $lezione_id = $data['lezione_id'] ?? 0; $nuova_data = $data['nuova_data'] ?? ''; $nuovo_slot = $data['nuovo_slot'] ?? 0; if (!$lezione_id || !$nuova_data || !$nuovo_slot) throw new Exception('Dati insufficienti per lo spostamento');
    $lezione = dbQueryOne("SELECT * FROM calendario_lezioni WHERE id = ?", [$lezione_id]); if (!$lezione) throw new Exception('Lezione non trovata');
    $dati_verifica = ['data_lezione'=>$nuova_data,'slot_id'=>$nuovo_slot,'classe_id'=>$lezione['classe_id'],'docente_id'=>$lezione['docente_id'],'aula_id'=>$lezione['aula_id'],'sede_id'=>$lezione['sede_id']];
    $conflitti = checkConflitti($dati_verifica, $lezione_id); if (!empty($conflitti)) return [ 'success' => false, 'message' => 'Conflitti rilevati', 'data' => ['conflitti' => $conflitti] ];
    try { dbExecute("UPDATE calendario_lezioni SET data_lezione = ?, slot_id = ?, modificato_da = ?, modificato_manualmente = 1, updated_at = NOW(), versione = versione + 1 WHERE id = ?", [$nuova_data, $nuovo_slot, $_SESSION['user_id'], $lezione_id]); logAttivita('modifica','lezione_spostata',"Spostata lezione ID: $lezione_id a $nuova_data slot $nuovo_slot",'calendario_lezioni',$lezione_id); return [ 'success' => true, 'message' => 'Lezione spostata con successo', 'data' => ['lezione_id' => $lezione_id] ]; } catch (Exception $e) { error_log('moveLezione error: '.$e->getMessage()); throw new Exception('Impossibile spostare la lezione'); }
}

/**
 * Restituisce conflitti associati a una lezione
 *
 * Parametri (GET): lezione_id
 *
 * @return array Associative response: success, message, data (array di conflitti)
 */
function getConflittiLezione() {
    $lezione_id = $_GET['lezione_id'] ?? 0; if (!$lezione_id) throw new Exception('ID lezione non specificato');
    try { $conflitti = dbQueryAll("SELECT * FROM conflitti_orario WHERE lezione_id = ? AND risolto = 0 ORDER BY gravita DESC", [$lezione_id]); return [ 'success' => true, 'message' => 'Conflitti caricati', 'data' => $conflitti ]; } catch (Exception $e) { error_log('getConflittiLezione error: '.$e->getMessage()); return [ 'success' => false, 'message' => 'Errore caricamento conflitti', 'data' => [] ]; }
}

/**
 * Verifica conflitti per i dati di una lezione (usata internamente da create/update/move)
 *
 * @param array $dati Array con chiavi: data_lezione, slot_id, docente_id, aula_id, classe_id
 * @param int $escludi_lezione_id ID lezione da escludere (opzionale)
 * @return array Array di conflitti rilevati
 */
function checkConflitti($dati, $escludi_lezione_id = 0) {
    $conflitti = [];
    $where = ["cl.data_lezione = ?", "cl.slot_id = ?", "cl.stato != 'cancellata'"];
    $params = [$dati['data_lezione'], $dati['slot_id']];
    if ($escludi_lezione_id) { $where[] = "cl.id != ?"; $params[] = $escludi_lezione_id; }

    if (!empty($dati['docente_id'])) {
        $docente_conflitto = dbQueryOne("SELECT cl.id, c.nome as classe_nome, m.nome as materia_nome FROM calendario_lezioni cl JOIN classi c ON cl.classe_id = c.id JOIN materie m ON cl.materia_id = m.id WHERE " . implode(' AND ', $where) . " AND cl.docente_id = ?", array_merge($params, [$dati['docente_id']]));
        if ($docente_conflitto) $conflitti[] = [ 'tipo' => 'docente_occupato', 'messaggio' => "Il docente è già impegnato con {$docente_conflitto['classe_nome']} in {$docente_conflitto['materia_nome']}" ];
    }

    if (!empty($dati['aula_id'])) {
        $aula_conflitto = dbQueryOne("SELECT cl.id, c.nome as classe_nome, m.nome as materia_nome FROM calendario_lezioni cl JOIN classi c ON cl.classe_id = c.id JOIN materie m ON cl.materia_id = m.id WHERE " . implode(' AND ', $where) . " AND cl.aula_id = ?", array_merge($params, [$dati['aula_id']]));
        if ($aula_conflitto) $conflitti[] = [ 'tipo' => 'aula_occupata', 'messaggio' => "L'aula è già occupata da {$aula_conflitto['classe_nome']} per {$aula_conflitto['materia_nome']}" ];
    }

    if (!empty($dati['classe_id'])) {
        $classe_conflitto = dbQueryOne("SELECT cl.id, m.nome as materia_nome, CONCAT(d.cognome, ' ', d.nome) as docente_nome FROM calendario_lezioni cl JOIN materie m ON cl.materia_id = m.id JOIN docenti d ON cl.docente_id = d.id WHERE " . implode(' AND ', $where) . " AND cl.classe_id = ?", array_merge($params, [$dati['classe_id']]));
        if ($classe_conflitto) $conflitti[] = [ 'tipo' => 'classe_occupata', 'messaggio' => "La classe ha già lezione di {$classe_conflitto['materia_nome']} con {$classe_conflitto['docente_nome']}" ];
    }

    return $conflitti;
}

function logAttivita($tipo, $azione, $descrizione, $tabella = null, $record_id = null) {
    try {
        dbExecute("INSERT INTO log_attivita (tipo, azione, descrizione, tabella, record_id, utente, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", [ $tipo, $azione, $descrizione, $tabella, $record_id, $_SESSION['username'] ?? 'sistema', $_SERVER['REMOTE_ADDR'] ?? 'unknown' ]);
    } catch (Exception $e) {
        error_log('logAttivita error: ' . $e->getMessage());
    }
}

?>