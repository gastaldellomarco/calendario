<?php
/**
 * Cron job per la gestione automatica degli stage
 * Eseguito giornalmente per inviare notifiche e aggiornamenti
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/StageNotifier.php';

// Log inizio esecuzione
error_log("[" . date('Y-m-d H:i:s') . "] Stage Checker: Inizio esecuzione");

try {
    $notifier = new StageNotifier();
    $pdo = Database::getPDOConnection();
    
    // 1. Stage che iniziano tra 3 giorni
    $stage_inizio_imminente = $pdo->query("
        SELECT id 
        FROM stage_periodi 
        WHERE stato = 'pianificato'
        AND data_inizio = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($stage_inizio_imminente as $stage_id) {
        $notifier->notificaInizioStage($stage_id, 3);
        error_log("Stage Checker: Notifica inizio per stage ID $stage_id");
    }
    
    // 2. Stage che terminano oggi
    $stage_fine_oggi = $pdo->query("
        SELECT id 
        FROM stage_periodi 
        WHERE stato = 'in_corso'
        AND data_fine = CURDATE()
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($stage_fine_oggi as $stage_id) {
        $notifier->notificaFineStage($stage_id);
        error_log("Stage Checker: Notifica fine per stage ID $stage_id");
    }
    
    // 3. Aggiorna stati stage
    $this->aggiornaStatiStage();
    
    // 4. Reminder registrazione ore per stage in corso
    $stage_in_corso = $pdo->query("
        SELECT id 
        FROM stage_periodi 
        WHERE stato = 'in_corso'
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($stage_in_corso as $stage_id) {
        // Reminder registrazione ore
        $notifier->reminderRegistrazioneOre($stage_id);
        
        // Notifica ore rimanenti
        $notifier->notificaOreRimanenti($stage_id);
        
        // Alert stage in ritardo
        $notifier->notificaStageInRitardo($stage_id);
        
        // Alert documenti mancanti
        $notifier->alertDocumentiMancanti($stage_id);
    }
    
    // 5. Stage completati senza documenti (follow-up)
    $stage_completati_senza_documenti = $pdo->query("
        SELECT sp.id
        FROM stage_periodi sp
        WHERE sp.stato = 'completato'
        AND sp.data_fine <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM stage_documenti sd 
            WHERE sd.stage_periodo_id = sp.id 
            AND sd.tipo_documento = 'valutazione_finale'
        )
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($stage_completati_senza_documenti as $stage_id) {
        $this->notificaDocumentiMancantiPostScadenza($stage_id);
    }
    
    error_log("[" . date('Y-m-d H:i:s') . "] Stage Checker: Esecuzione completata con successo");
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Stage Checker ERRORE: " . $e->getMessage());
}

/**
 * Aggiorna automaticamente gli stati degli stage
 */
function aggiornaStatiStage() {
    $pdo = Database::getPDOConnection();
    
    // Stage pianificati -> in corso
    $pdo->exec("
        UPDATE stage_periodi 
        SET stato = 'in_corso', updated_at = NOW()
        WHERE stato = 'pianificato' 
        AND data_inizio <= CURDATE()
        AND data_fine >= CURDATE()
    ");
    
    $aggiornati_in_corso = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    if ($aggiornati_in_corso > 0) {
        error_log("Stage Checker: Aggiornati $aggiornati_in_corso stage a 'in_corso'");
    }
    
    // Stage in corso -> completati
    $pdo->exec("
        UPDATE stage_periodi 
        SET stato = 'completato', updated_at = NOW()
        WHERE stato = 'in_corso' 
        AND data_fine < CURDATE()
    ");
    
    $aggiornati_completati = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    if ($aggiornati_completati > 0) {
        error_log("Stage Checker: Aggiornati $aggiornati_completati stage a 'completato'");
    }
}

/**
 * Notifica documenti mancanti dopo la scadenza
 */
function notificaDocumentiMancantiPostScadenza($stage_id) {
    $pdo = Database::getPDOConnection();
    $notifier = new StageNotifier();
    
    $stage = $pdo->prepare("
        SELECT sp.*, c.nome as classe_nome
        FROM stage_periodi sp
        JOIN classi c ON sp.classe_id = c.id
        WHERE sp.id = ?
    ")->execute([$stage_id])->fetch(PDO::FETCH_ASSOC);
    
    if (!$stage) return;
    
    $documenti_mancanti = $pdo->prepare("
        SELECT 'valutazione_finale' as tipo 
        FROM dual
        WHERE NOT EXISTS (
            SELECT 1 FROM stage_documenti 
            WHERE stage_periodo_id = ? 
            AND tipo_documento = 'valutazione_finale'
        )
        UNION
        SELECT 'registro_presenze' as tipo 
        FROM dual
        WHERE NOT EXISTS (
            SELECT 1 FROM stage_documenti 
            WHERE stage_periodo_id = ? 
            AND tipo_documento = 'registro_presenze'
        )
    ")->execute([$stage_id, $stage_id])->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($documenti_mancanti)) {
        // Notifica al tutor
        $tutor = $pdo->prepare("
            SELECT st.docente_id 
            FROM stage_tutor st 
            WHERE st.stage_periodo_id = ?
        ")->execute([$stage_id])->fetch(PDO::FETCH_ASSOC);
        
        if ($tutor && $tutor['docente_id']) {
            $documenti_lista = implode(', ', $documenti_mancanti);
            $giorni_dopo_scadenza = (strtotime(date('Y-m-d')) - strtotime($stage['data_fine'])) / (60 * 60 * 24);
            
            $notifier->creaNotifica(
                $tutor['docente_id'],
                'allerta',
                'alta',
                'Documenti stage ancora mancanti',
                "Sono passati $giorni_dopo_scadenza giorni dalla fine dello stage " .
                "della classe {$stage['classe_nome']} ma mancano ancora i documenti: $documenti_lista",
                'stage_periodi',
                $stage_id
            );
            
            error_log("Stage Checker: Notifica documenti mancanti post-scadenza per stage ID $stage_id");
        }
    }
}

// Esegui solo se chiamato da riga di comando
if (php_sapi_name() === 'cli') {
    // Il codice sopra viene eseguito automaticamente
} else {
    header('HTTP/1.1 403 Forbidden');
    echo 'Accesso negato';
    exit;
}
?>