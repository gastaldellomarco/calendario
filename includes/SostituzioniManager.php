<?php
/**
 * Classe per la gestione delle sostituzioni
 */

/**
 * Classe per la gestione delle sostituzioni
 *
 * Questa classe si occupa di:
 * - Registrare assenze e creare sostituzioni automatiche
 * - Ottenere lezioni che richiedono sostituzione per un docente in un periodo
 * - Applicare, confermare e annullare sostituzioni
 * - Fornire statistiche e storico delle sostituzioni
 *
 * @package Scuola
 */
class SostituzioniManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    // Helper: esegui query con prepared statement e parametri
    private function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Registra un'assenza per un docente e crea record di sostituzione per le lezioni
     * presenti nel periodo indicato.
     *
     * @param int $docente_id ID del docente che sarà assente
     * @param string $data_inizio Data inizio assenza (Y-m-d)
     * @param string $data_fine Data fine assenza (Y-m-d)
     * @param string $motivo Motivo dell'assenza
     * @param string $note Note opzionali
     *
     * @return array Associative array con chiavi: success (bool), message (string), [lezioni] (array), [sostituzioni_creati] (int)
     *
     * @throws InvalidArgumentException Se i parametri non sono validi
     * @throws Exception Se la query fallisce
     *
     * @example
     * $result = $sostManager->creaAssenza(5, '2024-01-15', '2024-01-20', 'malattia');
     */
    public function creaAssenza($docente_id, $data_inizio, $data_fine, $motivo, $note = '') {
        try {
            // Verifica dati
            if (empty($docente_id) || empty($data_inizio) || empty($data_fine)) {
                return ['success' => false, 'message' => 'Dati mancanti'];
            }
            
            // Trova lezioni nel periodo
            $lezioni = $this->getLezioniDaSostituire($docente_id, $data_inizio, $data_fine);
            
            if (empty($lezioni)) {
                return ['success' => true, 'message' => 'Nessuna lezione da sostituire nel periodo specificato', 'lezioni' => []];
            }
            
            // Crea record per ogni lezione
            $sostituzioni_creati = 0;
            foreach ($lezioni as $lezione) {
                $this->query("
                    INSERT INTO sostituzioni (lezione_id, docente_originale_id, motivo, note)
                    VALUES (?, ?, ?, ?)
                ", [$lezione['id'], $docente_id, $motivo, $note]);
                $sostituzioni_creati++;
            }
            return ['success' => true, 'message' => 'Assenza registrata', 'lezioni' => $lezioni, 'sostituzioni_creati' => $sostituzioni_creati];
        } catch (Exception $e) {
            error_log("Errore creaAssenza: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la registrazione dell\'assenza'];
        }
    }
    
    /**
     * Ottiene lezioni da sostituire per un docente nel periodo
     *
     * @param int $docente_id
     * @param string $data_inizio Data inizio (Y-m-d)
     * @param string $data_fine Data fine (Y-m-d)
     *
     * @return array Array associativo di lezioni (id, data_lezione, classe_nome, materia_nome, ecc.)
     */
    public function getLezioniDaSostituire($docente_id, $data_inizio, $data_fine) {
    $stmt = $this->query("
            SELECT cl.id, cl.data_lezione, c.nome as classe_nome, m.nome as materia_nome,
                   os.ora_inizio, os.ora_fine, s.nome as sede_nome,
                   (SELECT COUNT(*) FROM sostituzioni s WHERE s.lezione_id = cl.id) as gia_sostituita
            FROM calendario_lezioni cl
            JOIN classi c ON cl.classe_id = c.id
            JOIN materie m ON cl.materia_id = m.id
            JOIN orari_slot os ON cl.slot_id = os.id
            JOIN sedi s ON cl.sede_id = s.id
            WHERE cl.docente_id = ?
            AND cl.data_lezione BETWEEN ? AND ?
            AND cl.stato IN ('pianificata', 'confermata')
            AND (SELECT COUNT(*) FROM sostituzioni s WHERE s.lezione_id = cl.id) = 0
            ORDER BY cl.data_lezione, os.ora_inizio
    ", [$docente_id, $data_inizio, $data_fine]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Applica una sostituzione a una lezione, aggiornando il calendario e creando
     * un record nella tabella `sostituzioni`.
     *
     * @param int $lezione_id ID della lezione
     * @param int $docente_sostituto_id ID del docente sostituto
     * @return array Associative array con chiavi: success (bool), message (string)
     * @throws Exception In caso di errore nella transazione
     */
    public function applicaSostituzione($lezione_id, $docente_sostituto_id) {
        try {
            $this->db->beginTransaction();
            
            // Verifica che la lezione esista e non sia già sostituita
            $lezione = $this->query("
                SELECT * FROM calendario_lezioni 
                WHERE id = ? AND stato IN ('pianificata', 'confermata')
            ", [$lezione_id])->fetch(PDO::FETCH_ASSOC);
            
            if (!$lezione) {
                throw new Exception("Lezione non trovata o già annullata");
            }
            
            // Crea record sostituzione
            $this->query("
                INSERT INTO sostituzioni (lezione_id, docente_originale_id, docente_sostituto_id, motivo, note, confermata)
                VALUES (?, ?, ?, 'sostituzione', 'Assegnazione automatica', 1)
            ", [$lezione_id, $lezione['docente_id'], $docente_sostituto_id]);
            
            $sostituzione_id = $this->db->lastInsertId();
            
            // Aggiorna calendario
            $this->query("
                UPDATE calendario_lezioni 
                SET docente_id = ?, modificato_manualmente = 1, updated_at = NOW()
                WHERE id = ?
            ", [$docente_sostituto_id, $lezione_id]);
            
            // Invia notifica
            $this->inviaNotificaSostituzione($sostituzione_id);
            
            $this->db->commit();
            
            $this->logAttivita("applica_sostituzione", "Lezione: $lezione_id, Sostituto: $docente_sostituto_id");
            
            return ['success' => true, 'message' => 'Sostituzione applicata con successo'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Errore applicaSostituzione: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'applicazione della sostituzione'];
        }
    }
    
    /**
     * Conferma una sostituzione (setta confermata = 1)
     *
     * @param int $sostituzione_id
     * @return array Associative array con chiavi: success (bool), message (string)
     */
    public function confermaSostituzione($sostituzione_id) {
        try {
            $this->query("UPDATE sostituzioni SET confermata = 1 WHERE id = ?", [$sostituzione_id]);
            
            $this->logAttivita("conferma_sostituzione", "Sostituzione: $sostituzione_id");
            
            return ['success' => true, 'message' => 'Sostituzione confermata'];
            
        } catch (Exception $e) {
            error_log("Errore confermaSostituzione: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la conferma'];
        }
    }
    
    /**
     * Annulla una sostituzione, ripristinando il docente originale nella lezione
     * e rimuovendo il record di sostituzione.
     *
     * @param int $sostituzione_id
     * @return array Associative array con chiavi: success (bool), message (string)
     */
    public function annullaSostituzione($sostituzione_id) {
        try {
            $this->db->beginTransaction();
            
            // Ripristina docente originale
            $sostituzione = $this->query("SELECT * FROM sostituzioni WHERE id = ?", [$sostituzione_id])->fetch(PDO::FETCH_ASSOC);
            
            if ($sostituzione) {
                $this->query("UPDATE calendario_lezioni SET docente_id = ?, modificato_manualmente = 1, updated_at = NOW() WHERE id = ?", [$sostituzione['docente_originale_id'], $sostituzione['lezione_id']]);
                
                // Elimina record sostituzione
                $this->query("DELETE FROM sostituzioni WHERE id = ?", [$sostituzione_id]);
            }
            
            $this->db->commit();
            
            $this->logAttivita("annulla_sostituzione", "Sostituzione: $sostituzione_id");
            
            return ['success' => true, 'message' => 'Sostituzione annullata'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Errore annullaSostituzione: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'annullamento'];
        }
    }
    
    /**
     * Ottiene sostituzioni attive (in corso) con filtri opzionali.
     *
     * @param string|null $filtro_data Se impostata, filtra per la data della lezione
     * @param string $filtro_stato 'tutti'|'confermata'|'in_attesa'
     * @return array Array associativo con sostituzioni
     */
    public function getSostituzioniAttive($filtro_data = null, $filtro_stato = 'tutti') {
        $params = [];
        $where = "WHERE s.id IS NOT NULL";
        
        if ($filtro_data) {
            $where .= " AND cl.data_lezione = ?";
            $params[] = $filtro_data;
        }
        
        if ($filtro_stato != 'tutti') {
            if ($filtro_stato == 'confermata') {
                $where .= " AND s.confermata = 1";
            } elseif ($filtro_stato == 'in_attesa') {
                $where .= " AND (s.confermata = 0 OR s.confermata IS NULL)";
            }
        }
        
        $stmt = $this->db->prepare("
            SELECT s.*, cl.data_lezione, cl.id as lezione_id,
                   c.nome as classe_nome, m.nome as materia_nome,
                   os.ora_inizio, os.ora_fine,
                   CONCAT(do.cognome, ' ', do.nome) as docente_originale,
                   CONCAT(ds.cognome, ' ', ds.nome) as docente_sostituto,
                   ds.id as docente_sostituto_id,
                   CASE 
                     WHEN s.confermata = 1 THEN 'confermata'
                     ELSE 'in_attesa'
                   END as stato
            FROM sostituzioni s
            JOIN calendario_lezioni cl ON s.lezione_id = cl.id
            JOIN classi c ON cl.classe_id = c.id
            JOIN materie m ON cl.materia_id = m.id
            JOIN orari_slot os ON cl.slot_id = os.id
            JOIN docenti do ON s.docente_originale_id = do.id
            LEFT JOIN docenti ds ON s.docente_sostituto_id = ds.id
            $where
            ORDER BY cl.data_lezione, os.ora_inizio
        ");
        $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottiene lo storico delle sostituzioni per un intervallo di date.
     *
     * @param string $data_inizio Data inizio (Y-m-d)
     * @param string $data_fine Data fine (Y-m-d)
     * @return array Array associativo con le sostituzioni (fetchAll)
     */
    public function getStoricoSostituzioni($data_inizio, $data_fine) {
    $stmt = $this->query("
            SELECT s.*, cl.data_lezione,
                   c.nome as classe_nome, m.nome as materia_nome,
                   CONCAT(do.cognome, ' ', do.nome) as docente_originale,
                   CONCAT(ds.cognome, ' ', ds.nome) as docente_sostituto,
                   s.motivo
            FROM sostituzioni s
            JOIN calendario_lezioni cl ON s.lezione_id = cl.id
            JOIN classi c ON cl.classe_id = c.id
            JOIN materie m ON cl.materia_id = m.id
            JOIN docenti do ON s.docente_originale_id = do.id
            LEFT JOIN docenti ds ON s.docente_sostituto_id = ds.id
            WHERE cl.data_lezione BETWEEN ? AND ?
            ORDER BY cl.data_lezione DESC
            LIMIT 100
        ", [$data_inizio, $data_fine]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calcola statistiche sulle sostituzioni per un intervallo di date.
     *
     * @param string $data_inizio Data inizio (Y-m-d)
     * @param string $data_fine Data fine (Y-m-d)
     * @return array Statistiche aggregate (totali, confermate, in_attesa, per_motivo)
     */
    public function calcolaStatisticheSostituzioni($data_inizio, $data_fine) {
        try {
            // Query principale per statistiche totali
                $stats_stmt = $this->query("
                SELECT 
                    COUNT(*) as totali,
                    SUM(CASE WHEN s.confermata = 1 THEN 1 ELSE 0 END) as confermate,
                    SUM(CASE WHEN s.confermata = 0 OR s.confermata IS NULL THEN 1 ELSE 0 END) as in_attesa
                FROM sostituzioni s
                JOIN calendario_lezioni cl ON s.lezione_id = cl.id
                WHERE cl.data_lezione BETWEEN ? AND ?
            ", [$data_inizio, $data_fine]);
            $stats_totali = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Query per statistiche per motivo
                $motivo_stmt = $this->query("
                SELECT 
                    s.motivo,
                    COUNT(*) as count
                FROM sostituzioni s
                JOIN calendario_lezioni cl ON s.lezione_id = cl.id
                WHERE cl.data_lezione BETWEEN ? AND ?
                GROUP BY s.motivo
            ", [$data_inizio, $data_fine]);
            $stats_motivo = $motivo_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepara array per motivi
            $per_motivo = [];
            foreach ($stats_motivo as $stat) {
                $per_motivo[$stat['motivo']] = $stat['count'];
            }
            
            // Assicurati che tutti i motivi abbiano un valore
            $motivi_possibili = ['malattia', 'permesso', 'formazione', 'altro'];
            foreach ($motivi_possibili as $motivo) {
                if (!isset($per_motivo[$motivo])) {
                    $per_motivo[$motivo] = 0;
                }
            }
            
            return [
                'totali' => $stats_totali['totali'] ?? 0,
                'confermate' => $stats_totali['confermate'] ?? 0,
                'in_attesa' => $stats_totali['in_attesa'] ?? 0,
                'per_motivo' => $per_motivo
            ];
            
        } catch (Exception $e) {
            error_log("Errore calcolaStatisticheSostituzioni: " . $e->getMessage());
            return [
                'totali' => 0,
                'confermate' => 0,
                'in_attesa' => 0,
                'per_motivo' => [
                    'malattia' => 0,
                    'permesso' => 0,
                    'formazione' => 0,
                    'altro' => 0
                ]
            ];
        }
    }
    
    /**
     * Invia notifica in-app e opzionalmente via email al docente sostituto
     * per la sostituzione appena creata.
     *
     * @param int $sostituzione_id
     * @return void
     */
    private function inviaNotificaSostituzione($sostituzione_id) {
        $sostituzione = $this->query("
            SELECT s.*, cl.data_lezione, os.ora_inizio, os.ora_fine,
                   c.nome as classe_nome, m.nome as materia_nome,
                   CONCAT(do.cognome, ' ', do.nome) as docente_originale,
                   CONCAT(ds.cognome, ' ', ds.nome) as docente_sostituto,
                   ds.email as email_sostituto
            FROM sostituzioni s
            JOIN calendario_lezioni cl ON s.lezione_id = cl.id
            JOIN classi c ON cl.classe_id = c.id
            JOIN materie m ON cl.materia_id = m.id
            JOIN orari_slot os ON cl.slot_id = os.id
            JOIN docenti do ON s.docente_originale_id = do.id
            JOIN docenti ds ON s.docente_sostituto_id = ds.id
            WHERE s.id = ?
        ", [$sostituzione_id])->fetch(PDO::FETCH_ASSOC);
        
        if ($sostituzione && !empty($sostituzione['email_sostituto'])) {
            // Crea notifica in-app per il sostituto
            $this->query("
                INSERT INTO notifiche (utente_id, tipo, priorita, titolo, messaggio, riferimento_tabella, riferimento_id)
                SELECT u.id, 'avviso', 'alta', 'Sostituzione Assegnata',
                       CONCAT('Sei stato assegnato come sostituto per ', ?, ' - ', ?, ' il ', ?),
                       'sostituzioni', ?
                FROM utenti u 
                WHERE u.docente_id = ?
            ", [
                $sostituzione['classe_nome'],
                $sostituzione['materia_nome'],
                date('d/m/Y', strtotime($sostituzione['data_lezione'])),
                $sostituzione_id,
                $sostituzione['docente_sostituto_id']
            ]);
            
            // Opzionale: invio email al sostituto
            // $this->inviaEmailSostituzione($sostituzione);
        }
    }
    
    /**
     * Registra una voce di log per le attività legate alle sostituzioni
     *
     * @param string $azione Identificativo azione
     * @param string $dettagli Testo descrittivo dell'azione
     * @return void
     */
    private function logAttivita($azione, $dettagli) {
        $user = $_SESSION['username'] ?? 'sistema';
    $this->query("INSERT INTO log_attivita (tipo, azione, descrizione, utente, ip_address) VALUES ('sostituzione', ?, ?, ?, ?)", [$azione, $dettagli, $user, $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}