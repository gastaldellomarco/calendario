<?php
/**
 * Classe per trovare docenti sostituti ottimali
 */

class SostitutoFinder {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Trova sostituti per una lezione specifica
     */
    public function trovaSostituti($lezione_id, $opzioni = []) {
        // Dettagli lezione
        $lezione = $this->getDettagliLezione($lezione_id);
        if (!$lezione) {
            return [];
        }
        
        // Docenti candidati (escludendo docente originale)
        $candidati = $this->getDocentiCandidati($lezione);
        
        // Calcola score per ogni candidato
        $sostituti = [];
        foreach ($candidati as $docente) {
            $score = $this->calcolaScoreDocente($docente, $lezione);
            
            if ($score['score_totale'] > 0) { // Solo quelli con score positivo
                $sostituti[] = array_merge($docente, $score);
            }
        }
        
        // Ordina per score decrescente
        usort($sostituti, function($a, $b) {
            return $b['score_totale'] - $a['score_totale'];
        });
        
        // Limita risultati
        $limit = $opzioni['limit'] ?? 10;
        return array_slice($sostituti, 0, $limit);
    }
    
    /**
     * Ottiene dettagli completi della lezione
     */
    private function getDettagliLezione($lezione_id) {
        return $this->db->query("
            SELECT cl.*, c.nome as classe_nome, m.nome as materia_nome, m.id as materia_id,
                   d.id as docente_id, d.cognome as docente_cognome, d.nome as docente_nome,
                   s.id as sede_id, s.nome as sede_nome,
                   os.ora_inizio, os.ora_fine, os.numero_slot
            FROM calendario_lezioni cl
            JOIN classi c ON cl.classe_id = c.id
            JOIN materie m ON cl.materia_id = m.id
            JOIN docenti d ON cl.docente_id = d.id
            JOIN sedi s ON cl.sede_id = s.id
            JOIN orari_slot os ON cl.slot_id = os.id
            WHERE cl.id = ?
        ", [$lezione_id])->fetch();
    }
    
    /**
     * Ottiene lista docenti candidati
     */
    private function getDocentiCandidati($lezione) {
        return $this->db->query("
            SELECT d.id, d.cognome, d.nome, d.sede_principale_id, d.ore_settimanali_contratto,
                   d.max_ore_giorno, d.max_ore_settimana, d.permette_buchi_orario,
                   s.nome as sede_nome,
                   -- Ore già fatte oggi
                   (SELECT COUNT(*) 
                    FROM calendario_lezioni cl2 
                    JOIN orari_slot os2 ON cl2.slot_id = os2.id 
                    WHERE cl2.docente_id = d.id 
                    AND cl2.data_lezione = ? 
                    AND cl2.stato IN ('pianificata', 'confermata')) as ore_oggi,
                   
                   -- Ore già fatte questa settimana
                   (SELECT COUNT(*) 
                    FROM calendario_lezioni cl2 
                    WHERE cl2.docente_id = d.id 
                    AND cl2.data_lezione BETWEEN ? AND ? 
                    AND cl2.stato IN ('pianificata', 'confermata')) as ore_settimana
                   
            FROM docenti d
            JOIN sedi s ON d.sede_principale_id = s.id
            WHERE d.stato = 'attivo'
            AND d.id != ?
            ORDER BY d.cognome, d.nome
        ", [
            $lezione['data_lezione'],
            date('Y-m-d', strtotime('monday this week', strtotime($lezione['data_lezione']))),
            date('Y-m-d', strtotime('sunday this week', strtotime($lezione['data_lezione']))),
            $lezione['docente_id']
        ])->fetchAll();
    }
    
    /**
     * Calcola score di idoneità per un docente
     */
    private function calcolaScoreDocente($docente, $lezione) {
        $score = 0;
        $dettagli_score = [];
        
        // 1. Competenza nella materia (+50 punti)
        $puo_insegnare = $this->verificaCompetenzaMateria($docente['id'], $lezione['materia_id']);
        if ($puo_insegnare) {
            $score += 50;
            $dettagli_score[] = "+50 (Competenza materia)";
        }
        
        // 2. Disponibilità orario (+30 se completamente libero)
        $disponibile = $this->verificaDisponibilita($docente['id'], $lezione);
        if ($disponibile['completamente_libero']) {
            $score += 30;
            $dettagli_score[] = "+30 (Completamente libero)";
        } elseif ($disponibile['parzialmente_libero']) {
            $score += 15;
            $dettagli_score[] = "+15 (Parzialmente libero)";
        }
        
        // 3. Stessa sede (+30 punti)
        if ($docente['sede_principale_id'] == $lezione['sede_id']) {
            $score += 30;
            $dettagli_score[] = "+30 (Stessa sede)";
        }
        
        // 4. Esperienza con la classe (+20 punti)
        $esperienza_classe = $this->verificaEsperienzaClasse($docente['id'], $lezione['classe_id']);
        if ($esperienza_classe) {
            $score += 20;
            $dettagli_score[] = "+20 (Esperienza classe)";
        }
        
        // 5. Preferenza materia (+10 per alta, +5 per media)
        $preferenza = $this->getPreferenzaMateria($docente['id'], $lezione['materia_id']);
        if ($preferenza == 1) {
            $score += 10;
            $dettagli_score[] = "+10 (Preferenza alta)";
        } elseif ($preferenza == 2) {
            $score += 5;
            $dettagli_score[] = "+5 (Preferenza media)";
        }
        
        // PENALITÀ
        
        // 6. Ore già fatte oggi (-10 per ogni ora oltre la 3a)
        if ($docente['ore_oggi'] >= 3) {
            $penale = ($docente['ore_oggi'] - 2) * 10;
            $score -= min($penale, 30); // Max 30 punti di penalità
            if ($penale > 0) {
                $dettagli_score[] = "-{$penale} (Ore oggi: {$docente['ore_oggi']})";
            }
        }
        
        // 7. Ore oltre contratto settimanale (-5 per ogni ora)
        $ore_oltre_contratto = max(0, $docente['ore_settimana'] - $docente['ore_settimanali_contratto']);
        if ($ore_oltre_contratto > 0) {
            $penale = $ore_oltre_contratto * 5;
            $score -= min($penale, 25); // Max 25 punti di penalità
            $dettagli_score[] = "-{$penale} (Ore oltre contratto: +{$ore_oltre_contratto})";
        }
        
        // 8. Vincoli specifici (-100 se viola vincoli importanti)
        $vincoli_violati = $this->verificaVincoli($docente['id'], $lezione);
        if ($vincoli_violati) {
            $score -= 100;
            $dettagli_score[] = "-100 (Vincoli violati)";
        }
        
        // Normalizza score tra 0-100
        $score_normalizzato = max(0, min(100, $score));
        
        return [
            'score_totale' => $score_normalizzato,
            'score_grezzo' => $score,
            'puo_insegnare' => $puo_insegnare,
            'disponibile' => $disponibile['completamente_libero'],
            'esperienza_classe' => $esperienza_classe,
            'preferenza_materia' => $preferenza,
            'ore_oggi' => $docente['ore_oggi'],
            'ore_settimana' => $docente['ore_settimana'],
            'ore_contratto' => $docente['ore_settimanali_contratto'],
            'dettagli_score' => $dettagli_score
        ];
    }
    
    /**
     * Verifica se il docente può insegnare la materia
     */
    private function verificaCompetenzaMateria($docente_id, $materia_id) {
        $result = $this->db->query("
            SELECT 1 FROM docenti_materie 
            WHERE docente_id = ? AND materia_id = ? AND abilitato = 1
        ", [$docente_id, $materia_id])->fetch();
        
        return (bool)$result;
    }
    
    /**
     * Verifica disponibilità del docente nell'orario
     */
    private function verificaDisponibilita($docente_id, $lezione) {
        // Verifica se già impegnato in quell'orario
        $impegnato = $this->db->query("
            SELECT 1 FROM calendario_lezioni cl
            WHERE cl.docente_id = ? 
            AND cl.data_lezione = ?
            AND cl.slot_id = ?
            AND cl.stato IN ('pianificata', 'confermata')
        ", [$docente_id, $lezione['data_lezione'], $lezione['slot_id']])->fetch();
        
        if ($impegnato) {
            return ['completamente_libero' => false, 'parzialmente_libero' => false];
        }
        
        // Verifica vincoli di indisponibilità
        $vincolo_indisponibilita = $this->db->query("
            SELECT 1 FROM vincoli_docenti 
            WHERE docente_id = ? 
            AND giorno_settimana = DAYOFWEEK(?)
            AND ((ora_inizio IS NULL AND ora_fine IS NULL) OR 
                 (TIME(?) BETWEEN ora_inizio AND ora_fine))
            AND attivo = 1
        ", [$docente_id, $lezione['data_lezione'], $lezione['ora_inizio']])->fetch();
        
        if ($vincolo_indisponibilita) {
            return ['completamente_libero' => false, 'parzialmente_libero' => false];
        }
        
        return ['completamente_libero' => true, 'parzialmente_libero' => true];
    }
    
    /**
     * Verifica esperienza con la classe
     */
    private function verificaEsperienzaClasse($docente_id, $classe_id) {
        $esperienza = $this->db->query("
            SELECT 1 FROM classi_materie_docenti 
            WHERE docente_id = ? AND classe_id = ?
        ", [$docente_id, $classe_id])->fetch();
        
        return (bool)$esperienza;
    }
    
    /**
     * Ottiene preferenza per la materia
     */
    private function getPreferenzaMateria($docente_id, $materia_id) {
        $preferenza = $this->db->query("
            SELECT preferenza FROM docenti_materie 
            WHERE docente_id = ? AND materia_id = ?
        ", [$docente_id, $materia_id])->fetchColumn();
        
        return $preferenza ?: 3; // Default: bassa preferenza
    }
    
    /**
     * Verifica vincoli importanti
     */
    private function verificaVincoli($docente_id, $lezione) {
        // Verifica max ore giornaliere
        $ore_oggi = $this->db->query("
            SELECT COUNT(*) FROM calendario_lezioni 
            WHERE docente_id = ? AND data_lezione = ? 
            AND stato IN ('pianificata', 'confermata')
        ", [$docente_id, $lezione['data_lezione']])->fetchColumn();
        
        $max_ore_giorno = $this->db->query("
            SELECT max_ore_giorno FROM docenti WHERE id = ?
        ", [$docente_id])->fetchColumn();
        
        if ($ore_oggi >= $max_ore_giorno) {
            return true; // Supererebbe il limite
        }
        
        // Altri vincoli possono essere aggiunti qui
        
        return false;
    }
}