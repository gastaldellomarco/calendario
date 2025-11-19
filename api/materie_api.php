<?php
// api/materie_api.php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Connessione al database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    switch ($action) {
        case 'get':
            getMateria($pdo);
            break;
        case 'get_by_percorso_anno':
            getMaterieByPercorsoAnno($pdo);
            break;
        case 'get_docenti_abilitati':
            getDocentiAbilitati($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // ✅ CORRETTO: Prendi action dal body JSON O dalla query string (retrocompatibilità)
    $action = $input['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            createMateria($pdo, $input);
            break;
        case 'update':
            updateMateria($pdo, $input);
            break;
        // ✅ AGGIUNTO: Funzioni per gestione docenti-materie
        case 'aggiungi_docente':
            aggiungiDocente($pdo, $input);
            break;
        case 'modifica_preferenza':
            modificaPreferenza($pdo, $input);
            break;
        case 'rimuovi_abilitazione':
            rimuoviAbilitazione($pdo, $input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida: ' . $action]);
    }
} elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    deleteMateria($pdo, $input);
} else {
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
}

function getMateria($pdo) {
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID materia non specificato']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM materie WHERE id = ?");
        $stmt->execute([$id]);
        $materia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($materia) {
            echo json_encode($materia);
        } else {
            echo json_encode(['success' => false, 'message' => 'Materia non trovata']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nel recupero materia']);
    }
}

function getMaterieByPercorsoAnno($pdo) {
    $percorso_id = $_GET['percorso_id'] ?? '';
    $anno_corso = $_GET['anno_corso'] ?? '';
    
    if (!$percorso_id || !$anno_corso) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.* 
            FROM materie m
            WHERE m.percorso_formativo_id = ? AND m.anno_corso = ? AND m.attiva = 1
            ORDER BY m.tipo, m.nome
        ");
        $stmt->execute([$percorso_id, $anno_corso]);
        $materie = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($materie);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nel recupero materie']);
    }
}

function getDocentiAbilitati($pdo) {
    $materia_id = $_GET['materia_id'] ?? '';
    
    if (!$materia_id) {
        echo json_encode(['success' => false, 'message' => 'ID materia non specificato']);
        return;
    }
    
    try {
        // Recupera docenti abilitati per la materia
        $stmt = $pdo->prepare("
            SELECT d.id, d.cognome, d.nome, d.ore_settimanali_contratto, d.max_ore_settimana
            FROM docenti_materie dm
            JOIN docenti d ON dm.docente_id = d.id
            WHERE dm.materia_id = ? AND dm.abilitato = 1 AND d.stato = 'attivo'
            ORDER BY d.cognome, d.nome
        ");
        $stmt->execute([$materia_id]);
        $docenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($docenti);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nel recupero docenti']);
    }
}

/**
 * Ritorna il numero di settimane di lezione per l'anno scolastico attivo (default 33)
 */
function getSettimaneLezioneCorrente($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT settimane_lezione FROM anni_scolastici WHERE attivo = 1 LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val ? (int)$val : 33;
    } catch (PDOException $e) {
        return 33;
    }
}

/**
 * Mappa tipo materia -> peso default
 */
function tipoToPeso($tipo) {
    $map = [
        'culturale' => 1,
        'professionale' => 3,
        'laboratoriale' => 3,
        'stage' => 2,
        'sostegno' => 1
    ];
    return $map[$tipo] ?? 1;
}

function createMateria($pdo, $data) {
    // Validazione
    $errori = [];
    
    if (empty($data['nome'])) {
        $errori[] = "Il nome è obbligatorio";
    }
    
    if (empty($data['codice'])) {
        $errori[] = "Il codice è obbligatorio";
    }
    
    if (empty($data['percorso_formativo_id'])) {
        $errori[] = "Il percorso formativo è obbligatorio";
    }
    
    if (empty($data['anno_corso'])) {
        $errori[] = "L'anno corso è obbligatorio";
    }
    
    if (!empty($errori)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errori)]);
        return;
    }
    
    try {
        // Calcola ore_annuali e ore_settimanali in base ai dati forniti (supporto manuale)
        $settimane = getSettimaneLezioneCorrente($pdo);
        $ore_annuali = isset($data['ore_annuali']) && intval($data['ore_annuali']) ? intval($data['ore_annuali']) : null;
        $ore_sett = isset($data['ore_settimanali']) && intval($data['ore_settimanali']) ? intval($data['ore_settimanali']) : null;

        if ($ore_annuali !== null && $ore_sett === null) {
            $ore_sett = max(1, intval(round($ore_annuali / max(1, $settimane))));
        } elseif ($ore_annuali === null && $ore_sett !== null) {
            $ore_annuali = intval(round($settimane * $ore_sett));
        } elseif ($ore_annuali === null && $ore_sett === null) {
            $ore_sett = 2;
            $ore_annuali = intval(round($settimane * $ore_sett));
        }
        $peso = isset($data['peso']) && intval($data['peso']) ? intval($data['peso']) : tipoToPeso($data['tipo'] ?? 'culturale');
    $sql = "INSERT INTO materie 
        (nome, codice, tipo, percorso_formativo_id, anno_corso, ore_settimanali, 
         ore_annuali, peso, richiede_laboratorio, descrizione, attiva, distribuzione, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
    $distribuzione = in_array($data['distribuzione'] ?? '', ['settimanale', 'sparsa', 'casuale']) ? $data['distribuzione'] : 'settimanale';

    $stmt->execute([
            $data['nome'], $data['codice'], $data['tipo'] ?? 'culturale',
            $data['percorso_formativo_id'], $data['anno_corso'], $ore_sett,
            $ore_annuali, $peso, $data['richiede_laboratorio'] ?? 0,
            $data['descrizione'] ?? '', $data['attiva'] ?? 1, $distribuzione
        ]);
        
        // Log attività
        logAttivita($pdo, 'materia', 'creazione', "Creata materia: " . $data['nome'], $pdo->lastInsertId());
        
        echo json_encode(['success' => true, 'message' => 'Materia creata con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nella creazione: ' . $e->getMessage()]);
    }
}

function updateMateria($pdo, $data) {
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID materia non specificato']);
        return;
    }
    
    try {
        // Calcola ore_annuali e ore_settimanali in base ai dati forniti (supporto manuale)
        $settimane = getSettimaneLezioneCorrente($pdo);
        $ore_annuali = isset($data['ore_annuali']) && intval($data['ore_annuali']) ? intval($data['ore_annuali']) : null;
        $ore_sett = isset($data['ore_settimanali']) && intval($data['ore_settimanali']) ? intval($data['ore_settimanali']) : null;

        if ($ore_annuali !== null && $ore_sett === null) {
            $ore_sett = max(1, intval(round($ore_annuali / max(1, $settimane))));
        } elseif ($ore_annuali === null && $ore_sett !== null) {
            $ore_annuali = intval(round($settimane * $ore_sett));
        } elseif ($ore_annuali === null && $ore_sett === null) {
            $ore_sett = 2;
            $ore_annuali = intval(round($settimane * $ore_sett));
        }
        $peso = isset($data['peso']) && intval($data['peso']) ? intval($data['peso']) : tipoToPeso($data['tipo'] ?? 'culturale');
    $sql = "UPDATE materie SET 
                nome = ?, codice = ?, tipo = ?, percorso_formativo_id = ?, anno_corso = ?, 
                ore_settimanali = ?, ore_annuali = ?, peso = ?, richiede_laboratorio = ?, 
                descrizione = ?, attiva = ?, updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
    $distribuzione = in_array($data['distribuzione'] ?? '', ['settimanale', 'sparsa', 'casuale']) ? $data['distribuzione'] : 'settimanale';

    $sql = "UPDATE materie SET 
        nome = ?, codice = ?, tipo = ?, percorso_formativo_id = ?, anno_corso = ?, 
        ore_settimanali = ?, ore_annuali = ?, peso = ?, richiede_laboratorio = ?, 
        descrizione = ?, attiva = ?, distribuzione = ?, updated_at = NOW()
        WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
            $data['nome'], $data['codice'], $data['tipo'] ?? 'culturale',
            $data['percorso_formativo_id'], $data['anno_corso'], $ore_sett,
            $ore_annuali, $peso, $data['richiede_laboratorio'] ?? 0,
            $data['descrizione'] ?? '', $data['attiva'] ?? 1, $distribuzione, $data['id']
        ]);
        
        // Log attività
        logAttivita($pdo, 'materia', 'modifica', "Modificata materia: " . $data['nome'], $data['id']);
        
        echo json_encode(['success' => true, 'message' => 'Materia aggiornata con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento: ' . $e->getMessage()]);
    }
}

function deleteMateria($pdo, $data) {
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID materia non specificato']);
        return;
    }
    
    try {
        // Verifica se la materia è utilizzata
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as count FROM classi_materie_docenti WHERE materia_id = ? AND attivo = 1");
        $stmt_check->execute([$data['id']]);
        $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Impossibile eliminare: la materia è assegnata ad alcune classi']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM materie WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        // Log attività
        logAttivita($pdo, 'materia', 'eliminazione', "Eliminata materia ID: " . $data['id'], $data['id']);
        
        echo json_encode(['success' => true, 'message' => 'Materia eliminata con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione: ' . $e->getMessage()]);
    }
}

function logAttivita($pdo, $tipo, $azione, $descrizione, $record_id) {
    try {
        $sql = "INSERT INTO log_attivita (tipo, azione, descrizione, tabella, record_id, utente, ip_address) 
                VALUES (?, ?, ?, 'materie', ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tipo, $azione, $descrizione, $record_id, $_SESSION['username'], $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Ignora errori di log
    }
}

// ✅ AGGIUNTO: Funzione aggiungi docente a materia
function aggiungiDocente($pdo, $data) {
    if (empty($data['materia_id']) || empty($data['docente_id'])) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    try {
        // Controlla se già esiste
        $sql_check = "SELECT id FROM docenti_materie WHERE materia_id = ? AND docente_id = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$data['materia_id'], $data['docente_id']]);
        
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Docente già assegnato a questa materia']);
            return;
        }
        
        $sql = "INSERT INTO docenti_materie (materia_id, docente_id, preferenza, abilitato) VALUES (?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['materia_id'], $data['docente_id'], $data['preferenza'] ?? 2]);
        
        echo json_encode(['success' => true, 'message' => 'Docente aggiunto con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiunta: ' . $e->getMessage()]);
    }
}

// ✅ AGGIUNTO: Funzione modifica preferenza docente
function modificaPreferenza($pdo, $data) {
    if (empty($data['id']) || empty($data['preferenza'])) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    try {
        $sql = "UPDATE docenti_materie SET preferenza = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['preferenza'], $data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Preferenza aggiornata con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento: ' . $e->getMessage()]);
    }
}

// ✅ AGGIUNTO: Funzione rimuovi abilitazione docente
function rimuoviAbilitazione($pdo, $data) {
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID non specificato']);
        return;
    }
    
    try {
        $sql = "DELETE FROM docenti_materie WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Abilitazione rimossa con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nella rimozione: ' . $e->getMessage()]);
    }
}
?>