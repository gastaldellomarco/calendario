<?php
/**
 * Classe per generare suggerimenti intelligenti per risolvere conflitti
 */
class SuggerimentiRisoluzione {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Suggerisce slot alternativi per una lezione
     */
    public function suggerisciSlotAlternativi($lezione_id, $num_suggerimenti = 3) {
        // Ottieni dettagli lezione
        $sql_lezione = "SELECT cl.*, c.sede_id, m.nome as materia_nome, 
                               d.id as docente_id, d.max_ore_giorno,
                               c.numero_studenti
                        FROM calendario_lezioni cl
                        JOIN classi c ON cl.classe_id = c.id
                        JOIN materie m ON cl.materia_id = m.id
                        JOIN docenti d ON cl.docente_id = d.id
                        WHERE cl.id = ?";
        
        $stmt = $this->pdo->prepare($sql_lezione);
        $stmt->execute([$lezione_id]);
        $lezione = $stmt->fetch();
        
        if (!$lezione) {
            return [];
        }
        
        $suggerimenti = [];
        
        // Cerca slot liberi per la stessa classe
        $sql_slot_classe = "SELECT os.*, 
                            COUNT(cl.id) as lezioni_classe,
                            COUNT(cl2.id) as lezioni_docente,
                            COUNT(cl3.id) as lezioni_aula
                     FROM orari_slot os
                     LEFT JOIN calendario_lezioni cl ON os.id = cl.slot_id 
                         AND cl.data_lezione = ? 
                         AND cl.classe_id = ?
                         AND cl.stato != 'cancellata'
                     LEFT JOIN calendario_lezioni cl2 ON os.id = cl2.slot_id 
                         AND cl2.data_lezione = ? 
                         AND cl2.docente_id = ?
                         AND cl2.stato != 'cancellata'
                     LEFT JOIN calendario_lezioni cl3 ON os.id = cl3.slot_id 
                         AND cl3.data_lezione = ? 
                         AND cl3.aula_id = ?
                         AND cl3.stato != 'cancellata'
                     WHERE os.attivo = 1
                     AND (cl.id IS NULL OR cl.id = ?)  // Slot libero o stesso slot
                     AND cl2.id IS NULL  // Docente libero
                     AND (cl3.id IS NULL OR cl3.aula_id = ?)  // Aula libera o stessa aula
                     GROUP BY os.id
                     ORDER BY 
                         CASE WHEN cl.id = ? THEN 1 ELSE 0 END DESC,  // Preferisci stesso slot se aula cambia
                         ABS(os.numero_slot - (SELECT numero_slot FROM orari_slot WHERE id = ?)) ASC  // Slot vicini
                     LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql_slot_classe);
        $stmt->execute([
            $lezione['data_lezione'], $lezione['classe_id'],
            $lezione['data_lezione'], $lezione['docente_id'],
            $lezione['data_lezione'], $lezione['aula_id'],
            $lezione_id, $lezione['aula_id'],
            $lezione_id, $lezione['slot_id'],
            $num_suggerimenti
        ]);
        
        $slot_alternativi = $stmt->fetchAll();
        
        foreach ($slot_alternativi as $slot) {
            $punteggio = $this->calcolaPunteggioSlot($lezione, $slot);
            
            $suggerimenti[] = [
                'slot_id' => $slot['id'],
                'numero_slot' => $slot['numero_slot'],
                'ora_inizio' => $slot['ora_inizio'],
                'ora_fine' => $slot['ora_fine'],
                'data_suggerita' => $lezione['data_lezione'],
                'punteggio' => $punteggio,
                'motivo' => $this->getMotivoPunteggioSlot($punteggio)
            ];
        }
        
        return $suggerimenti;
    }
    
    /**
     * Suggerisce docenti alternativi per una materia
     */
    public function suggerisciDocentiAlternativi($materia_id, $data, $slot_id, $sede_id = null) {
        $sql = "SELECT d.*, dm.preferenza,
                       COUNT(cl.id) as lezioni_esistenti,
                       SUM(cmd.ore_settimanali) as ore_assegnate,
                       (d.max_ore_settimana - SUM(cmd.ore_settimanali)) as ore_libere
                FROM docenti d
                JOIN docenti_materie dm ON d.id = dm.docente_id
                LEFT JOIN classi_materie_docenti cmd ON d.id = cmd.docente_id AND cmd.attivo = 1
                LEFT JOIN calendario_lezioni cl ON d.id = cl.docente_id 
                    AND cl.data_lezione = ? 
                    AND cl.slot_id = ?
                    AND cl.stato != 'cancellata'
                WHERE dm.materia_id = ? 
                AND dm.abilitato = 1
                AND d.stato = 'attivo'
                AND cl.id IS NULL  // Docente libero in quello slot
                GROUP BY d.id
                HAVING ore_libere > 0 OR ore_libere IS NULL
                ORDER BY 
                    dm.preferenza ASC,  // Preferenza più alta
                    ore_libere DESC,    // Più ore libere
                    lezioni_esistenti ASC  // Meno lezioni esistenti
                LIMIT 5";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$data, $slot_id, $materia_id]);
        $docenti = $stmt->fetchAll();
        
        $suggerimenti = [];
        foreach ($docenti as $docente) {
            $punteggio = $this->calcolaPunteggioDocente($docente);
            
            $suggerimenti[] = [
                'docente_id' => $docente['id'],
                'docente_nome' => $docente['nome'] . ' ' . $docente['cognome'],
                'preferenza' => $docente['preferenza'],
                'ore_libere' => $docente['ore_libere'] ?? 0,
                'punteggio' => $punteggio,
                'motivo' => $this->getMotivoPunteggioDocente($punteggio, $docente)
            ];
        }
        
        return $suggerimenti;
    }
    
    /**
     * Suggerisce aule alternative
     */
    public function suggerisciAuleAlternative($tipo_aula = null, $data, $slot_id, $sede_id, $capienza_minima = null) {
        $where_conditions = ["a.attiva = 1", "a.sede_id = ?"];
        $params = [$sede_id];
        
        if ($tipo_aula) {
            $where_conditions[] = "a.tipo = ?";
            $params[] = $tipo_aula;
        }
        
        if ($capienza_minima) {
            $where_conditions[] = "a.capienza >= ?";
            $params[] = $capienza_minima;
        }
        
        $where_sql = implode(" AND ", $where_conditions);
        
        $sql = "SELECT a.*,
                       COUNT(cl.id) as lezioni_esistenti,
                       (SELECT COUNT(*) FROM calendario_lezioni cl2 
                        WHERE cl2.aula_id = a.id 
                        AND cl2.data_lezione = ? 
                        AND cl2.slot_id = ? 
                        AND cl2.stato != 'cancellata') as occupata
                FROM aule a
                LEFT JOIN calendario_lezioni cl ON a.id = cl.aula_id 
                    AND cl.data_lezione = ? 
                    AND cl.slot_id = ?
                    AND cl.stato != 'cancellata'
                WHERE $where_sql
                GROUP BY a.id
                HAVING occupata = 0  // Aula libera in quello slot
                ORDER BY 
                    lezioni_esistenti ASC,  // Aula meno utilizzata
                    a.capienza DESC         // Aula più capiente
                LIMIT 5";
        
        $params = array_merge([$data, $slot_id, $data, $slot_id], $params);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $aule = $stmt->fetchAll();
        
        $suggerimenti = [];
        foreach ($aule as $aula) {
            $punteggio = $this->calcolaPunteggioAula($aula, $tipo_aula, $capienza_minima);
            
            $suggerimenti[] = [
                'aula_id' => $aula['id'],
                'aula_nome' => $aula['nome'],
                'tipo' => $aula['tipo'],
                'capienza' => $aula['capienza'],
                'punteggio' => $punteggio,
                'motivo' => $this->getMotivoPunteggioAula($punteggio, $aula)
            ];
        }
        
        return $suggerimenti;
    }
    
    /**
     * Calcola priorità di risoluzione per un conflitto
     */
    public function calcolaPrioritaRisoluzione($conflitto_id) {
        $sql = "SELECT c.*, 
                       DATEDIFF(c.data_conflitto, CURDATE()) as giorni_alla_data,
                       (SELECT COUNT(*) FROM conflitti_orario c2 
                        WHERE c2.docente_id = c.docente_id 
                        AND c2.risolto = 0) as altri_conflitti_docente
                FROM conflitti_orario c
                WHERE c.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conflitto_id]);
        $conflitto = $stmt->fetch();
        
        if (!$conflitto) {
            return 0;
        }
        
        $punteggio = 0;
        
        // Gravità
        $punteggio += [
            'critico' => 100,
            'error' => 60,
            'warning' => 30
        ][$conflitto['gravita']] ?? 0;
        
        // Prossimità alla data
        if ($conflitto['giorni_alla_data'] <= 0) {
            $punteggio += 50; // Conflitto per oggi o passato
        } elseif ($conflitto['giorni_alla_data'] <= 7) {
            $punteggio += 30; // Conflitto nella prossima settimana
        } elseif ($conflitto['giorni_alla_data'] <= 30) {
            $punteggio += 15; // Conflitto nel prossimo mese
        }
        
        // Altri conflitti per lo stesso docente
        if ($conflitto['altri_conflitti_docente'] > 0) {
            $punteggio += 20;
        }
        
        // Tipo di conflitto
        $punteggio += [
            'doppia_assegnazione_docente' => 40,
            'doppia_aula' => 25,
            'superamento_ore' => 35,
            'vincolo_docente' => 30,
            'vincolo_classe' => 20,
            'aula_non_adeguata' => 15,
            'sede_multipla' => 25
        ][$conflitto['tipo']] ?? 0;
        
        return min(100, $punteggio);
    }
    
    /**
     * Calcola punteggio per uno slot alternativo
     */
    private function calcolaPunteggioSlot($lezione, $slot) {
        $punteggio = 100;
        
        // Penalità per cambio di slot
        $differenza_slot = abs($slot['numero_slot'] - $lezione['slot_id']);
        $punteggio -= $differenza_slot * 5;
        
        // Bonus se aula rimane la stessa
        if ($slot['lezioni_aula'] == 0) {
            $punteggio += 10;
        }
        
        // Penalità se ci sono altre lezioni per la classe
        if ($slot['lezioni_classe'] > 0) {
            $punteggio -= 20;
        }
        
        return max(0, min(100, $punteggio));
    }
    
    /**
     * Calcola punteggio per un docente alternativo
     */
    private function calcolaPunteggioDocente($docente) {
        $punteggio = 100;
        
        // Bonus per preferenza alta
        $punteggio += (3 - $docente['preferenza']) * 15;
        
        // Bonus per ore libere
        if ($docente['ore_libere'] > 5) {
            $punteggio += 10;
        } elseif ($docente['ore_libere'] <= 2) {
            $punteggio -= 15;
        }
        
        // Penalità per lezioni esistenti
        if ($docente['lezioni_esistenti'] > 0) {
            $punteggio -= 10;
        }
        
        return max(0, min(100, $punteggio));
    }
    
    /**
     * Calcola punteggio per un'aula alternativa
     */
    private function calcolaPunteggioAula($aula, $tipo_richiesto, $capienza_minima) {
        $punteggio = 100;
        
        // Bonus per tipo corretto
        if ($tipo_richiesto && $aula['tipo'] === $tipo_richiesto) {
            $punteggio += 20;
        }
        
        // Bonus per capienza adeguata
        if ($capienza_minima && $aula['capienza'] >= $capienza_minima) {
            $punteggio += 15;
        } elseif ($capienza_minima) {
            $punteggio -= 25;
        }
        
        // Bonus per aula poco utilizzata
        if ($aula['lezioni_esistenti'] < 5) {
            $punteggio += 10;
        }
        
        return max(0, min(100, $punteggio));
    }
    
    private function getMotivoPunteggioSlot($punteggio) {
        if ($punteggio >= 80) return "Ottima compatibilità";
        if ($punteggio >= 60) return "Buona compatibilità";
        if ($punteggio >= 40) return "Compatibilità accettabile";
        return "Compatibilità limitata";
    }
    
    private function getMotivoPunteggioDocente($punteggio, $docente) {
        if ($punteggio >= 80) return "Docente ideale";
        if ($punteggio >= 60) return "Docente disponibile";
        return "Docente con limitazioni";
    }
    
    private function getMotivoPunteggioAula($punteggio, $aula) {
        if ($punteggio >= 80) return "Aula perfetta";
        if ($punteggio >= 60) return "Aula adeguata";
        return "Aula con compromessi";
    }
}