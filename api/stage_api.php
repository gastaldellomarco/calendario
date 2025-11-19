<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../config/database.php';
require_once '../algorithm/StageCalendarioManager.php';

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verifica CSRF token per le richieste POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrfToken($csrf_token);
    }

    switch ($action) {
        case 'create_stage':
            createStage();
            break;
            
        case 'update_stage':
            updateStage();
            break;
            
        case 'delete_stage':
            deleteStage();
            break;
            
        case 'get_stage_by_classe':
            getStageByClasse();
            break;
            
        case 'get_stage_in_periodo':
            getStageInPeriodo();
            break;
            
        case 'blocca_calendario_stage':
            bloccaCalendarioStage();
            break;
            
        case 'registra_presenza_giorno':
            registraPresenzaGiorno();
            break;
            
        case 'upload_documento_stage':
            uploadDocumentoStage();
            break;
            
        case 'completa_stage':
            completaStage();
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function createStage() {
    $classe_id = $_POST['classe_id'] ?? null;
    $data_inizio = $_POST['data_inizio'] ?? null;
    $data_fine = $_POST['data_fine'] ?? null;
    $ore_totali_previste = $_POST['ore_totali_previste'] ?? null;
    $descrizione = $_POST['descrizione'] ?? '';
    $note = $_POST['note'] ?? '';
    $stato = $_POST['stato'] ?? 'pianificato';
    
    // Dati tutor
    $tutor_scolastico_id = $_POST['tutor_scolastico_id'] ?? null;
    $nome_tutor_aziendale = $_POST['nome_tutor_aziendale'] ?? '';
    $azienda = $_POST['azienda'] ?? '';
    $telefono_azienda = $_POST['telefono_azienda'] ?? '';
    $email_azienda = $_POST['email_azienda'] ?? '';
    
    // Validazione
    if (!$classe_id || !$data_inizio || !$data_fine || !$ore_totali_previste) {
        throw new Exception('Tutti i campi obbligatori devono essere compilati');
    }
    
    if (strtotime($data_fine) <= strtotime($data_inizio)) {
        throw new Exception('La data fine deve essere successiva alla data inizio');
    }
    
    if ($ore_totali_previste <= 0) {
        throw new Exception('Le ore totali previste devono essere maggiori di 0');
    }
    
    // Verifica sovrapposizioni stage per la stessa classe
    $stage_esistenti = Database::queryAll("
        SELECT id FROM stage_periodi 
        WHERE classe_id = ? 
        AND stato != 'cancellato'
        AND (
            (data_inizio BETWEEN ? AND ?) OR
            (data_fine BETWEEN ? AND ?) OR
            (? BETWEEN data_inizio AND data_fine) OR
            (? BETWEEN data_inizio AND data_fine)
        )
    ", [$classe_id, $data_inizio, $data_fine, $data_inizio, $data_fine, $data_inizio, $data_fine]);
    
    if (!empty($stage_esistenti)) {
        throw new Exception('Esiste già uno stage per questa classe nel periodo selezionato');
    }
    
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    
    try {
        // Crea stage
        $stmt = $pdo->prepare("
            INSERT INTO stage_periodi 
            (classe_id, data_inizio, data_fine, ore_totali_previste, ore_effettuate, descrizione, note, stato, created_at, updated_at)
            VALUES (?, ?, ?, ?, 0, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$classe_id, $data_inizio, $data_fine, $ore_totali_previste, $descrizione, $note, $stato]);
        $stage_id = $pdo->lastInsertId();
        
        // Crea tutor
        if ($tutor_scolastico_id || $nome_tutor_aziendale) {
            $stmt = $pdo->prepare("
                INSERT INTO stage_tutor 
                (stage_periodo_id, docente_id, nome_tutor_aziendale, azienda, telefono_azienda, email_azienda, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$stage_id, $tutor_scolastico_id, $nome_tutor_aziendale, $azienda, $telefono_azienda, $email_azienda]);
        }
        
        // Gestione conflitti con calendario
        $cancella_lezioni = $_POST['cancella_lezioni_conflitto'] ?? false;
        if ($cancella_lezioni) {
            $stageManager = new StageCalendarioManager();
            $stageManager->bloccaCalendarioPerStage($stage_id);
        }
        
        // Log attività
        $stmt = $pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address, created_at)
            VALUES ('stage', 'creazione', ?, 'stage_periodi', ?, ?, ?, NOW())
        ");
        $stmt->execute(["Creato stage per classe ID $classe_id", $stage_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'stage_id' => $stage_id,
            'message' => 'Stage creato con successo'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateStage() {
    $stage_id = $_POST['stage_id'] ?? null;
    if (!$stage_id) {
        throw new Exception('ID stage non specificato');
    }
    
    // Recupera stage esistente
    $stage = Database::queryOne("SELECT * FROM stage_periodi WHERE id = ?", [$stage_id]);
    if (!$stage) {
        throw new Exception('Stage non trovato');
    }
    
    $data = [
        'data_inizio' => $_POST['data_inizio'] ?? $stage['data_inizio'],
        'data_fine' => $_POST['data_fine'] ?? $stage['data_fine'],
        'ore_totali_previste' => $_POST['ore_totali_previste'] ?? $stage['ore_totali_previste'],
        'descrizione' => $_POST['descrizione'] ?? $stage['descrizione'],
        'note' => $_POST['note'] ?? $stage['note'],
        'stato' => $_POST['stato'] ?? $stage['stato']
    ];
    
    // Validazione
    if (strtotime($data['data_fine']) <= strtotime($data['data_inizio'])) {
        throw new Exception('La data fine deve essere successiva alla data inizio');
    }
    
    if ($data['ore_totali_previste'] <= 0) {
        throw new Exception('Le ore totali previste devono essere maggiori di 0');
    }
    
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    
    try {
        // Aggiorna stage
        $stmt = $pdo->prepare("
            UPDATE stage_periodi 
            SET data_inizio = ?, data_fine = ?, ore_totali_previste = ?, 
                descrizione = ?, note = ?, stato = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$data['data_inizio'], $data['data_fine'], $data['ore_totali_previste'], 
            $data['descrizione'], $data['note'], $data['stato'], $stage_id]);
        
        // Aggiorna o crea tutor
        $tutor_esistente = Database::queryOne("SELECT * FROM stage_tutor WHERE stage_periodo_id = ?", [$stage_id]);
        
        $tutor_data = [
            'docente_id' => $_POST['tutor_scolastico_id'] ?? null,
            'nome_tutor_aziendale' => $_POST['nome_tutor_aziendale'] ?? '',
            'azienda' => $_POST['azienda'] ?? '',
            'telefono_azienda' => $_POST['telefono_azienda'] ?? '',
            'email_azienda' => $_POST['email_azienda'] ?? ''
        ];
        
        if ($tutor_esistente) {
            $stmt = $pdo->prepare("
                UPDATE stage_tutor 
                SET docente_id = ?, nome_tutor_aziendale = ?, azienda = ?, 
                    telefono_azienda = ?, email_azienda = ?, updated_at = NOW()
                WHERE stage_periodo_id = ?
            ");
            $stmt->execute(array_merge(array_values($tutor_data), [$stage_id]));
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO stage_tutor 
                (stage_periodo_id, docente_id, nome_tutor_aziendale, azienda, telefono_azienda, email_azienda, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute(array_merge([$stage_id], array_values($tutor_data)));
        }
        
        // Log attività
        $stmt = $pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address, created_at)
            VALUES ('stage', 'aggiornamento', ?, 'stage_periodi', ?, ?, ?, NOW())
        ");
        $stmt->execute(["Aggiornato stage ID $stage_id", $stage_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stage aggiornato con successo'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteStage() {
    $stage_id = $_GET['stage_id'] ?? null;
    if (!$stage_id) {
        throw new Exception('ID stage non specificato');
    }
    
    $stage = Database::queryOne("SELECT * FROM stage_periodi WHERE id = ?", [$stage_id]);
    if (!$stage) {
        throw new Exception('Stage non trovato');
    }
    
    if ($stage['stato'] !== 'pianificato') {
        throw new Exception('Solo gli stage in stato "pianificato" possono essere cancellati');
    }
    
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    
    try {
        // Cancella stage (soft delete)
        $stmt = $pdo->prepare("UPDATE stage_periodi SET stato = 'cancellato', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$stage_id]);
        
        // Log attività
        $stmt = $pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address, created_at)
            VALUES ('stage', 'cancellazione', ?, 'stage_periodi', ?, ?, ?, NOW())
        ");
        $stmt->execute(["Cancellato stage ID $stage_id", $stage_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stage cancellato con successo'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getStageByClasse() {
    $classe_id = $_GET['classe_id'] ?? null;
    if (!$classe_id) {
        throw new Exception('ID classe non specificato');
    }
    
    $stage = Database::queryAll("
        SELECT sp.*, c.nome as classe_nome
        FROM stage_periodi sp
        JOIN classi c ON sp.classe_id = c.id
        WHERE sp.classe_id = ? AND sp.stato != 'cancellato'
        ORDER BY sp.data_inizio DESC
    ", [$classe_id]);
    
    echo json_encode([
        'success' => true,
        'stage' => $stage
    ]);
}

function getStageInPeriodo() {
    $data_inizio = $_GET['data_inizio'] ?? null;
    $data_fine = $_GET['data_fine'] ?? null;
    
    if (!$data_inizio || !$data_fine) {
        throw new Exception('Periodo non specificato');
    }
    
    $stage = Database::queryAll("
        SELECT sp.*, c.nome as classe_nome
        FROM stage_periodi sp
        JOIN classi c ON sp.classe_id = c.id
        WHERE sp.stato != 'cancellato'
        AND (
            (sp.data_inizio BETWEEN ? AND ?) OR
            (sp.data_fine BETWEEN ? AND ?) OR
            (? BETWEEN sp.data_inizio AND sp.data_fine) OR
            (? BETWEEN sp.data_inizio AND sp.data_fine)
        )
        ORDER BY sp.data_inizio
    ", [$data_inizio, $data_fine, $data_inizio, $data_fine, $data_inizio, $data_fine]);
    
    echo json_encode([
        'success' => true,
        'stage' => $stage
    ]);
}

function bloccaCalendarioStage() {
    $stage_id = $_POST['stage_id'] ?? null;
    $azione = $_POST['azione'] ?? ''; // 'cancella' o 'sposta'
    $tipo = $_POST['tipo'] ?? ''; // 'tutte', 'selezionate', 'giorno'
    $parametri = $_POST['parametri'] ?? [];
    $opzioni = $_POST['opzioni'] ?? [];
    
    if (!$stage_id) {
        throw new Exception('ID stage non specificato');
    }
    
    $stageManager = new StageCalendarioManager();
    
    try {
        $result = $stageManager->gestisciConflittiCalendario($stage_id, $azione, $tipo, $parametri, $opzioni);
        
        echo json_encode([
            'success' => true,
            'message' => 'Operazione completata con successo',
            'dettagli' => $result
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Errore nella gestione del calendario: ' . $e->getMessage());
    }
}

function registraPresenzaGiorno() {
    $stage_id = $_POST['stage_id'] ?? null;
    $data = $_POST['data'] ?? null;
    $ore_effettuate = $_POST['ore_effettuate'] ?? 0;
    $presenza = $_POST['presenza'] ?? 1;
    $note = $_POST['note'] ?? '';
    
    if (!$stage_id || !$data) {
        throw new Exception('Stage ID e data sono obbligatori');
    }
    
    // Verifica che la data sia nel periodo dello stage
    $stage = Database::queryOne("
        SELECT * FROM stage_periodi 
        WHERE id = ? AND ? BETWEEN data_inizio AND data_fine
    ", [$stage_id, $data]);
    
    if (!$stage) {
        throw new Exception('La data selezionata non è nel periodo dello stage');
    }
    
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    
    try {
        // Verifica se esiste già una registrazione per questa data
        $giorno_esistente = Database::queryOne("
            SELECT * FROM stage_giorni 
            WHERE stage_periodo_id = ? AND data = ?
        ", [$stage_id, $data]);
        
        if ($giorno_esistente) {
            // Aggiorna registrazione esistente
            $stmt = $pdo->prepare("
                UPDATE stage_giorni 
                SET ore_effettuate = ?, presenza = ?, note = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$ore_effettuate, $presenza, $note, $giorno_esistente['id']]);
        } else {
            // Crea nuova registrazione
            $stmt = $pdo->prepare("
                INSERT INTO stage_giorni 
                (stage_periodo_id, data, ore_effettuate, presenza, note, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$stage_id, $data, $ore_effettuate, $presenza, $note]);
        }
        
        // Ricalcola ore totali effettuate
        $ore_totali = Database::queryOne("
            SELECT COALESCE(SUM(ore_effettuate), 0) as totale
            FROM stage_giorni 
            WHERE stage_periodo_id = ? AND presenza = 1
        ", [$stage_id]);
        
        $stmt = $pdo->prepare("
            UPDATE stage_periodi 
            SET ore_effettuate = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$ore_totali['totale'], $stage_id]);
        
        // Log attività
        $stmt = $pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address, created_at)
            VALUES ('stage', 'registrazione_ore', ?, 'stage_periodi', ?, ?, ?, NOW())
        ");
        $stmt->execute(["Registrate $ore_effettuate ore per stage ID $stage_id", $stage_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Presenza registrata con successo',
            'ore_totali' => $ore_totali['totale']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function uploadDocumentoStage() {
    $stage_id = $_POST['stage_id'] ?? null;
    $tipo_documento = $_POST['tipo_documento'] ?? '';
    
    if (!$stage_id || !$tipo_documento) {
        throw new Exception('Stage ID e tipo documento sono obbligatori');
    }
    
    if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Errore nel caricamento del file');
    }
    
    $file = $_FILES['documento'];
    $max_size = 10 * 1024 * 1024; // 10MB
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    if ($file['size'] > $max_size) {
        throw new Exception('Il file è troppo grande (max 10MB)');
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Tipo file non supportato. Sono ammessi: PDF, JPG, PNG, DOC, DOCX');
    }
    
    // Crea directory se non esiste
    $upload_dir = '../uploads/stage/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Genera nome file sicuro
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'stage_' . $stage_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Errore nel salvataggio del file');
    }
    
    $pdo = Database::getConnection();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO stage_documenti 
            (stage_periodo_id, tipo_documento, nome_file, nome_originale, dimensione, mime_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$stage_id, $tipo_documento, $file_name, $file['name'], $file['size'], $file['type']]);
        
        // Log attività
        $stmt = $pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address, created_at)
            VALUES ('stage', 'upload_documento', ?, 'stage_periodi', ?, ?, ?, NOW())
        ");
        $stmt->execute(["Caricato documento $tipo_documento per stage ID $stage_id", $stage_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento caricato con successo',
            'file_name' => $file_name
        ]);
        
    } catch (Exception $e) {
        // Cancella file in caso di errore DB
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        throw $e;
    }
}

function completaStage() {
    $stage_id = $_GET['stage_id'] ?? null;
    if (!$stage_id) {
        throw new Exception('ID stage non specificato');
    }
    
    $stage = Database::queryOne("SELECT * FROM stage_periodi WHERE id = ?", [$stage_id]);
    if (!$stage) {
        throw new Exception('Stage non trovato');
    }
    
    if ($stage['stato'] === 'completato') {
        throw new Exception('Stage già completato');
    }
    
    // Verifica che siano state registrate almeno il 90% delle ore previste
    $percentuale_completamento = ($stage['ore_effettuate'] / $stage['ore_totali_previste']) * 100;
    
    if ($percentuale_completamento < 90) {
        throw new Exception("Impossibile completare lo stage: completate solo il " . round($percentuale_completamento, 1) . "% delle ore previste");
    }
    
    $pdo = Database::getConnection();
    
    try {
        $stmt = $pdo->prepare("
            UPDATE stage_periodi 
            SET stato = 'completato', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$stage_id]);
        
        // Log attività
        $stmt = $pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address, created_at)
            VALUES ('stage', 'completamento', ?, 'stage_periodi', ?, ?, ?, NOW())
        ");
        $stmt->execute(["Completato stage ID $stage_id", $stage_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Stage completato con successo'
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}
?>