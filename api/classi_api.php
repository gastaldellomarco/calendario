<?php
// api/classi_api.php
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

// Ottieni settimane di lezione per l'anno scolastico attivo (fallback 33)
try {
    $stmt_weeks = $pdo->prepare("SELECT settimane_lezione FROM anni_scolastici WHERE attivo = 1 LIMIT 1");
    $stmt_weeks->execute();
    $settimane_lezione = (int)($stmt_weeks->fetchColumn() ?: 33);
} catch (Exception $e) {
    $settimane_lezione = 33;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    switch ($action) {
        case 'get_percorsi':
            getPercorsi($pdo);
            break;
        case 'get_aule':
            getAule($pdo);
            break;
        case 'get_percorso':
            getPercorso($pdo);
            break;
        case 'get_classe':
            getClasse($pdo);
            break;
        case 'get_materie_disponibili':
            getMaterieDisponibili($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($input['action'] ?? '') {
        case 'create_percorso':
            createPercorso($pdo, $input);
            break;
        case 'update_percorso':
            updatePercorso($pdo, $input);
            break;
        case 'create_classe':
            createClasse($pdo, $input);
            break;
        case 'update_classe':
            updateClasse($pdo, $input);
            break;
        case 'assegna_materie':
            assegnaMaterie($pdo, $input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
} elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['tipo']) && $input['tipo'] === 'percorso') {
        deletePercorso($pdo, $input);
    } else {
        deleteClasse($pdo, $input);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
}

function getPercorsi($pdo) {
    $sede_id = $_GET['sede_id'] ?? '';
    
    $sql = "SELECT id, nome FROM percorsi_formativi WHERE attivo = 1";
    $params = [];
    
    if ($sede_id) {
        $sql .= " AND sede_id = ?";
        $params[] = $sede_id;
    }
    
    $sql .= " ORDER BY nome";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $percorsi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($percorsi);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nel recupero percorsi']);
    }
}

function getAule($pdo) {
    $sede_id = $_GET['sede_id'] ?? '';
    
    if (!$sede_id) {
        echo json_encode([]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome 
            FROM aule 
            WHERE sede_id = ? AND attiva = 1 
            ORDER BY nome
        ");
        $stmt->execute([$sede_id]);
        $aule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($aule);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nel recupero aule']);
    }
}

function getPercorso($pdo) {
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID percorso non specificato']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM percorsi_formativi WHERE id = ?");
        $stmt->execute([$id]);
        $percorso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($percorso) {
            echo json_encode($percorso);
        } else {
            echo json_encode(['success' => false, 'message' => 'Percorso non trovato']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nel recupero percorso']);
    }
}

function getClasse($pdo) {
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID classe non specificato']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, a.anno as anno_scolastico_nome, p.nome as percorso_nome, s.nome as sede_nome
            FROM classi c
            LEFT JOIN anni_scolastici a ON c.anno_scolastico_id = a.id
            LEFT JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
            LEFT JOIN sedi s ON c.sede_id = s.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $classe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($classe) {
            echo json_encode($classe);
        } else {
            echo json_encode(['success' => false, 'message' => 'Classe non trovata']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nel recupero classe']);
    }
}

function getMaterieDisponibili($pdo) {
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

function createPercorso($pdo, $data) {
    // Validazione
    $errori = [];
    
    if (empty($data['nome'])) {
        $errori[] = "Il nome è obbligatorio";
    }
    
    if (empty($data['codice'])) {
        $errori[] = "Il codice è obbligatorio";
    }
    
    if (empty($data['sede_id'])) {
        $errori[] = "La sede è obbligatoria";
    }
    
    if (!empty($errori)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errori)]);
        return;
    }
    
    try {
        $sql = "INSERT INTO percorsi_formativi 
                (nome, codice, sede_id, durata_anni, ore_annuali_base, descrizione, attivo, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nome'], $data['codice'], $data['sede_id'],
            $data['durata_anni'] ?? 3, $data['ore_annuali_base'] ?? 990,
            $data['descrizione'] ?? '', $data['attivo'] ?? 1
        ]);
        
        // Log attività
        logAttivita($pdo, 'percorso', 'creazione', "Creato percorso: " . $data['nome'], $pdo->lastInsertId());
        
        echo json_encode(['success' => true, 'message' => 'Percorso creato con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nella creazione: ' . $e->getMessage()]);
    }
}

function updatePercorso($pdo, $data) {
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID percorso non specificato']);
        return;
    }
    
    try {
        $sql = "UPDATE percorsi_formativi SET 
                nome = ?, codice = ?, sede_id = ?, durata_anni = ?, 
                ore_annuali_base = ?, descrizione = ?, attivo = ?, updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nome'], $data['codice'], $data['sede_id'],
            $data['durata_anni'] ?? 3, $data['ore_annuali_base'] ?? 990,
            $data['descrizione'] ?? '', $data['attivo'] ?? 1, $data['id']
        ]);
        
        // Log attività
        logAttivita($pdo, 'percorso', 'modifica', "Modificato percorso: " . $data['nome'], $data['id']);
        
        echo json_encode(['success' => true, 'message' => 'Percorso aggiornato con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento: ' . $e->getMessage()]);
    }
}

function createClasse($pdo, $data) {
    // Validazione
    $errori = [];
    
    if (empty($data['nome'])) {
        $errori[] = "Il nome della classe è obbligatorio";
    }
    
    if (empty($data['anno_scolastico_id'])) {
        $errori[] = "L'anno scolastico è obbligatorio";
    }
    
    if (empty($data['percorso_formativo_id'])) {
        $errori[] = "Il percorso formativo è obbligatorio";
    }
    
    if (!empty($errori)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errori)]);
        return;
    }
    
    try {
        $sql = "INSERT INTO classi 
                (nome, anno_scolastico_id, percorso_formativo_id, anno_corso, sede_id, 
                 numero_studenti, ore_settimanali_previste, aula_preferenziale_id, note, stato, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nome'], $data['anno_scolastico_id'], $data['percorso_formativo_id'],
            $data['anno_corso'], $data['sede_id'], $data['numero_studenti'] ?? 20,
            $data['ore_settimanali_previste'] ?? 33, $data['aula_preferenziale_id'] ?? null,
            $data['note'] ?? '', $data['stato'] ?? 'attiva'
        ]);
        
        // Log attività
        logAttivita($pdo, 'classe', 'creazione', "Creata classe: " . $data['nome'], $pdo->lastInsertId());
        
        echo json_encode(['success' => true, 'message' => 'Classe creata con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nella creazione: ' . $e->getMessage()]);
    }
}

function updateClasse($pdo, $data) {
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID classe non specificato']);
        return;
    }
    
    try {
        $sql = "UPDATE classi SET 
                nome = ?, anno_scolastico_id = ?, percorso_formativo_id = ?, anno_corso = ?, sede_id = ?, 
                numero_studenti = ?, ore_settimanali_previste = ?, aula_preferenziale_id = ?, note = ?, stato = ?, updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nome'], $data['anno_scolastico_id'], $data['percorso_formativo_id'],
            $data['anno_corso'], $data['sede_id'], $data['numero_studenti'] ?? 20,
            $data['ore_settimanali_previste'] ?? 33, $data['aula_preferenziale_id'] ?? null,
            $data['note'] ?? '', $data['stato'] ?? 'attiva', $data['id']
        ]);
        
        // Log attività
        logAttivita($pdo, 'classe', 'modifica', "Modificata classe: " . $data['nome'], $data['id']);
        
        echo json_encode(['success' => true, 'message' => 'Classe aggiornata con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento: ' . $e->getMessage()]);
    }
}

function assegnaMaterie($pdo, $data) {
    if (empty($data['classe_id']) || empty($data['assegnazioni'])) {
        echo json_encode(['success' => false, 'message' => 'Dati insufficienti']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Disattiva tutte le assegnazioni esistenti
        $stmt_disattiva = $pdo->prepare("
            UPDATE classi_materie_docenti 
            SET attivo = 0, updated_at = NOW() 
            WHERE classe_id = ?
        ");
        $stmt_disattiva->execute([$data['classe_id']]);
        
        // Inserisci nuove assegnazioni
        foreach ($data['assegnazioni'] as $materia_id => $dati) {
            if (!empty($dati['docente_id']) && !empty($dati['ore_settimanali'])) {
                $stmt_inserisci = $pdo->prepare("
                    INSERT INTO classi_materie_docenti 
                    (classe_id, materia_id, docente_id, ore_settimanali, ore_annuali_previste, 
                     priorita, note, attivo, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    docente_id = VALUES(docente_id), ore_settimanali = VALUES(ore_settimanali),
                    ore_annuali_previste = VALUES(ore_annuali_previste), priorita = VALUES(priorita),
                    note = VALUES(note), attivo = 1, updated_at = NOW()
                ");
                
                $ore_sett = intval($dati['ore_settimanali']);
                $ore_annuali_previste = isset($dati['ore_annuali_previste']) && intval($dati['ore_annuali_previste']) ? intval($dati['ore_annuali_previste']) : intval(round($settimane_lezione * $ore_sett));
                
                $stmt_inserisci->execute([
                    $data['classe_id'], $materia_id, $dati['docente_id'], $dati['ore_settimanali'],
                    $ore_annuali_previste, $dati['priorita'] ?? 2, $dati['note'] ?? ''
                ]);
            }
        }
        
        // Log attività
        logAttivita($pdo, 'classe', 'assegna_materie', "Assegnate materie alla classe ID: " . $data['classe_id'], $data['classe_id']);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Assegnazioni salvate con successo']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio: ' . $e->getMessage()]);
    }
}

function deletePercorso($pdo, $data) {
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID percorso non specificato']);
        return;
    }
    
    try {
        // Verifica se il percorso è utilizzato
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as count FROM classi WHERE percorso_formativo_id = ?");
        $stmt_check->execute([$data['id']]);
        $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Impossibile eliminare: il percorso è utilizzato da alcune classi']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM percorsi_formativi WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        // Log attività
        logAttivita($pdo, 'percorso', 'eliminazione', "Eliminato percorso ID: " . $data['id'], $data['id']);
        
        echo json_encode(['success' => true, 'message' => 'Percorso eliminato con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione: ' . $e->getMessage()]);
    }
}

function deleteClasse($pdo, $data) {
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID classe non specificato']);
        return;
    }
    
    try {
        // Verifica se la classe ha assegnazioni attive
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as count FROM classi_materie_docenti WHERE classe_id = ? AND attivo = 1");
        $stmt_check->execute([$data['id']]);
        $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Impossibile eliminare: la classe ha materie assegnate']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM classi WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        // Log attività
        logAttivita($pdo, 'classe', 'eliminazione', "Eliminata classe ID: " . $data['id'], $data['id']);
        
        echo json_encode(['success' => true, 'message' => 'Classe eliminata con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione: ' . $e->getMessage()]);
    }
}

function logAttivita($pdo, $tipo, $azione, $descrizione, $record_id) {
    try {
        $sql = "INSERT INTO log_attivita (tipo, azione, descrizione, tabella, record_id, utente, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $tipo, $azione, $descrizione, 
            $tipo === 'percorso' ? 'percorsi_formativi' : 'classi',
            $record_id, $_SESSION['username'], $_SERVER['REMOTE_ADDR']
        ]);
    } catch (PDOException $e) {
        // Ignora errori di log
    }
}
?>