<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

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
        // DOCENTI CRUD
        case 'list':
            handleList($pdo);
            break;
        case 'get':
            handleGet($pdo);
            break;
        case 'create':
            handleCreate($pdo);
            break;
        case 'update':
            handleUpdate($pdo);
            break;
        case 'delete':
            handleDelete($pdo);
            break;
        
        // DOCENTI-MATERIE
        case 'assign_materia':
            assignDocenteMateria($pdo);
            break;
        case 'get_materie':
            getDocentiMaterie($pdo);
            break;
        case 'remove_materia':
            removeDocenteMateria($pdo);
            break;
        
        // VINCOLI DOCENTI
        case 'add_vincolo':
            addVincolo($pdo);
            break;
        case 'get_vincoli':
            getVincoli($pdo);
            break;
        case 'delete_vincolo':
            deleteVincolo($pdo);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
} catch (Exception $e) {
    error_log("Errore docenti_api: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}

// ===================== UTILS =====================

function enforceCsrfToken(array $requestData = null) {
    if (!function_exists('requireCsrfToken')) {
        return;
    }

    $token = $requestData['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (empty($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF mancante']);
        exit;
    }

    try {
        requireCsrfToken($token);
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF non valido']);
        exit;
    }
}

function getJsonPayload(): array {
    static $payload = null;

    if ($payload !== null) {
        return $payload;
    }

    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        $payload = [];
        return $payload;
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payload JSON non valido']);
        exit;
    }

    $payload = $decoded;
    return $payload;
}

function sanitizeDocentePayload(array $data): array {
    $allowed = [
        'cognome' => '',
        'nome' => '',
        'codice_fiscale' => null,
        'email' => '',
        'telefono' => '',
        'cellulare' => '',
        'sede_principale_id' => null,
        'specializzazione' => '',
        'ore_settimanali_contratto' => null,
        'max_ore_giorno' => null,
        'max_ore_settimana' => null,
        'permette_buchi_orario' => 0,
        'note' => '',
        'stato' => ''
    ];

    $sanitized = [];

    foreach ($allowed as $field => $default) {
        if (!array_key_exists($field, $data)) {
            $sanitized[$field] = $default;
            continue;
        }

        $value = $data[$field];

        switch ($field) {
            case 'sede_principale_id':
            case 'ore_settimanali_contratto':
            case 'max_ore_giorno':
            case 'max_ore_settimana':
                $sanitized[$field] = is_numeric($value) ? (int)$value : null;
                break;
            case 'permette_buchi_orario':
                $sanitized[$field] = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ? 1 : 0;
                break;
            default:
                $sanitized[$field] = trim((string)$value);
        }
    }

    return $sanitized;
}

function requireValidInt($value, string $fieldName) {
    $intValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($intValue === false) {
        echo json_encode(['success' => false, 'message' => "Valore non valido per {$fieldName}"]);
        exit;
    }

    return $intValue;
}

// ===================== FUNZIONI DOCENTI =====================

/**
 * Endpoint: GET api/docenti_api.php?action=list
 *
 * Restituisce la lista dei docenti con paginazione e ricerca
 *
 * @param PDO $pdo
 * @return void Stampa JSON con chiavi: success (bool), data (array)
 */
function handleList($pdo) {
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 20, 'min_range' => 1]]);
    $limit = min($limit, 100);
    $offset = ($page - 1) * $limit;
    $search = trim((string)($_GET['search'] ?? ''));
    $search = mb_substr($search, 0, 100);
    
    $sql = "SELECT d.*, s.nome as sede_nome FROM docenti d 
            LEFT JOIN sedi s ON d.sede_principale_id = s.id WHERE 1=1";
    
    $stmt = null;
    
    if ($search !== '') {
        $sql .= " AND (d.cognome LIKE :search OR d.nome LIKE :search OR d.email LIKE :search)";
    }
    
    $sql .= " ORDER BY d.cognome, d.nome LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    if ($search !== '') {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $docenti = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $docenti]);
}

/**
 * Endpoint: GET api/docenti_api.php?action=get&id={id}
 *
 * Restituisce i dettagli di un singolo docente
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, data
 */
function handleGet($pdo) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID non valido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM docenti WHERE id = ?");
    $stmt->execute([$id]);
    $docente = $stmt->fetch();
    
    if ($docente) {
        echo json_encode(['success' => true, 'data' => $docente]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Docente non trovato']);
    }
}

/**
 * Endpoint: POST api/docenti_api.php?action=create
 *
 * Crea un nuovo docente. I dati vengono prelevati da $_POST (o payload JSON) e
 * validati da validateDocenteData().
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, message, id
 */
function handleCreate($pdo) {
    enforceCsrfToken();
    $data = sanitizeDocentePayload($_POST);
    
    $errors = validateDocenteData($data);
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Dati non validi', 'errors' => $errors]);
        return;
    }
    
    $sql = "INSERT INTO docenti (cognome, nome, codice_fiscale, email, telefono, cellulare, 
                          sede_principale_id, specializzazione, ore_settimanali_contratto, max_ore_giorno, 
                          max_ore_settimana, permette_buchi_orario, note, stato) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $data['cognome'], $data['nome'], $data['codice_fiscale'], $data['email'],
        $data['telefono'], $data['cellulare'], $data['sede_principale_id'], $data['specializzazione'],
        $data['ore_settimanali_contratto'], $data['max_ore_giorno'], $data['max_ore_settimana'],
        $data['permette_buchi_orario'] ?? 0, $data['note'], $data['stato']
    ];
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $docente_id = $pdo->lastInsertId();
        logDocentiActivity($pdo, 'creazione', 'docenti', (int)$docente_id, "Creato docente: {$data['cognome']} {$data['nome']}");
        echo json_encode(['success' => true, 'message' => 'Docente creato con successo', 'id' => $docente_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio']);
    }
}

/**
 * Endpoint: POST api/docenti_api.php?action=update
 *
 * Modifica i dati di un docente esistente. Richiede campo id in POST.
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, message
 */
function handleUpdate($pdo) {
    enforceCsrfToken();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID non valido']);
        return;
    }
    
    $data = sanitizeDocentePayload($_POST);
    
    $errors = validateDocenteData($data);
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Dati non validi', 'errors' => $errors]);
        return;
    }
    
    $sql = "UPDATE docenti SET cognome = ?, nome = ?, codice_fiscale = ?, email = ?, 
                          telefono = ?, cellulare = ?, sede_principale_id = ?, specializzazione = ?,
                          ore_settimanali_contratto = ?, max_ore_giorno = ?, max_ore_settimana = ?,
                          permette_buchi_orario = ?, note = ?, stato = ?, updated_at = CURRENT_TIMESTAMP 
                          WHERE id = ?";
    
    $params = [
        $data['cognome'], $data['nome'], $data['codice_fiscale'], $data['email'],
        $data['telefono'], $data['cellulare'], $data['sede_principale_id'], $data['specializzazione'],
        $data['ore_settimanali_contratto'], $data['max_ore_giorno'], $data['max_ore_settimana'],
        $data['permette_buchi_orario'] ?? 0, $data['note'], $data['stato'], $id
    ];
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        logDocentiActivity($pdo, 'modifica', 'docenti', $id, "Modificato docente: {$data['cognome']} {$data['nome']}");
        echo json_encode(['success' => true, 'message' => 'Docente aggiornato con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento']);
    }
}

/**
 * Endpoint: POST api/docenti_api.php?action=delete
 *
 * Elimina un docente se non ha lezioni assegnate.
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, message
 */
function handleDelete($pdo) {
    enforceCsrfToken();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID non valido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM calendario_lezioni WHERE docente_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Impossibile eliminare: il docente ha lezioni assegnate']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT cognome, nome FROM docenti WHERE id = ?");
    $stmt->execute([$id]);
    $docente = $stmt->fetch();
    
    $stmt = $pdo->prepare("DELETE FROM docenti WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        logDocentiActivity($pdo, 'eliminazione', 'docenti', $id, "Eliminato docente: {$docente['cognome']} {$docente['nome']}");
        echo json_encode(['success' => true, 'message' => 'Docente eliminato con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione']);
    }
}

function validateDocenteData($data) {
    $errors = [];
    
    if (empty(trim($data['cognome'] ?? ''))) {
        $errors[] = 'Il cognome è obbligatorio';
    }
    
    if (empty(trim($data['nome'] ?? ''))) {
        $errors[] = 'Il nome è obbligatorio';
    }
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida';
    }
    
    if (!empty($data['codice_fiscale']) && strlen($data['codice_fiscale']) != 16) {
        $errors[] = 'Il codice fiscale deve essere di 16 caratteri';
    }
    
    if (empty($data['sede_principale_id'])) {
        $errors[] = 'La sede principale è obbligatoria';
    }
    
    if (!empty($data['email']) && strlen($data['email']) > 255) {
        $errors[] = 'Email troppo lunga';
    }
    
    if (!empty($data['telefono']) && strlen($data['telefono']) > 20) {
        $errors[] = 'Telefono troppo lungo';
    }
    
    if (!empty($data['cellulare']) && strlen($data['cellulare']) > 20) {
        $errors[] = 'Cellulare troppo lungo';
    }
    
    foreach (['ore_settimanali_contratto', 'max_ore_giorno', 'max_ore_settimana'] as $field) {
        if ($data[$field] !== null && (!is_int($data[$field]) || $data[$field] < 0)) {
            $errors[] = "Il campo {$field} deve essere un numero positivo";
        }
    }
    
    return $errors;
}

// ===================== DOCENTI-MATERIE =====================

/**
 * Endpoint: POST api/docenti_api.php?action=assign_materia
 *
 * Assegna una materia a un docente
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, message, id
 */
function assignDocenteMateria($pdo) {
    $data = getJsonPayload();
    enforceCsrfToken($data);
    
    $docenteId = $data['docente_id'] ?? null;
    $materiaId = $data['materia_id'] ?? null;
    
    if (empty($docenteId) || empty($materiaId)) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    $docenteId = requireValidInt($docenteId, 'docente_id');
    $materiaId = requireValidInt($materiaId, 'materia_id');
    
    try {
        $stmt_check = $pdo->prepare("SELECT id FROM docenti_materie WHERE docente_id = ? AND materia_id = ?");
        $stmt_check->execute([$docenteId, $materiaId]);
        
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Docente già assegnato a questa materia']);
            return;
        }
        
        $sql = "INSERT INTO docenti_materie (docente_id, materia_id, preferenza, abilitato) 
                VALUES (?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $preferenza = isset($data['preferenza']) ? max(1, min(5, (int)$data['preferenza'])) : 2;
        $stmt->execute([$docenteId, $materiaId, $preferenza]);
        
        $docenti_materie_id = $pdo->lastInsertId();
        
        logDocentiActivity($pdo, 'docente', 'docenti_materie', $docenti_materie_id, "Assegnato docente {$docenteId} alla materia {$materiaId}");
        
        echo json_encode(['success' => true, 'message' => 'Materia assegnata con successo', 'id' => $docenti_materie_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
    }
}

/**
 * Endpoint: GET api/docenti_api.php?action=get_materie&docente_id={id}
 *
 * Restituisce le materie in carico al docente
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, data
 */
function getDocentiMaterie($pdo) {
    $docente_id = $_GET['docente_id'] ?? 0;
    $docente_id = filter_var($docente_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    
    if (!$docente_id) {
        echo json_encode(['success' => false, 'message' => 'Docente non specificato']);
        return;
    }
    
    try {
        $sql = "SELECT dm.*, m.nome as materia_nome, p.nome as percorso_nome, m.anno_corso
                FROM docenti_materie dm
                JOIN materie m ON dm.materia_id = m.id
                JOIN percorsi_formativi p ON m.percorso_formativo_id = p.id
                WHERE dm.docente_id = ?
                ORDER BY p.nome, m.anno_corso, m.nome";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$docente_id]);
        $materie = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $materie]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
    }
}

/**
 * Endpoint: POST api/docenti_api.php?action=remove_materia
 *
 * Rimuove l'assegnazione di una materia a un docente
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, message
 */
function removeDocenteMateria($pdo) {
    $data = getJsonPayload();
    enforceCsrfToken($data);
    
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID non specificato']);
        return;
    }
    
    $recordId = requireValidInt($data['id'], 'id');
    
    try {
        $sql = "DELETE FROM docenti_materie WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$recordId]);
        
        logDocentiActivity($pdo, 'docente', 'docenti_materie', $recordId, "Rimossa materia da docente");
        
        echo json_encode(['success' => true, 'message' => 'Materia rimossa con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
    }
}

// ===================== VINCOLI DOCENTI =====================

/**
 * Endpoint: POST api/docenti_api.php?action=add_vincolo
 *
 * Aggiunge un vincolo (es. indisponibilità) per un docente
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, message
 */
function addVincolo($pdo) {
    $data = getJsonPayload();
    enforceCsrfToken($data);
    
    if (empty($data['docente_id']) || empty($data['tipo']) || empty($data['giorno_settimana'])) {
        echo json_encode(['success' => false, 'message' => 'Parametri obbligatori mancanti']);
        return;
    }
    
    $docenteId = requireValidInt($data['docente_id'], 'docente_id');
    $sedeId = isset($data['sede_id']) && $data['sede_id'] !== null
        ? requireValidInt($data['sede_id'], 'sede_id')
        : null;
    
    try {
        $sql = "INSERT INTO vincoli_docenti 
                (docente_id, tipo, giorno_settimana, ora_inizio, ora_fine, sede_id, motivo, attivo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $docenteId, trim($data['tipo']), trim($data['giorno_settimana']),
            $data['ora_inizio'] ?? null, $data['ora_fine'] ?? null,
            $sedeId, trim($data['motivo'] ?? ''),
            isset($data['attivo']) ? (int)!!$data['attivo'] : 1
        ]);
        
        $vincolo_id = $pdo->lastInsertId();
        
        logDocentiActivity($pdo, 'docente', 'vincoli_docenti', (int)$vincolo_id, "Aggiunto vincolo a docente {$docenteId}");
        
        echo json_encode(['success' => true, 'message' => 'Vincolo aggiunto con successo', 'id' => $vincolo_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
    }
}

/**
 * Endpoint: GET api/docenti_api.php?action=get_vincoli&docente_id={id}
 *
 * Recupera i vincoli di un docente
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, data
 */
function getVincoli($pdo) {
    $docente_id = $_GET['docente_id'] ?? 0;
    $docente_id = filter_var($docente_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    
    if (!$docente_id) {
        echo json_encode(['success' => false, 'message' => 'Docente non specificato']);
        return;
    }
    
    try {
        $sql = "SELECT v.*, s.nome as sede_nome
                FROM vincoli_docenti v
                LEFT JOIN sedi s ON v.sede_id = s.id
                WHERE v.docente_id = ?
                ORDER BY v.giorno_settimana, v.ora_inizio";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$docente_id]);
        $vincoli = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $vincoli]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
    }
}

/**
 * Endpoint: POST api/docenti_api.php?action=delete_vincolo
 *
 * Elimina un vincolo associato a un docente
 *
 * @param PDO $pdo
 * @return void JSON con chiavi: success, message
 */
function deleteVincolo($pdo) {
    $data = getJsonPayload();
    enforceCsrfToken($data);
    
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID non specificato']);
        return;
    }
    
    $vincoloId = requireValidInt($data['id'], 'id');
    
    try {
        $sql = "DELETE FROM vincoli_docenti WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vincoloId]);
        
        logDocentiActivity($pdo, 'docente', 'vincoli_docenti', $vincoloId, "Rimosso vincolo");
        
        echo json_encode(['success' => true, 'message' => 'Vincolo rimosso con successo']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
    }
}

// ===================== FUNZIONE LOG =====================

function logDocentiActivity($pdo, $tipo, $tabella, $record_id, $descrizione) {
    try {
        $sql = "INSERT INTO log_attivita (tipo, azione, descrizione, tabella, record_id, utente, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $tipo,
            $tipo,
            $descrizione,
            $tabella,
            $record_id,
            $_SESSION['username'] ?? 'sistema',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        // Ignora errori di log
    }
}
?>
