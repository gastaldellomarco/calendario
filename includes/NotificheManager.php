<?php
/**
 * Gestore notifiche automatiche
 */
/**
 * Gestore notifiche automatiche
 *
 * Raccoglie metodi per creare notifiche, inviare avvisi in-app e controllare eventi
 * rilevanti (conflitti, sostituzioni, scadenze stage, superamenti ore).
 */
class NotificheManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Crea una notifica in-app
     *
     * @param int $utente_id ID dell'utente ricevente
     * @param string $tipo Tipo di notifica (es: info, avviso, allerta)
     * @param string $titolo Titolo della notifica
     * @param string $messaggio Testo della notifica
     * @param string $priorita Priorità (bassa, media, alta, urgente)
     * @param string|null $riferimento_tabella Tabella referenziata (opzionale)
     * @param int|null $riferimento_id ID referente (opzionale)
     * @param string|null $azione_url URL di riferimento per l'azione (opzionale)
     * @return bool True se la notifica è stata creata correttamente
     */
    public function creaNotifica($utente_id, $tipo, $titolo, $messaggio, $priorita = 'media', $riferimento_tabella = null, $riferimento_id = null, $azione_url = null) {
        $sql = "INSERT INTO notifiche (utente_id, tipo, priorita, titolo, messaggio, riferimento_tabella, riferimento_id, azione_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$utente_id, $tipo, $priorita, $titolo, $messaggio, $riferimento_tabella, $riferimento_id, $azione_url]);
    }
    
    /**
     * Notifica gli amministratori in caso di conflitto orario
     *
     * @param int $conflitto_id ID del conflitto rilevato
     * @return bool True se le notifiche sono state inviate correttamente
     */
    public function notificaConflittoOrario($conflitto_id) {
        // Ottieni dettagli conflitto
        $sql = "SELECT c.*, d.nome as docente_nome, d.cognome as docente_cognome, 
                       cl.nome as classe_nome, a.nome as aula_nome
                FROM conflitti_orario c
                LEFT JOIN docenti d ON c.docente_id = d.id
                LEFT JOIN classi cl ON c.classe_id = cl.id
                LEFT JOIN aule a ON c.aula_id = a.id
                WHERE c.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conflitto_id]);
        $conflitto = $stmt->fetch();
        
        if (!$conflitto) return false;
        
        $titolo = "Conflitto orario rilevato";
        $messaggio = "Tipo: {$conflitto['tipo']}. {$conflitto['descrizione']}";
        
        // Invia a preside e amministratori
        $admin_users = $this->getUtentiByRuolo(['amministratore', 'preside']);
        
        foreach ($admin_users as $user) {
            $this->creaNotifica(
                $user['id'],
                'conflitto',
                $titolo,
                $messaggio,
                $conflitto['gravita'] === 'critico' ? 'urgente' : 'alta',
                'conflitti_orario',
                $conflitto_id,
                "/pages/conflitti.php?highlight={$conflitto_id}"
            );
        }
        
        return true;
    }
    
    /**
     * Notifica il docente sostituto e altri utenti rilevanti di una sostituzione
     *
     * @param int $sostituzione_id ID della sostituzione
     * @return bool True se la notifica è stata inviata correttamente
     */
    public function notificaSostituzione($sostituzione_id) {
        $sql = "SELECT s.*, 
                       do.nome as docente_originale_nome, do.cognome as docente_originale_cognome,
                       ds.nome as docente_sostituto_nome, ds.cognome as docente_sostituto_cognome,
                       cl.nome as classe_nome, m.nome as materia_nome
                FROM sostituzioni s
                JOIN docenti do ON s.docente_originale_id = do.id
                JOIN docenti ds ON s.docente_sostituto_id = ds.id
                JOIN calendario_lezioni cl ON s.lezione_id = cl.id
                JOIN materie m ON cl.materia_id = m.id
                WHERE s.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$sostituzione_id]);
        $sostituzione = $stmt->fetch();
        
        if (!$sostituzione) return false;
        
        // Notifica al docente sostituto
        $titolo = "Sostituzione assegnata";
        $messaggio = "Sei stato assegnato come sostituto per {$sostituzione['materia_nome']} in {$sostituzione['classe_nome']}";
        
        $this->creaNotifica(
            $sostituzione['docente_sostituto_id'],
            'avviso',
            $titolo,
            $messaggio,
            'media',
            'sostituzioni',
            $sostituzione_id,
            "/pages/calendario.php"
        );
        
        return true;
    }
    
    /**
     * Notifica il docente e altri utenti quando una lezione è stata modificata
     *
     * @param int $lezione_id ID della lezione modificata
     * @param string $utente_modificatore Username dell'utente che ha modificato
     * @return bool True se la notifica è stata inviata correttamente
     */
    public function notificaModificaCalendario($lezione_id, $utente_modificatore) {
        $sql = "SELECT cl.*, d.id as docente_id, d.nome as docente_nome, d.cognome as docente_cognome,
                       c.nome as classe_nome, m.nome as materia_nome
                FROM calendario_lezioni cl
                JOIN docenti d ON cl.docente_id = d.id
                JOIN classi c ON cl.classe_id = c.id
                JOIN materie m ON cl.materia_id = m.id
                WHERE cl.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lezione_id]);
        $lezione = $stmt->fetch();
        
        if (!$lezione) return false;
        
        $titolo = "Modifica lezione";
        $messaggio = "La lezione di {$lezione['materia_nome']} in {$lezione['classe_nome']} del " . 
                     date('d/m/Y', strtotime($lezione['data_lezione'])) . " è stata modificata";
        
        // Notifica al docente
        $this->creaNotifica(
            $lezione['docente_id'],
            'info',
            $titolo,
            $messaggio,
            'media',
            'calendario_lezioni',
            $lezione_id,
            "/pages/calendario.php?data={$lezione['data_lezione']}"
        );
        
        return true;
    }
    
    /**
     * Invia notifiche quando lo stage di un periodo sta per scadere
     *
     * @param int $stage_id ID del periodo stage
     * @return bool True se la notifica è stata inviata o se non necessario
     */
    public function notificaScadenzaStage($stage_id) {
        $sql = "SELECT s.*, c.nome as classe_nome, d.id as tutor_id
                FROM stage_periodi s
                JOIN classi c ON s.classe_id = c.id
                LEFT JOIN stage_tutor st ON s.id = st.stage_periodo_id
                LEFT JOIN docenti d ON st.docente_id = d.id
                WHERE s.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$stage_id]);
        $stage = $stmt->fetch();
        
        if (!$stage) return false;
        
        $giorni_rimanenti = floor((strtotime($stage['data_fine']) - time()) / (60 * 60 * 24));
        
        if ($giorni_rimanenti <= 7) {
            $titolo = "Stage in scadenza";
            $messaggio = "Lo stage della classe {$stage['classe_nome']} scade tra {$giorni_rimanenti} giorni";
            $priorita = $giorni_rimanenti <= 3 ? 'urgente' : 'alta';
            
            // Notifica al tutor
            if ($stage['tutor_id']) {
                $this->creaNotifica(
                    $stage['tutor_id'],
                    'allerta',
                    $titolo,
                    $messaggio,
                    $priorita,
                    'stage_periodi',
                    $stage_id,
                    "/pages/stage.php"
                );
            }
            
            // Notifica a preside
            $preside_users = $this->getUtentiByRuolo(['preside']);
            foreach ($preside_users as $user) {
                $this->creaNotifica(
                    $user['id'],
                    'allerta',
                    $titolo,
                    $messaggio,
                    $priorita,
                    'stage_periodi',
                    $stage_id,
                    "/pages/stage.php"
                );
            }
        }
        
        return true;
    }
    
    /**
     * Notifica gli amministratori quando un docente supera le ore contrattuali
     *
     * @param int $docente_id ID del docente
     * @return bool True se la notifica è stata inviata, false altrimenti
     */
    public function notificaSuperamentoOre($docente_id) {
        $sql = "SELECT d.nome, d.cognome, 
                       SUM(cmd.ore_settimanali) as ore_totali,
                       d.max_ore_settimana
                FROM docenti d
                JOIN classi_materie_docenti cmd ON d.id = cmd.docente_id
                WHERE d.id = ? AND cmd.attivo = 1
                GROUP BY d.id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$docente_id]);
        $docente = $stmt->fetch();
        
        if (!$docente || $docente['ore_totali'] <= $docente['max_ore_settimana']) {
            return false;
        }
        
        $titolo = "Superamento ore contrattuali";
        $messaggio = "Il docente {$docente['cognome']} {$docente['nome']} ha {$docente['ore_totali']} ore assegnate, superando il limite di {$docente['max_ore_settimana']} ore";
        
        // Notifica a preside e amministratori
        $admin_users = $this->getUtentiByRuolo(['amministratore', 'preside']);
        
        foreach ($admin_users as $user) {
            $this->creaNotifica(
                $user['id'],
                'allerta',
                $titolo,
                $messaggio,
                'alta',
                'docenti',
                $docente_id,
                "/pages/docente_edit.php?id={$docente_id}"
            );
        }
        
        return true;
    }
    
    /**
     * Esegue gli invii programmati delle notifiche giornaliere
     * (viene eseguito dal cron job delle notifiche giornaliere)
     *
     * @return bool True se completato con successo
     */
    public function inviaNotificheGiornaliere() {
        $today = date('Y-m-d');
        
        // 1. Notifica lezioni del giorno ai docenti
        $this->notificheLezioniGiornaliere($today);
        
        // 2. Controlla stage in scadenza
        $this->controllaStageScadenza();
        
        // 3. Controlla conflitti non risolti
        $this->controllaConflittiAperti();
        
        return true;
    }
    
    /**
     * Invio notifiche riepilogo lezioni giornaliere per ciascun docente
     *
     * @param string $data Data corrente nel formato Y-m-d
     */
    private function notificheLezioniGiornaliere($data) {
        $sql = "SELECT cl.*, d.id as docente_id, d.nome as docente_nome, d.cognome as docente_cognome,
                       c.nome as classe_nome, m.nome as materia_nome, a.nome as aula_nome,
                       os.ora_inizio, os.ora_fine
                FROM calendario_lezioni cl
                JOIN docenti d ON cl.docente_id = d.id
                JOIN classi c ON cl.classe_id = c.id
                JOIN materie m ON cl.materia_id = m.id
                LEFT JOIN aule a ON cl.aula_id = a.id
                JOIN orari_slot os ON cl.slot_id = os.id
                WHERE cl.data_lezione = ? AND cl.stato != 'cancellata'
                ORDER BY d.id, os.ora_inizio";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$data]);
        $lezioni = $stmt->fetchAll();
        
        $lezioni_per_docente = [];
        foreach ($lezioni as $lezione) {
            $lezioni_per_docente[$lezione['docente_id']][] = $lezione;
        }
        
        foreach ($lezioni_per_docente as $docente_id => $lezioni_docente) {
            $messaggio = "Lezioni per oggi " . date('d/m/Y') . ":\n\n";
            
            foreach ($lezioni_docente as $lezione) {
                $ora_inizio = date('H:i', strtotime($lezione['ora_inizio']));
                $messaggio .= "• {$ora_inizio} - {$lezione['materia_nome']} - {$lezione['classe_nome']}";
                if ($lezione['aula_nome']) {
                    $messaggio .= " (Aula: {$lezione['aula_nome']})";
                }
                $messaggio .= "\n";
            }
            
            $this->creaNotifica(
                $docente_id,
                'info',
                "Riepilogo lezioni giornaliero",
                $messaggio,
                'bassa',
                null,
                null,
                "/pages/calendario.php?data={$data}"
            );
        }
    }
    
    /**
     * Controlla stage in scadenza e invia notifiche di promemoria
     */
    private function controllaStageScadenza() {
        $sql = "SELECT id FROM stage_periodi 
                WHERE data_fine BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND stato = 'in_corso'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stages = $stmt->fetchAll();
        
        foreach ($stages as $stage) {
            $this->notificaScadenzaStage($stage['id']);
        }
    }
    
    /**
     * Controlla conflitti aperti e invia notifiche ai responsabili
     */
    private function controllaConflittiAperti() {
        $sql = "SELECT COUNT(*) as total FROM conflitti_orario WHERE risolto = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $total = $stmt->fetchColumn();
        
        if ($total > 0) {
            $admin_users = $this->getUtentiByRuolo(['amministratore', 'preside']);
            $messaggio = "Ci sono {$total} conflitti aperti che richiedono attenzione";
            
            foreach ($admin_users as $user) {
                $this->creaNotifica(
                    $user['id'],
                    'avviso',
                    "Conflitti aperti",
                    $messaggio,
                    'media',
                    null,
                    null,
                    "/pages/conflitti.php"
                );
            }
        }
    }
    
    /**
     * Recupera gli utenti attivi che hanno uno dei ruoli richiesti
     *
     * @param array $ruoli Array di ruoli da filtrare
     * @return array Array di utenti (id, ...)
     */
    private function getUtentiByRuolo($ruoli) {
        $placeholders = str_repeat('?,', count($ruoli) - 1) . '?';
        $sql = "SELECT id FROM utenti WHERE ruolo IN ($placeholders) AND attivo = 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ruoli);
        return $stmt->fetchAll();
    }
}