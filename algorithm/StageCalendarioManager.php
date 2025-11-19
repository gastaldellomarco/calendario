<?php
require_once '../config/database.php';

class StageCalendarioManager {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::getPDOConnection();
    }
    
    /**
     * Blocca il calendario per uno stage, cancellando le lezioni in conflitto
     */
    public function bloccaCalendarioPerStage($stage_id) {
        $stage = $this->getStage($stage_id);
        if (!$stage) {
            throw new Exception("Stage non trovato");
        }
        
        $lezioni_conflitto = $this->getLezioniInConflitto($stage['classe_id'], $stage['data_inizio'], $stage['data_fine']);
        
        $this->pdo->beginTransaction();
        
        try {
            $lezioni_cancellate = 0;
            $notifiche_inviate = [];
            
            foreach ($lezioni_conflitto as $lezione) {
                // Marca lezione come cancellata
                $this->pdo->prepare("
                    UPDATE calendario_lezioni 
                    SET stato = 'cancellata', 
                        cancellata_motivo = 'stage',
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$lezione['id']]);
                
                $lezioni_cancellate++;
                
                // Crea notifica per il docente
                $this->creaNotificaDocente($lezione['docente_id'], $stage, $lezione);
                $notifiche_inviate[] = $lezione['docente_id'];
            }
            
            // Log dell'operazione
            $this->logAttivita(
                'stage_calendario',
                'blocco_automatico',
                "Bloccato calendario per stage ID $stage_id: cancellate $lezioni_cancellate lezioni",
                'stage_periodi',
                $stage_id
            );
            
            $this->pdo->commit();
            
            return [
                'lezioni_cancellate' => $lezioni_cancellate,
                'notifiche_inviate' => count(array_unique($notifiche_inviate))
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Gestisce i conflitti del calendario in base alle opzioni specificate
     */
    public function gestisciConflittiCalendario($stage_id, $azione, $tipo, $parametri = [], $opzioni = []) {
        $stage = $this->getStage($stage_id);
        if (!$stage) {
            throw new Exception("Stage non trovato");
        }
        
        $this->pdo->beginTransaction();
        
        try {
            $result = [];
            
            switch ($azione) {
                case 'cancella':
                    $result = $this->cancellaLezioni($stage, $tipo, $parametri);
                    break;
                    
                case 'sposta':
                    $result = $this->spostaLezioni($stage, $tipo, $parametri, $opzioni);
                    break;
                    
                default:
                    throw new Exception("Azione non supportata: $azione");
            }
            
            $this->pdo->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Cancella lezioni in base al tipo specificato
     */
    private function cancellaLezioni($stage, $tipo, $parametri) {
        $lezioni_ids = [];
        
        switch ($tipo) {
            case 'tutte':
                $lezioni = $this->getLezioniInConflitto($stage['classe_id'], $stage['data_inizio'], $stage['data_fine']);
                $lezioni_ids = array_column($lezioni, 'id');
                break;
                
            case 'giorno':
                $data = $parametri;
                $lezioni = $this->getLezioniInConflitto($stage['classe_id'], $data, $data);
                $lezioni_ids = array_column($lezioni, 'id');
                break;
                
            case 'selezionate':
                $lezioni_ids = $parametri;
                break;
                
            default:
                throw new Exception("Tipo cancellazione non supportato: $tipo");
        }
        
        $cancellate = 0;
        $notifiche = [];
        
        foreach ($lezioni_ids as $lezione_id) {
            $lezione = $this->getLezione($lezione_id);
            if ($lezione && $lezione['stato'] != 'cancellata') {
                $this->pdo->prepare("
                    UPDATE calendario_lezioni 
                    SET stato = 'cancellata', 
                        cancellata_motivo = 'stage',
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$lezione_id]);
                
                $cancellate++;
                
                // Notifica docente
                $this->creaNotificaDocente($lezione['docente_id'], $stage, $lezione);
                $notifiche[] = $lezione['docente_id'];
            }
        }
        
        $this->logAttivita(
            'stage_calendario',
            'cancellazione_lezioni',
            "Cancellate $cancellate lezioni per stage ID {$stage['id']} (tipo: $tipo)",
            'stage_periodi',
            $stage['id']
        );
        
        return [
            'lezioni_cancellate' => $cancellate,
            'docenti_notificati' => count(array_unique($notifiche))
        ];
    }
    
    /**
     * Sposta lezioni in base al tipo specificato
     */
    private function spostaLezioni($stage, $tipo, $parametri, $opzioni) {
        $nuova_data = $opzioni['data'] ?? null;
        $nuovo_slot = $opzioni['slot'] ?? null;
        
        if (!$nuova_data) {
            throw new Exception("Data di destinazione non specificata");
        }
        
        // Verifica che la nuova data non sia nel periodo di stage
        if ($nuova_data >= $stage['data_inizio'] && $nuova_data <= $stage['data_fine']) {
            throw new Exception("La data di destinazione non può essere nel periodo di stage");
        }
        
        $lezioni_ids = [];
        
        switch ($tipo) {
            case 'tutte':
                $lezioni = $this->getLezioniInConflitto($stage['classe_id'], $stage['data_inizio'], $stage['data_fine']);
                $lezioni_ids = array_column($lezioni, 'id');
                break;
                
            case 'selezionate':
                $lezioni_ids = $parametri;
                break;
                
            default:
                throw new Exception("Tipo spostamento non supportato: $tipo");
        }
        
        $spostate = 0;
        $conflitti = 0;
        
        foreach ($lezioni_ids as $lezione_id) {
            $lezione = $this->getLezione($lezione_id);
            if ($lezione && $lezione['stato'] != 'cancellata') {
                
                // Verifica che non ci siano conflitti nella nuova data
                $conflitto = $this->verificaConflittoSpostamento($lezione, $nuova_data, $nuovo_slot);
                
                if (!$conflitto) {
                    // Sposta la lezione
                    $this->pdo->prepare("
                        UPDATE calendario_lezioni 
                        SET data_lezione = ?,
                            slot_id = COALESCE(?, slot_id),
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$nuova_data, $nuovo_slot, $lezione_id]);
                    
                    $spostate++;
                    
                    // Notifica docente
                    $this->creaNotificaSpostamento($lezione['docente_id'], $stage, $lezione, $nuova_data);
                    
                } else {
                    $conflitti++;
                }
            }
        }
        
        $this->logAttivita(
            'stage_calendario',
            'spostamento_lezioni',
            "Spostate $spostate lezioni per stage ID {$stage['id']} (conflitti: $conflitti)",
            'stage_periodi',
            $stage['id']
        );
        
        return [
            'lezioni_spostate' => $spostate,
            'conflitti_rilevati' => $conflitti
        ];
    }
    
    /**
     * Ricalcola le ore rimanenti dopo uno stage
     */
    public function ricalcolaOreDopoStage($stage_id) {
        $stage = $this->getStage($stage_id);
        if (!$stage) {
            throw new Exception("Stage non trovato");
        }
        
        // Recupera tutte le assegnazioni della classe
        $assegnazioni = $this->pdo->prepare("
            SELECT cmd.*, m.nome as materia_nome
            FROM classi_materie_docenti cmd
            JOIN materie m ON cmd.materia_id = m.id
            WHERE cmd.classe_id = ? AND cmd.attivo = 1
        ")->execute([$stage['classe_id']])->fetchAll(PDO::FETCH_ASSOC);
        
        $suggerimenti = [];
        
        foreach ($assegnazioni as $assegnazione) {
            $ore_effettuate_pre_stage = $this->calcolaOreEffettuatePreStage($stage['classe_id'], $assegnazione['materia_id'], $stage['data_inizio']);
            $ore_rimanenti = $assegnazione['ore_annuali_previste'] - $ore_effettuate_pre_stage;
            
            if ($ore_rimanenti < 0) {
                $suggerimenti[] = [
                    'materia' => $assegnazione['materia_nome'],
                    'deficit' => abs($ore_rimanenti),
                    'suggerimento' => "Piano di recupero necessario: " . abs($ore_rimanenti) . " ore"
                ];
            }
        }
        
        return $suggerimenti;
    }
    
    /**
     * Recupera le lezioni in conflitto con un periodo di stage
     */
    public function getLezioniInConflitto($classe_id, $data_inizio, $data_fine) {
        return $this->pdo->prepare("
            SELECT 
                cl.*,
                m.nome as materia_nome,
                CONCAT(d.cognome, ' ', d.nome) as docente_nome,
                a.nome as aula_nome,
                os.ora_inizio, os.ora_fine
            FROM calendario_lezioni cl
            JOIN materie m ON cl.materia_id = m.id
            JOIN docenti d ON cl.docente_id = d.id
            LEFT JOIN aule a ON cl.aula_id = a.id
            JOIN orari_slot os ON cl.slot_id = os.id
            WHERE cl.classe_id = ?
            AND cl.data_lezione BETWEEN ? AND ?
            AND cl.stato != 'cancellata'
            ORDER BY cl.data_lezione, os.ora_inizio
        ")->execute([$classe_id, $data_inizio, $data_fine])->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Metodi privati di supporto
     */
    private function getStage($stage_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM stage_periodi WHERE id = ?");
        $stmt->execute([$stage_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getLezione($lezione_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM calendario_lezioni WHERE id = ?");
        $stmt->execute([$lezione_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function creaNotificaDocente($docente_id, $stage, $lezione) {
        $messaggio = "La lezione di {$lezione['materia_nome']} del " . 
                    date('d/m/Y', strtotime($lezione['data_lezione'])) . 
                    " è stata cancellata per stage della classe (periodo: " .
                    date('d/m/Y', strtotime($stage['data_inizio'])) . " - " .
                    date('d/m/Y', strtotime($stage['data_fine'])) . ")";
        
        $this->pdo->prepare("
            INSERT INTO notifiche 
            (utente_id, tipo, priorita, titolo, messaggio, riferimento_tabella, riferimento_id)
            VALUES (
                (SELECT id FROM utenti WHERE docente_id = ? LIMIT 1),
                'avviso', 'media', 'Lezione cancellata per stage', ?, 'calendario_lezioni', ?
            )
        ")->execute([$docente_id, $messaggio, $lezione['id']]);
    }
    
    private function creaNotificaSpostamento($docente_id, $stage, $lezione, $nuova_data) {
        $messaggio = "La lezione di {$lezione['materia_nome']} è stata spostata al " . 
                    date('d/m/Y', strtotime($nuova_data)) . 
                    " per stage della classe (periodo: " .
                    date('d/m/Y', strtotime($stage['data_inizio'])) . " - " .
                    date('d/m/Y', strtotime($stage['data_fine'])) . ")";
        
        $this->pdo->prepare("
            INSERT INTO notifiche 
            (utente_id, tipo, priorita, titolo, messaggio, riferimento_tabella, riferimento_id)
            VALUES (
                (SELECT id FROM utenti WHERE docente_id = ? LIMIT 1),
                'info', 'media', 'Lezione spostata per stage', ?, 'calendario_lezioni', ?
            )
        ")->execute([$docente_id, $messaggio, $lezione['id']]);
    }
    
    private function verificaConflittoSpostamento($lezione, $nuova_data, $nuovo_slot) {
        $slot_id = $nuovo_slot ?: $lezione['slot_id'];
        
        // Verifica conflitti docente
        $conflitto_docente = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM calendario_lezioni 
            WHERE docente_id = ? 
            AND data_lezione = ? 
            AND slot_id = ?
            AND stato != 'cancellata'
            AND id != ?
        ")->execute([$lezione['docente_id'], $nuova_data, $slot_id, $lezione['id']])->fetchColumn();
        
        if ($conflitto_docente > 0) {
            return "Conflitto con altro impegno del docente";
        }
        
        // Verifica conflitti aula
        if ($lezione['aula_id']) {
            $conflitto_aula = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM calendario_lezioni 
                WHERE aula_id = ? 
                AND data_lezione = ? 
                AND slot_id = ?
                AND stato != 'cancellata'
                AND id != ?
            ")->execute([$lezione['aula_id'], $nuova_data, $slot_id, $lezione['id']])->fetchColumn();
            
            if ($conflitto_aula > 0) {
                return "Aula non disponibile";
            }
        }
        
        return false;
    }
    
    private function calcolaOreEffettuatePreStage($classe_id, $materia_id, $data_inizio_stage) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(ore_effettive), 0) as totale
            FROM calendario_lezioni 
            WHERE classe_id = ? 
            AND materia_id = ?
            AND data_lezione < ?
            AND stato IN ('svolta', 'confermata')
        ");
        $stmt->execute([$classe_id, $materia_id, $data_inizio_stage]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['totale'];
    }
    
    private function logAttivita($tipo, $azione, $descrizione, $tabella, $record_id) {
        $this->pdo->prepare("
            INSERT INTO log_attivita 
            (tipo, azione, descrizione, tabella, record_id, utente, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $tipo, $azione, $descrizione, $tabella, $record_id, 
            $_SESSION['user_id'] ?? 'sistema', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }
}
?>