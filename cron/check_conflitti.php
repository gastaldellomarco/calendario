<?php
/**
 * Script di controllo conflitti - da eseguire periodicamente via cron
 * Esempio cron: esegui ogni 15 minuti (/usr/bin/php /path/to/cron/check_conflitti.php)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/NotificheManager.php';

// Log iniziale
error_log("[" . date('Y-m-d H:i:s') . "] Avvio controllo conflitti");

try {
    $pdo = getPDOConnection();
    $notificheManager = new NotificheManager($pdo);
    
    // 1. Pulisci conflitti risolti vecchi (più di 30 giorni)
    pulisciConflittiVecchi($pdo);
    
    // 2. Controlla doppie assegnazioni docente
    $doppie_docente = controllaDoppieAssegnazioniDocente($pdo);
    
    // 3. Controlla doppie assegnazioni aula
    $doppie_aula = controllaDoppieAssegnazioniAula($pdo);
    
    // 4. Controlla vincoli docente
    $vincoli_docente = controllaVincoliDocente($pdo);
    
    // 5. Controlla vincoli classe
    $vincoli_classe = controllaVincoliClasse($pdo);
    
    // 6. Controlla superamento ore
    $superamento_ore = controllaSuperamentoOre($pdo);
    
    // 7. Controlla aule non adeguate
    $aule_non_adeguate = controllaAuleNonAdeguate($pdo);
    
    // 8. Controlla sede multipla
    $sede_multipla = controllaSedeMultipla($pdo);
    
    // Statistiche
    $total_conflitti = count($doppie_docente) + count($doppie_aula) + count($vincoli_docente) + 
                      count($vincoli_classe) + count($superamento_ore) + count($aule_non_adeguate) + 
                      count($sede_multipla);
    
    error_log("[" . date('Y-m-d H:i:s') . "] Controllo completato: {$total_conflitti} conflitti rilevati");
    
    // Invia notifica se ci sono nuovi conflitti critici
    if ($total_conflitti > 0) {
        $conflitti_critici = array_merge(
            array_filter($doppie_docente, fn($c) => $c['gravita'] === 'critico'),
            array_filter($doppie_aula, fn($c) => $c['gravita'] === 'critico'),
            array_filter($superamento_ore, fn($c) => $c['gravita'] === 'critico')
        );
        
        if (count($conflitti_critici) > 0) {
            $notificheManager->creaNotifica(
                getAdminUserId($pdo),
                'conflitto',
                'Conflitti critici rilevati',
                "Sono stati rilevati " . count($conflitti_critici) . " conflitti critici che richiedono attenzione immediata",
                'urgente',
                null,
                null,
                '/pages/conflitti.php'
            );
        }
    }
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] ERRORE controllo conflitti: " . $e->getMessage());
}

/**
 * Pulisce conflitti risolti vecchi
 */
function pulisciConflittiVecchi($pdo) {
    $sql = "DELETE FROM conflitti_orario 
            WHERE risolto = 1 
            AND data_risoluzione < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $deleted = $stmt->rowCount();
    if ($deleted > 0) {
        error_log("Puliti {$deleted} conflitti risolti vecchi");
    }
}

/**
 * Controlla doppie assegnazioni docente
 */
function controllaDoppieAssegnazioniDocente($pdo) {
    $sql = "SELECT cl1.id as lezione1_id, cl2.id as lezione2_id,
                   cl1.docente_id, cl1.data_lezione, cl1.slot_id,
                   d.cognome, d.nome,
                   CONCAT('Doppia assegnazione docente: ', d.cognome, ' ', d.nome, 
                          ' il ', DATE_FORMAT(cl1.data_lezione, '%d/%m/%Y'), 
                          ' alle ', TIME_FORMAT(os.ora_inizio, '%H:%i')) as descrizione
            FROM calendario_lezioni cl1
            JOIN calendario_lezioni cl2 ON cl1.docente_id = cl2.docente_id 
                AND cl1.data_lezione = cl2.data_lezione 
                AND cl1.slot_id = cl2.slot_id 
                AND cl1.id != cl2.id
                AND cl1.stato != 'cancellata'
                AND cl2.stato != 'cancellata'
            JOIN docenti d ON cl1.docente_id = d.id
            JOIN orari_slot os ON cl1.slot_id = os.id
            LEFT JOIN conflitti_orario co ON co.lezione_id = cl1.id 
                AND co.tipo = 'doppia_assegnazione_docente'
                AND co.risolto = 0
            WHERE co.id IS NULL
            GROUP BY cl1.docente_id, cl1.data_lezione, cl1.slot_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $conflitti = $stmt->fetchAll();
    
    foreach ($conflitti as $conflitto) {
        creaConflitto($pdo, [
            'tipo' => 'doppia_assegnazione_docente',
            'gravita' => 'critico',
            'titolo' => 'Doppia assegnazione docente',
            'descrizione' => $conflitto['descrizione'],
            'docente_id' => $conflitto['docente_id'],
            'lezione_id' => $conflitto['lezione1_id'],
            'data_conflitto' => $conflitto['data_lezione'],
            'slot_id' => $conflitto['slot_id'],
            'dati_conflitto' => json_encode([
                'lezione1_id' => $conflitto['lezione1_id'],
                'lezione2_id' => $conflitto['lezione2_id'],
                'docente' => $conflitto['cognome'] . ' ' . $conflitto['nome']
            ])
        ]);
    }
    
    error_log("Doppie assegnazioni docente: " . count($conflitti));
    return $conflitti;
}

/**
 * Controlla doppie assegnazioni aula
 */
function controllaDoppieAssegnazioniAula($pdo) {
    $sql = "SELECT cl1.id as lezione1_id, cl2.id as lezione2_id,
                   cl1.aula_id, cl1.data_lezione, cl1.slot_id,
                   a.nome as aula_nome,
                   CONCAT('Doppia assegnazione aula: ', a.nome, 
                          ' il ', DATE_FORMAT(cl1.data_lezione, '%d/%m/%Y'), 
                          ' alle ', TIME_FORMAT(os.ora_inizio, '%H:%i')) as descrizione
            FROM calendario_lezioni cl1
            JOIN calendario_lezioni cl2 ON cl1.aula_id = cl2.aula_id 
                AND cl1.aula_id IS NOT NULL
                AND cl1.data_lezione = cl2.data_lezione 
                AND cl1.slot_id = cl2.slot_id 
                AND cl1.id != cl2.id
                AND cl1.stato != 'cancellata'
                AND cl2.stato != 'cancellata'
            JOIN aule a ON cl1.aula_id = a.id
            JOIN orari_slot os ON cl1.slot_id = os.id
            LEFT JOIN conflitti_orario co ON co.lezione_id = cl1.id 
                AND co.tipo = 'doppia_aula'
                AND co.risolto = 0
            WHERE co.id IS NULL
            GROUP BY cl1.aula_id, cl1.data_lezione, cl1.slot_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $conflitti = $stmt->fetchAll();
    
    foreach ($conflitti as $conflitto) {
        creaConflitto($pdo, [
            'tipo' => 'doppia_aula',
            'gravita' => 'error',
            'titolo' => 'Doppia assegnazione aula',
            'descrizione' => $conflitto['descrizione'],
            'aula_id' => $conflitto['aula_id'],
            'lezione_id' => $conflitto['lezione1_id'],
            'data_conflitto' => $conflitto['data_lezione'],
            'slot_id' => $conflitto['slot_id'],
            'dati_conflitto' => json_encode([
                'lezione1_id' => $conflitto['lezione1_id'],
                'lezione2_id' => $conflitto['lezione2_id'],
                'aula' => $conflitto['aula_nome']
            ])
        ]);
    }
    
    error_log("Doppie assegnazioni aula: " . count($conflitti));
    return $conflitti;
}

/**
 * Controlla vincoli docente
 */
function controllaVincoliDocente($pdo) {
    $sql = "SELECT cl.id as lezione_id, vd.docente_id, vd.giorno_settimana,
                   vd.ora_inizio, vd.ora_fine, vd.motivo,
                   d.cognome, d.nome,
                   CONCAT('Vincolo docente violato: ', d.cognome, ' ', d.nome, 
                          ' - ', vd.motivo) as descrizione
            FROM calendario_lezioni cl
            JOIN vincoli_docenti vd ON cl.docente_id = vd.docente_id
            JOIN docenti d ON cl.docente_id = d.id
            JOIN orari_slot os ON cl.slot_id = os.id
            WHERE vd.attivo = 1
            AND vd.tipo = 'indisponibilita'
            AND DAYOFWEEK(cl.data_lezione) = vd.giorno_settimana
            AND (
                (vd.ora_inizio IS NULL AND vd.ora_fine IS NULL) OR
                (os.ora_inizio >= vd.ora_inizio AND os.ora_fine <= vd.ora_fine)
            )
            AND cl.stato != 'cancellata'
            LEFT JOIN conflitti_orario co ON co.lezione_id = cl.id 
                AND co.tipo = 'vincolo_docente'
                AND co.risolto = 0
            WHERE co.id IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $conflitti = $stmt->fetchAll();
    
    foreach ($conflitti as $conflitto) {
        creaConflitto($pdo, [
            'tipo' => 'vincolo_docente',
            'gravita' => 'error',
            'titolo' => 'Vincolo docente violato',
            'descrizione' => $conflitto['descrizione'],
            'docente_id' => $conflitto['docente_id'],
            'lezione_id' => $conflitto['lezione_id'],
            'data_conflitto' => date('Y-m-d'), // Usa data corrente per conflitti di vincolo
            'dati_conflitto' => json_encode([
                'vincolo_motivo' => $conflitto['motivo'],
                'giorno_settimana' => $conflitto['giorno_settimana'],
                'ora_inizio' => $conflitto['ora_inizio'],
                'ora_fine' => $conflitto['ora_fine']
            ])
        ]);
    }
    
    error_log("Vincoli docente violati: " . count($conflitti));
    return $conflitti;
}

/**
 * Controlla superamento ore docente
 */
function controllaSuperamentoOre($pdo) {
    $sql = "SELECT d.id as docente_id, d.cognome, d.nome,
                   SUM(cmd.ore_settimanali) as ore_assegnate,
                   d.max_ore_settimana,
                   CONCAT('Superamento ore contrattuali: ', d.cognome, ' ', d.nome, 
                          ' - ', SUM(cmd.ore_settimanali), '/', d.max_ore_settimana, ' ore') as descrizione
            FROM docenti d
            JOIN classi_materie_docenti cmd ON d.id = cmd.docente_id
            WHERE cmd.attivo = 1
            AND d.stato = 'attivo'
            GROUP BY d.id
            HAVING ore_assegnate > d.max_ore_settimana
            AND NOT EXISTS (
                SELECT 1 FROM conflitti_orario co 
                WHERE co.docente_id = d.id 
                AND co.tipo = 'superamento_ore'
                AND co.risolto = 0
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $conflitti = $stmt->fetchAll();
    
    foreach ($conflitti as $conflitto) {
        creaConflitto($pdo, [
            'tipo' => 'superamento_ore',
            'gravita' => 'error',
            'titolo' => 'Superamento ore contrattuali',
            'descrizione' => $conflitto['descrizione'],
            'docente_id' => $conflitto['docente_id'],
            'data_conflitto' => date('Y-m-d'),
            'dati_conflitto' => json_encode([
                'ore_assegnate' => $conflitto['ore_assegnate'],
                'max_ore_settimana' => $conflitto['max_ore_settimana'],
                'eccedenza' => $conflitto['ore_assegnate'] - $conflitto['max_ore_settimana']
            ])
        ]);
    }
    
    error_log("Superamento ore: " . count($conflitti));
    return $conflitti;
}

/**
 * Controlla aule non adeguate
 */
function controllaAuleNonAdeguate($pdo) {
    $sql = "SELECT cl.id as lezione_id, cl.aula_id, cl.classe_id,
                   c.numero_studenti, a.capienza, a.tipo,
                   m.richiede_laboratorio,
                   CONCAT('Aula non adeguata: capienza ', c.numero_studenti, 
                          ' > ', a.capienza) as descrizione
            FROM calendario_lezioni cl
            JOIN classi c ON cl.classe_id = c.id
            JOIN aule a ON cl.aula_id = a.id
            JOIN materie m ON cl.materia_id = m.id
            WHERE cl.stato != 'cancellata'
            AND (c.numero_studenti > a.capienza OR 
                (m.richiede_laboratorio = 1 AND a.tipo != 'laboratorio'))
            AND NOT EXISTS (
                SELECT 1 FROM conflitti_orario co 
                WHERE co.lezione_id = cl.id 
                AND co.tipo = 'aula_non_adeguata'
                AND co.risolto = 0
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $conflitti = $stmt->fetchAll();
    
    foreach ($conflitti as $conflitto) {
        creaConflitto($pdo, [
            'tipo' => 'aula_non_adeguata',
            'gravita' => 'warning',
            'titolo' => 'Aula non adeguata',
            'descrizione' => $conflitto['descrizione'],
            'aula_id' => $conflitto['aula_id'],
            'classe_id' => $conflitto['classe_id'],
            'lezione_id' => $conflitto['lezione_id'],
            'data_conflitto' => date('Y-m-d'),
            'dati_conflitto' => json_encode([
                'numero_studenti' => $conflitto['numero_studenti'],
                'capienza_aula' => $conflitto['capienza'],
                'richiede_laboratorio' => $conflitto['richiede_laboratorio'],
                'tipo_aula' => $conflitto['tipo']
            ])
        ]);
    }
    
    error_log("Aule non adeguate: " . count($conflitti));
    return $conflitti;
}

/**
 * Controlla lezioni senza aula
 */
function controllaSedeMultipla($pdo) {
    $sql = "SELECT cl.id as lezione_id, cl.docente_id, cl.data_lezione,
                   d.sede_principale_id, cl.sede_id,
                   CONCAT('Docente in sede multipla: ', d.cognome, ' ', d.nome) as descrizione
            FROM calendario_lezioni cl
            JOIN docenti d ON cl.docente_id = d.id
            WHERE cl.stato != 'cancellata'
            AND cl.sede_id != d.sede_principale_id
            AND NOT EXISTS (
                SELECT 1 FROM conflitti_orario co 
                WHERE co.lezione_id = cl.id 
                AND co.tipo = 'sede_multipla'
                AND co.risolto = 0
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $conflitti = $stmt->fetchAll();
    
    foreach ($conflitti as $conflitto) {
        creaConflitto($pdo, [
            'tipo' => 'sede_multipla',
            'gravita' => 'warning',
            'titolo' => 'Docente in sede multipla',
            'descrizione' => $conflitto['descrizione'],
            'docente_id' => $conflitto['docente_id'],
            'lezione_id' => $conflitto['lezione_id'],
            'data_conflitto' => $conflitto['data_lezione'],
            'dati_conflitto' => json_encode([
                'sede_principale' => $conflitto['sede_principale_id'],
                'sede_lezione' => $conflitto['sede_id']
            ])
        ]);
    }
    
    error_log("Sede multipla: " . count($conflitti));
    return $conflitti;
}

/**
 * Crea record conflitto nel database
 */
function creaConflitto($pdo, $dati) {
    $sql = "INSERT INTO conflitti_orario 
            (tipo, gravita, titolo, descrizione, dati_conflitto, 
             docente_id, classe_id, aula_id, lezione_id, data_conflitto, slot_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $dati['tipo'],
        $dati['gravita'],
        $dati['titolo'],
        $dati['descrizione'],
        $dati['dati_conflitto'] ?? null,
        $dati['docente_id'] ?? null,
        $dati['classe_id'] ?? null,
        $dati['aula_id'] ?? null,
        $dati['lezione_id'] ?? null,
        $dati['data_conflitto'],
        $dati['slot_id'] ?? null
    ]);
    
    if ($success) {
        // Invia notifica per conflitti critici
        if ($dati['gravita'] === 'critico') {
            $notificheManager = new NotificheManager($pdo);
            $notificheManager->notificaConflittoOrario($pdo->lastInsertId());
        }
    }
    
    return $success;
}

/**
 * Ottiene ID di un admin per le notifiche
 */
function getAdminUserId($pdo) {
    $sql = "SELECT id FROM utenti WHERE ruolo = 'amministratore' AND attivo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $admin = $stmt->fetch();
    
    return $admin ? $admin['id'] : 1; // Fallback all'admin di default
}