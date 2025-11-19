<?php
require_once '../config/database.php';

class StageNotifier {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::getPDOConnection();
    }
    
    /**
     * Invia notifica per l'inizio imminente di uno stage
     */
    public function notificaInizioStage($stage_id, $giorni_anticipo = 3) {
        $stage = $this->getStageConDettagli($stage_id);
        if (!$stage) {
            return false;
        }
        
        $data_notifica = date('Y-m-d', strtotime("-$giorni_anticipo days", strtotime($stage['data_inizio'])));
        
        // Notifica al tutor scolastico
        if ($stage['tutor_scolastico_id']) {
            $this->creaNotifica(
                $stage['tutor_scolastico_id'],
                'info',
                'media',
                'Stage in programma',
                "Lo stage per la classe {$stage['classe_nome']} inizierà il " . 
                date('d/m/Y', strtotime($stage['data_inizio'])) . 
                " presso {$stage['azienda']}",
                'stage_periodi',
                $stage_id
            );
        }
        
        // Notifica a tutti i docenti della classe
        $docenti_classe = $this->getDocentiClasse($stage['classe_id']);
        foreach ($docenti_classe as $docente) {
            $this->creaNotifica(
                $docente['docente_id'],
                'info',
                'media',
                'Stage classe in programma',
                "La classe {$stage['classe_nome']} sarà in stage dal " . 
                date('d/m/Y', strtotime($stage['data_inizio'])) . " al " . 
                date('d/m/Y', strtotime($stage['data_fine'])) . 
                ". Verificare le lezioni in conflitto.",
                'stage_periodi',
                $stage_id
            );
        }
        
        $this->logAttivita(
            'stage_notifiche',
            'notifica_inizio',
            "Inviate notifiche per inizio stage ID $stage_id",
            'stage_periodi',
            $stage_id
        );
        
        return true;
    }
    
    /**
     * Invia notifica per la fine di uno stage
     */
    public function notificaFineStage($stage_id) {
        $stage = $this->getStageConDettagli($stage_id);
        if (!$stage) {
            return false;
        }
        
        // Notifica al tutor scolastico
        if ($stage['tutor_scolastico_id']) {
            $percentuale = $stage['ore_totali_previste'] > 0 ? 
                round(($stage['ore_effettuate'] / $stage['ore_totali_previste']) * 100, 1) : 0;
            
            $this->creaNotifica(
                $stage['tutor_scolastico_id'],
                'info',
                'media',
                'Stage completato',
                "Lo stage per la classe {$stage['classe_nome']} si è concluso. " .
                "Completamento: {$percentuale}% ({$stage['ore_effettuate']}/{$stage['ore_totali_previste']} ore)",
                'stage_periodi',
                $stage_id
            );
        }
        
        // Notifica per documenti mancanti
        $documenti_mancanti = $this->verificaDocumentiMancanti($stage_id);
        if (!empty($documenti_mancanti)) {
            $this->notificaDocumentiMancanti($stage_id, $documenti_mancanti);
        }
        
        $this->logAttivita(
            'stage_notifiche',
            'notifica_fine',
            "Inviate notifiche per fine stage ID $stage_id",
            'stage_periodi',
            $stage_id
        );
        
        return true;
    }
    
    /**
     * Invia notifica per ore rimanenti
     */
    public function notificaOreRimanenti($stage_id, $soglia_percentuale = 20) {
        $stage = $this->getStage($stage_id);
        if (!$stage || $stage['stato'] != 'in_corso') {
            return false;
        }
        
        $percentuale_rimanente = (($stage['ore_totali_previste'] - $stage['ore_effettuate']) / $stage['ore_totali_previste']) * 100;
        
        if ($percentuale_rimanente <= $soglia_percentuale) {
            $tutor = $this->getTutorStage($stage_id);
            if ($tutor && $tutor['docente_id']) {
                $this->creaNotifica(
                    $tutor['docente_id'],
                    'avviso',
                    'media',
                    'Ore stage in esaurimento',
                    "Mancano " . round($percentuale_rimanente, 1) . "% delle ore previste per lo stage. " .
                    "Ore effettuate: {$stage['ore_effettuate']}/{$stage['ore_totali_previste']}",
                    'stage_periodi',
                    $stage_id
                );
                
                $this->logAttivita(
                    'stage_notifiche',
                    'notifica_ore_rimanenti',
                    "Notifica ore rimanenti per stage ID $stage_id",
                    'stage_periodi',
                    $stage_id
                );
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Invia reminder per registrazione ore
     */
    public function reminderRegistrazioneOre($stage_id) {
        $stage = $this->getStage($stage_id);
        if (!$stage || $stage['stato'] != 'in_corso') {
            return false;
        }
        
        // Verifica ultima registrazione
        $ultima_registrazione = $this->pdo->prepare("
            SELECT MAX(data) as ultima_data 
            FROM stage_giorni 
            WHERE stage_periodo_id = ? AND presenza = 1
        ")->execute([$stage_id])->fetch(PDO::FETCH_ASSOC);
        
        $giorni_senza_registrazione = 0;
        if ($ultima_registrazione['ultima_data']) {
            $giorni_senza_registrazione = (strtotime(date('Y-m-d')) - strtotime($ultima_registrazione['ultima_data'])) / (60 * 60 * 24);
        }
        
        // Se sono passati più di 3 giorni lavorativi senza registrazioni
        if ($giorni_senza_registrazione >= 3) {
            $tutor = $this->getTutorStage($stage_id);
            if ($tutor && $tutor['docente_id']) {
                $this->creaNotifica(
                    $tutor['docente_id'],
                    'avviso',
                    'bassa',
                    'Reminder registrazione ore',
                    "Non vengono registrate ore per lo stage da $giorni_senza_registrazione giorni. " .
                    "Ricordati di aggiornare il registro presenze.",
                    'stage_periodi',
                    $stage_id
                );
                
                $this->logAttivita(
                    'stage_notifiche',
                    'reminder_registrazione',
                    "Reminder registrazione ore per stage ID $stage_id",
                    'stage_periodi',
                    $stage_id
                );
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Alert per documenti mancanti
     */
    public function alertDocumentiMancanti($stage_id, $giorni_scadenza = 7) {
        $stage = $this->getStage($stage_id);
        if (!$stage) {
            return false;
        }
        
        $documenti_mancanti = $this->verificaDocumentiMancanti($stage_id);
        if (empty($documenti_mancanti)) {
            return false;
        }
        
        $giorni_alla_scadenza = (strtotime($stage['data_fine']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
        
        if ($giorni_alla_scadenza <= $giorni_scadenza) {
            $this->notificaDocumentiMancanti($stage_id, $documenti_mancanti);
            return true;
        }
        
        return false;
    }
    
    /**
     * Notifica per stage in ritardo
     */
    public function notificaStageInRitardo($stage_id) {
        $stage = $this->getStage($stage_id);
        if (!$stage || $stage['stato'] != 'in_corso') {
            return false;
        }
        
        // Considera uno stage in ritardo se ha completato meno del 50% delle ore 
        // ed è a metà del periodo
        $percentuale_ore = ($stage['ore_effettuate'] / $stage['ore_totali_previste']) * 100;
        $percentuale_tempo = $this->calcolaPercentualeTempo($stage['data_inizio'], $stage['data_fine']);
        
        if ($percentuale_tempo >= 50 && $percentuale_ore < 50) {
            $tutor = $this->getTutorStage($stage_id);
            if ($tutor && $tutor['docente_id']) {
                $this->creaNotifica(
                    $tutor['docente_id'],
                    'allerta',
                    'alta',
                    'Stage in ritardo',
                    "Lo stage mostra un ritardo significativo: completate solo il " . 
                    round($percentuale_ore, 1) . "% delle ore previste a metà del periodo.",
                    'stage_periodi',
                    $stage_id
                );
                
                // Notifica anche al coordinatore
                $this->notificaCoordinatoreStageInRitardo($stage_id, $percentuale_ore, $percentuale_tempo);
                
                $this->logAttivita(
                    'stage_notifiche',
                    'notifica_ritardo',
                    "Notifica stage in ritardo ID $stage_id",
                    'stage_periodi',
                    $stage_id
                );
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Metodi privati di supporto
     */
    private function getStage($stage_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM stage_periodi WHERE id = ?");
        $stmt->execute([$stage_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getStageConDettagli($stage_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                sp.*,
                c.nome as classe_nome,
                st.docente_id as tutor_scolastico_id,
                st.azienda
            FROM stage_periodi sp
            JOIN classi c ON sp.classe_id = c.id
            LEFT JOIN stage_tutor st ON sp.id = st.stage_periodo_id
            WHERE sp.id = ?
        ");
        $stmt->execute([$stage_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getTutorStage($stage_id) {
        $stmt = $this->pdo->prepare("
            SELECT st.*, d.cognome, d.nome 
            FROM stage_tutor st
            LEFT JOIN docenti d ON st.docente_id = d.id
            WHERE st.stage_periodo_id = ?
        ");
        $stmt->execute([$stage_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getDocentiClasse($classe_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT cmd.docente_id
            FROM classi_materie_docenti cmd
            WHERE cmd.classe_id = ? AND cmd.attivo = 1
        ");
        $stmt->execute([$classe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function verificaDocumentiMancanti($stage_id) {
        $documenti_richiesti = ['convenzione', 'registro_presenze', 'valutazione_finale'];
        $documenti_presenti = [];
        
        $stmt = $this->pdo->prepare("
            SELECT tipo_documento 
            FROM stage_documenti 
            WHERE stage_periodo_id = ?
        ");
        $stmt->execute([$stage_id]);
        $documenti_caricati = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return array_diff($documenti_richiesti, $documenti_caricati);
    }
    
    private function notificaDocumentiMancanti($stage_id, $documenti_mancanti) {
        $stage = $this->getStageConDettagli($stage_id);
        $tutor = $this->getTutorStage($stage_id);
        
        if ($tutor && $tutor['docente_id']) {
            $documenti_lista = implode(', ', $documenti_mancanti);
            
            $this->creaNotifica(
                $tutor['docente_id'],
                'allerta',
                'alta',
                'Documenti stage mancanti',
                "Mancano i seguenti documenti per lo stage della classe {$stage['classe_nome']}: $documenti_lista",
                'stage_periodi',
                $stage_id
            );
        }
    }
    
    private function notificaCoordinatoreStageInRitardo($stage_id, $percentuale_ore, $percentuale_tempo) {
        // Trova utenti con ruolo di coordinatore o preside
        $stmt = $this->pdo->prepare("
            SELECT u.id 
            FROM utenti u 
            WHERE u.ruolo IN ('preside', 'vice_preside', 'amministratore') 
            AND u.attivo = 1
        ");
        $stmt->execute();
        $coordinatori = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stage = $this->getStageConDettagli($stage_id);
        
        foreach ($coordinatori as $coordinatore_id) {
            $this->creaNotifica(
                $coordinatore_id,
                'allerta',
                'media',
                'Stage in ritardo - Richiede attenzione',
                "Lo stage della classe {$stage['classe_nome']} mostra un ritardo: " .
                round($percentuale_ore, 1) . "% ore completate a " .
                round($percentuale_tempo, 1) . "% del periodo.",
                'stage_periodi',
                $stage_id
            );
        }
    }
    
    private function calcolaPercentualeTempo($data_inizio, $data_fine) {
        $oggi = time();
        $inizio = strtotime($data_inizio);
        $fine = strtotime($data_fine);
        
        if ($oggi <= $inizio) return 0;
        if ($oggi >= $fine) return 100;
        
        $totale = $fine - $inizio;
        $trascorso = $oggi - $inizio;
        
        return ($trascorso / $totale) * 100;
    }
    
    private function creaNotifica($docente_id, $tipo, $priorita, $titolo, $messaggio, $riferimento_tabella, $riferimento_id) {
        // Trova l'utente associato al docente
        $stmt = $this->pdo->prepare("
            SELECT id FROM utenti WHERE docente_id = ? LIMIT 1
        ");
        $stmt->execute([$docente_id]);
        $utente_id = $stmt->fetchColumn();
        
        if ($utente_id) {
            $this->pdo->prepare("
                INSERT INTO notifiche 
                (utente_id, tipo, priorita, titolo, messaggio, riferimento_tabella, riferimento_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$utente_id, $tipo, $priorita, $titolo, $messaggio, $riferimento_tabella, $riferimento_id]);
        }
    }
    
    private function logAttivita($tipo, $azione, $descrizione, $tabella, $record_id) {
        $this->pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address)
            VALUES (?, ?, ?, ?, ?, 'sistema', '127.0.0.1')
        ")->execute([$tipo, $azione, $descrizione, $tabella, $record_id]);
    }
}
?>