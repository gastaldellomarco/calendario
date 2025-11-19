<?php
/**
 * Script notifiche giornaliere - da eseguire ogni mattina via cron
 * Esempio cron: 0 7 * * * /usr/bin/php /path/to/cron/notifiche_giornaliere.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/NotificheManager.php';

// Log iniziale
error_log("[" . date('Y-m-d H:i:s') . "] Avvio notifiche giornaliere");

try {
    $pdo = getPDOConnection();
    $notificheManager = new NotificheManager($pdo);
    
    $oggi = date('Y-m-d');
    
    // 1. Notifiche lezioni del giorno ai docenti
    $notifiche_inviate = inviaNotificheLezioniGiornaliere($pdo, $notificheManager, $oggi);
    
    // 2. Notifica riepilogo al preside
    inviaRiepilogoPreside($pdo, $notificheManager, $oggi);
    
    // 3. Controlla e notifica stage in scadenza
    $stage_notificati = controllaStageScadenza($pdo, $notificheManager);
    
    // 4. Controlla e notifica conflitti aperti
    $conflitti_notificati = controllaConflittiAperti($pdo, $notificheManager);
    
    error_log("[" . date('Y-m-d H:i:s') . "] Notifiche giornaliere completate:");
    error_log("  - Notifiche lezioni: {$notifiche_inviate}");
    error_log("  - Stage in scadenza: {$stage_notificati}");
    error_log("  - Conflitti aperti: {$conflitti_notificati}");
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] ERRORE notifiche giornaliere: " . $e->getMessage());
}

/**
 * Invia notifiche lezioni del giorno ai docenti
 */
function inviaNotificheLezioniGiornaliere($pdo, $notificheManager, $data) {
    $sql = "SELECT cl.*, d.id as docente_id, d.nome as docente_nome, d.cognome as docente_cognome,
                   c.nome as classe_nome, m.nome as materia_nome, a.nome as aula_nome,
                   os.ora_inizio, os.ora_fine, s.nome as sede_nome
            FROM calendario_lezioni cl
            JOIN docenti d ON cl.docente_id = d.id
            JOIN classi c ON cl.classe_id = c.id
            JOIN materie m ON cl.materia_id = m.id
            LEFT JOIN aule a ON cl.aula_id = a.id
            JOIN orari_slot os ON cl.slot_id = os.id
            JOIN sedi s ON cl.sede_id = s.id
            WHERE cl.data_lezione = ? 
            AND cl.stato != 'cancellata'
            ORDER BY d.id, os.ora_inizio";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data]);
    $lezioni = $stmt->fetchAll();
    
    if (empty($lezioni)) {
        error_log("Nessuna lezione trovata per oggi");
        return 0;
    }
    
    // Raggruppa lezioni per docente
    $lezioni_per_docente = [];
    foreach ($lezioni as $lezione) {
        $lezioni_per_docente[$lezione['docente_id']][] = $lezione;
    }
    
    $notifiche_inviate = 0;
    
    foreach ($lezioni_per_docente as $docente_id => $lezioni_docente) {
        $messaggio = "📚 Riepilogo lezioni per oggi " . date('d/m/Y') . "\n\n";
        
        foreach ($lezioni_docente as $lezione) {
            $ora_inizio = date('H:i', strtotime($lezione['ora_inizio']));
            $messaggio .= "⏰ {$ora_inizio} - {$lezione['materia_nome']}\n";
            $messaggio .= "   👥 {$lezione['classe_nome']}\n";
            
            if ($lezione['aula_nome']) {
                $messaggio .= "   🏫 Aula: {$lezione['aula_nome']}\n";
            }
            
            if ($lezione['sede_nome']) {
                $messaggio .= "   📍 Sede: {$lezione['sede_nome']}\n";
            }
            
            if ($lezione['argomento']) {
                $messaggio .= "   📝 Argomento: {$lezione['argomento']}\n";
            }
            
            $messaggio .= "\n";
        }
        
        $messaggio .= "Buona giornata di lavoro! 🍀";
        
        $success = $notificheManager->creaNotifica(
            $docente_id,
            'info',
            'Riepilogo lezioni giornaliero',
            $messaggio,
            'bassa',
            null,
            null,
            "/pages/calendario.php?data={$data}"
        );
        
        if ($success) {
            $notifiche_inviate++;
        }
    }
    
    return $notifiche_inviate;
}

/**
 * Invia riepilogo giornaliero al preside
 */
function inviaRiepilogoPreside($pdo, $notificheManager, $data) {
    // Statistiche lezioni del giorno
    $sql_lezioni = "SELECT 
        COUNT(*) as total_lezioni,
        COUNT(DISTINCT docente_id) as docenti_coinvolti,
        COUNT(DISTINCT classe_id) as classi_coinvolte,
        COUNT(DISTINCT materia_id) as materie_previste
        FROM calendario_lezioni 
        WHERE data_lezione = ? 
        AND stato != 'cancellata'";
    
    $stmt = $pdo->prepare($sql_lezioni);
    $stmt->execute([$data]);
    $stats_lezioni = $stmt->fetch();
    
    // Conflitti aperti
    $sql_conflitti = "SELECT COUNT(*) as conflitti_aperti FROM conflitti_orario WHERE risolto = 0";
    $stmt = $pdo->prepare($sql_conflitti);
    $stmt->execute();
    $conflitti_aperti = $stmt->fetchColumn();
    
    // Lezioni senza aula
    $sql_senza_aula = "SELECT COUNT(*) as senza_aula 
                      FROM calendario_lezioni 
                      WHERE data_lezione = ? 
                      AND aula_id IS NULL 
                      AND stato != 'cancellata'";
    
    $stmt = $pdo->prepare($sql_senza_aula);
    $stmt->execute([$data]);
    $senza_aula = $stmt->fetchColumn();
    
    $messaggio = "📊 Riepilogo giornaliero - " . date('d/m/Y') . "\n\n";
    $messaggio .= "📚 Lezioni oggi:\n";
    $messaggio .= "   • Totale: {$stats_lezioni['total_lezioni']}\n";
    $messaggio .= "   • Docenti: {$stats_lezioni['docenti_coinvolti']}\n";
    $messaggio .= "   • Classi: {$stats_lezioni['classi_coinvolte']}\n";
    $messaggio .= "   • Materie: {$stats_lezioni['materie_previste']}\n\n";
    
    $messaggio .= "⚠️  Situazione:\n";
    $messaggio .= "   • Conflitti aperti: {$conflitti_aperti}\n";
    $messaggio .= "   • Lezioni senza aula: {$senza_aula}\n\n";
    
    if ($conflitti_aperti > 0) {
        $messaggio .= "🔴 Attenzione: Ci sono {$conflitti_aperti} conflitti che richiedono attenzione.\n";
    } else {
        $messaggio .= "✅ Tutto sotto controllo!\n";
    }
    
    // Ottieni ID del preside
    $sql_preside = "SELECT id FROM utenti WHERE ruolo = 'preside' AND attivo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql_preside);
    $stmt->execute();
    $preside = $stmt->fetch();
    
    if ($preside) {
        $notificheManager->creaNotifica(
            $preside['id'],
            'info',
            'Riepilogo giornaliero',
            $messaggio,
            'media',
            null,
            null,
            '/dashboard.php'
        );
    }
}

/**
 * Controlla e notifica stage in scadenza
 */
function controllaStageScadenza($pdo, $notificheManager) {
    $sql = "SELECT s.*, c.nome as classe_nome, 
                   DATEDIFF(s.data_fine, CURDATE()) as giorni_alla_scadenza
            FROM stage_periodi s
            JOIN classi c ON s.classe_id = c.id
            WHERE s.stato = 'in_corso'
            AND s.data_fine BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stages = $stmt->fetchAll();
    
    $notificati = 0;
    
    foreach ($stages as $stage) {
        $giorni_rimanenti = $stage['giorni_alla_scadenza'];
        
        if ($giorni_rimanenti <= 7) {
            $titolo = "Stage in scadenza";
            $messaggio = "Lo stage della classe {$stage['classe_nome']} scade tra {$giorni_rimanenti} giorni";
            $priorita = $giorni_rimanenti <= 3 ? 'urgente' : 'alta';
            
            // Notifica ai tutor
            $sql_tutor = "SELECT docente_id FROM stage_tutor WHERE stage_periodo_id = ?";
            $stmt_tutor = $pdo->prepare($sql_tutor);
            $stmt_tutor->execute([$stage['id']]);
            $tutors = $stmt_tutor->fetchAll();
            
            foreach ($tutors as $tutor) {
                if ($tutor['docente_id']) {
                    $notificheManager->creaNotifica(
                        $tutor['docente_id'],
                        'allerta',
                        $titolo,
                        $messaggio,
                        $priorita,
                        'stage_periodi',
                        $stage['id'],
                        "/pages/stage.php"
                    );
                    $notificati++;
                }
            }
            
            // Notifica al preside
            $sql_preside = "SELECT id FROM utenti WHERE ruolo = 'preside' AND attivo = 1 LIMIT 1";
            $stmt_preside = $pdo->prepare($sql_preside);
            $stmt_preside->execute();
            $preside = $stmt_preside->fetch();
            
            if ($preside) {
                $notificheManager->creaNotifica(
                    $preside['id'],
                    'allerta',
                    $titolo,
                    $messaggio,
                    $priorita,
                    'stage_periodi',
                    $stage['id'],
                    "/pages/stage.php"
                );
                $notificati++;
            }
        }
    }
    
    return $notificati;
}

/**
 * Controlla e notifica conflitti aperti
 */
function controllaConflittiAperti($pdo, $notificheManager) {
    $sql = "SELECT COUNT(*) as total, 
                   SUM(gravita = 'critico') as critici,
                   SUM(gravita = 'error') as errori
            FROM conflitti_orario 
            WHERE risolto = 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats = $stmt->fetch();
    
    if ($stats['total'] > 0) {
        $messaggio = "Ci sono {$stats['total']} conflitti aperti: ";
        $messaggio .= "{$stats['critici']} critici, {$stats['errori']} errori";
        
        $priorita = $stats['critici'] > 0 ? 'alta' : 'media';
        
        // Notifica a preside e amministratori
        $sql_admin = "SELECT id FROM utenti WHERE ruolo IN ('amministratore', 'preside') AND attivo = 1";
        $stmt_admin = $pdo->prepare($sql_admin);
        $stmt_admin->execute();
        $admins = $stmt_admin->fetchAll();
        
        $notificati = 0;
        
        foreach ($admins as $admin) {
            $notificheManager->creaNotifica(
                $admin['id'],
                'conflitto',
                'Conflitti aperti',
                $messaggio,
                $priorita,
                null,
                null,
                '/pages/conflitti.php'
            );
            $notificati++;
        }
        
        return $notificati;
    }
    
    return 0;
}