<?php
require_once __DIR__ . '/VincoliValidator.php';
require_once __DIR__ . '/ConflittiDetector.php';

class CalendarioGenerator {
    private $db;
    private $validator;
    private $conflittiDetector;
    private $aule = [];
    private $slot_orari = [];
    private $log = [];
    private $statistiche = [
        'lezioni_assegnate' => 0,
        'conflitti' => 0,
        'ore_totali' => 0,
        'tempo_esecuzione' => 0
    ];

    public function __construct($db) {
        $this->db = $db;
        $this->validator = new VincoliValidator($db);
        $this->conflittiDetector = new ConflittiDetector($db);
    }

    public function generaCalendario($anno_scolastico_id, $opzioni = []) {
        $start_time = microtime(true);
        
        try {
            $this->log("🚀 Inizio generazione calendario per anno scolastico ID: " . $anno_scolastico_id);
            
            // STEP 1 - CARICAMENTO DATI
            $dati = $this->caricaDatiBase($anno_scolastico_id);
            if (!$dati) {
                throw new Exception("Dati insufficienti per generare il calendario");
            }

            // Backup del calendario esistente
            $this->creaBackup($anno_scolastico_id);

            // STEP 2 - CREAZIONE STRUTTURA
            $calendario = $this->inizializzaCalendario($dati);

            // STEP 3 - APPLICAZIONE VINCOLI HARD
            $this->applicaVincoliHard($calendario, $dati);

            // STEP 4 - ALGORITMO DI ASSEGNAZIONE
            $this->assegnaLezioni($calendario, $dati, $opzioni);

            // STEP 5 - SALVATAGGIO
            $this->salvaCalendario($calendario, $dati);
            
            // STEP 6 - RILEVA CONFLITTI FINALI
            $this->rilevaConflittiFinali($anno_scolastico_id);

            $this->statistiche['tempo_esecuzione'] = microtime(true) - $start_time;
            $this->log("✅ Generazione completata in " . round($this->statistiche['tempo_esecuzione'], 2) . " secondi");

            return [
                'success' => true,
                'statistiche' => $this->statistiche,
                'log' => $this->log
            ];

        } catch (Exception $e) {
            $this->log("❌ ERRORE: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'statistiche' => $this->statistiche,
                'log' => $this->log
            ];
        }
    }

    private function caricaDatiBase($anno_scolastico_id) {
        $this->log("📥 Caricamento dati base...");

        // Carica anno scolastico
        $stmt = $this->db->prepare("SELECT * FROM anni_scolastici WHERE id = ?");
        $stmt->execute([$anno_scolastico_id]);
        $anno_scolastico = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$anno_scolastico) {
            throw new Exception("Anno scolastico non trovato");
        }

        $this->log("📅 Anno scolastico: {$anno_scolastico['anno']} ({$anno_scolastico['data_inizio']} - {$anno_scolastico['data_fine']})");

        // Carica assegnazioni attive
    $stmt = $this->db->prepare("\n            SELECT cmd.*, c.nome as classe_nome, c.sede_id, c.anno_corso,\n                   m.nome as materia_nome, m.tipo as materia_tipo, m.richiede_laboratorio, m.distribuzione as materia_distribuzione,\n                   d.cognome, d.nome as docente_nome, d.sede_principale_id,\n                   pf.nome as percorso_nome\n            FROM classi_materie_docenti cmd\n            JOIN classi c ON cmd.classe_id = c.id\n            JOIN materie m ON cmd.materia_id = m.id\n            JOIN docenti d ON cmd.docente_id = d.id\n            JOIN percorsi_formativi pf ON c.percorso_formativo_id = pf.id\n            WHERE cmd.attivo = 1 AND c.anno_scolastico_id = ?\n            ORDER BY cmd.priorita ASC, cmd.ore_settimanali DESC\n        ");
        $stmt->execute([$anno_scolastico_id]);
        $assegnazioni = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($assegnazioni)) {
            throw new Exception("Nessuna assegnazione attiva trovata");
        }

        $this->log("📚 Trovate " . count($assegnazioni) . " assegnazioni attive");

        // Carica slot orari
        $stmt = $this->db->prepare("SELECT * FROM orari_slot WHERE attivo = 1 ORDER BY numero_slot");
        $stmt->execute();
        $slot_orari = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->slot_orari = $slot_orari;

        $this->log("⏰ Trovati " . count($slot_orari) . " slot orari attivi");

        // Carica aule
        $stmt = $this->db->prepare("SELECT * FROM aule WHERE attiva = 1");
        $stmt->execute();
        $aule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->aule = $aule;

        $this->log("🏫 Trovate " . count($aule) . " aule attive");

        // Carica vincoli docenti
        $stmt = $this->db->prepare("SELECT * FROM vincoli_docenti WHERE attivo = 1");
        $stmt->execute();
        $vincoli_docenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->log("🔒 Trovati " . count($vincoli_docenti) . " vincoli docenti");

        // Carica vincoli classi
        $stmt = $this->db->prepare("SELECT * FROM vincoli_classi WHERE attivo = 1");
        $stmt->execute();
        $vincoli_classi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->log("🔒 Trovati " . count($vincoli_classi) . " vincoli classi");

        // Carica giorni chiusura
        $stmt = $this->db->prepare("
            SELECT * FROM giorni_chiusura 
            WHERE data_chiusura BETWEEN ? AND ?
        ");
        $stmt->execute([$anno_scolastico['data_inizio'], $anno_scolastico['data_fine']]);
        $giorni_chiusura = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->log("📅 Trovati " . count($giorni_chiusura) . " giorni di chiusura");

        return [
            'anno_scolastico' => $anno_scolastico,
            'assegnazioni' => $assegnazioni,
            'slot_orari' => $slot_orari,
            'aule' => $aule,
            'vincoli_docenti' => $vincoli_docenti,
            'vincoli_classi' => $vincoli_classi,
            'giorni_chiusura' => $giorni_chiusura
        ];
    }

    private function inizializzaCalendario($dati) {
        $this->log("📊 Inizializzazione struttura calendario...");

        $calendario = [];
        $data_corrente = new DateTime($dati['anno_scolastico']['data_inizio']);
        $data_fine = new DateTime($dati['anno_scolastico']['data_fine']);

        $date_lavorative = 0;
        $date_chiusura = 0;

        // Genera tutte le date lavorative
        while ($data_corrente <= $data_fine) {
            $data_str = $data_corrente->format('Y-m-d');
            $giorno_settimana = $data_corrente->format('N'); // 1=Lunedì, 7=Domenica

            // Salta domeniche
            if ($giorno_settimana != 7) {
                // Salta giorni di chiusura
                $is_chiusura = false;
                foreach ($dati['giorni_chiusura'] as $chiusura) {
                    if ($chiusura['data_chiusura'] == $data_str) {
                        $is_chiusura = true;
                        $date_chiusura++;
                        break;
                    }
                }

                if (!$is_chiusura) {
                    $calendario[$data_str] = [];
                    foreach ($dati['slot_orari'] as $slot) {
                        $calendario[$data_str][$slot['id']] = [
                            'disponibile' => true,
                            'assegnazioni' => []
                        ];
                    }
                    $date_lavorative++;
                }
            }

            $data_corrente->modify('+1 day');
        }

        $this->log("📅 Generate {$date_lavorative} date lavorative, escluse {$date_chiusura} giorni di chiusura");
        return $calendario;
    }

    private function applicaVincoliHard(&$calendario, $dati) {
        $this->log("🔧 Applicazione vincoli hard...");

        $vincoli_applicati = 0;

        foreach ($calendario as $data => $slots) {
            $giorno_settimana = date('N', strtotime($data));

            // Applica vincoli docenti
            foreach ($dati['vincoli_docenti'] as $vincolo) {
                if ($vincolo['giorno_settimana'] == $giorno_settimana) {
                    foreach ($slots as $slot_id => $slot) {
                        if ($this->slotInVincolo($slot_id, $vincolo)) {
                            // Segna slot come non disponibile per il docente specifico
                            if (!isset($calendario[$data][$slot_id]['vincoli_docenti'])) {
                                $calendario[$data][$slot_id]['vincoli_docenti'] = [];
                            }
                            $calendario[$data][$slot_id]['vincoli_docenti'][] = $vincolo['docente_id'];
                            $vincoli_applicati++;
                        }
                    }
                }
            }

            // Applica vincoli classi
            foreach ($dati['vincoli_classi'] as $vincolo) {
                if ($vincolo['giorno_settimana'] == $giorno_settimana) {
                    if ($vincolo['tipo'] == 'no_lezioni') {
                        // Segna tutti gli slot come non disponibili per la classe
                        foreach ($slots as $slot_id => $slot) {
                            if (!isset($calendario[$data][$slot_id]['vincoli_classi'])) {
                                $calendario[$data][$slot_id]['vincoli_classi'] = [];
                            }
                            $calendario[$data][$slot_id]['vincoli_classi'][] = $vincolo['classe_id'];
                            $vincoli_applicati++;
                        }
                    }
                }
            }
        }

        $this->log("✅ Applicati {$vincoli_applicati} vincoli hard");
    }

    private function assegnaLezioni(&$calendario, $dati, $opzioni) {
        $this->log("🎯 Inizio assegnazione lezioni...");

        $strategia = $opzioni['strategia'] ?? 'bilanciato';
        $max_tentativi = $opzioni['max_tentativi'] ?? 3;
        $considera_preferenze = $opzioni['considera_preferenze'] ?? true;

        $this->log("⚙️ Strategia: {$strategia}, Max tentativi: {$max_tentativi}, Preferenze: " . ($considera_preferenze ? 'SI' : 'NO'));

        $assegnazioni_elaborate = 0;
        $totale_assegnazioni = count($dati['assegnazioni']);

        foreach ($dati['assegnazioni'] as $assegnazione) {
            $assegnazioni_elaborate++;
            $this->log("📝 Elaborando assegnazione {$assegnazioni_elaborate}/{$totale_assegnazioni}: {$assegnazione['classe_nome']} - {$assegnazione['materia_nome']} - {$assegnazione['cognome']}");

            $lezioni_da_assegnare = $this->calcolaLezioniDaAssegnare($assegnazione, $dati);
            $lezioni_assegnate = 0;

            foreach ($lezioni_da_assegnare as $settimana => $num_lezioni_settimana) {
                for ($i = 0; $i < $num_lezioni_settimana; $i++) {
                    $slot_trovato = $this->trovaSlotDisponibile(
                        $calendario, 
                        $assegnazione, 
                        $settimana, 
                        $strategia,
                        $max_tentativi
                    );

                    if ($slot_trovato) {
                        $this->assegnaSlot($calendario, $slot_trovato, $assegnazione);
                        $lezioni_assegnate++;
                        $this->statistiche['lezioni_assegnate']++;
                    } else {
                        $this->registraConflitto($assegnazione, $settimana, "Nessuno slot disponibile");
                        $this->statistiche['conflitti']++;
                    }
                }
            }

            $this->aggiornaOreRimanenti($assegnazione, $lezioni_assegnate);
            
            if ($lezioni_assegnate > 0) {
                $this->log("✅ Assegnate {$lezioni_assegnate} lezioni per {$assegnazione['classe_nome']} - {$assegnazione['materia_nome']}");
            } else {
                $this->log("⚠️ Nessuna lezione assegnata per {$assegnazione['classe_nome']} - {$assegnazione['materia_nome']}");
            }
        }

        $this->log("🎉 Assegnazione completata: {$this->statistiche['lezioni_assegnate']} lezioni assegnate, {$this->statistiche['conflitti']} conflitti");
    }

    private function calcolaLezioniDaAssegnare($assegnazione, $dati) {
        $settimane_lezione = max(1, intval($dati['anno_scolastico']['settimane_lezione'] ?? 33));
        $ore_settimanali = max(1, intval($assegnazione['ore_settimanali'] ?? 1));
        $ore_annuali_target = intval($assegnazione['ore_annuali_previste'] ?? round($settimane_lezione * $ore_settimanali));
        $distribuzione = $assegnazione['materia_distribuzione'] ?? ($assegnazione['distribuzione'] ?? 'settimanale');

        // Cap totale alle capacità settimanali disponibili
        $capacita_totale = $settimane_lezione * $ore_settimanali;
        if ($ore_annuali_target > $capacita_totale) {
            $ore_annuali_target = $capacita_totale;
        }

        // Initialize
        $lezioni_per_settimana = array_fill(1, $settimane_lezione, 0);

        if ($distribuzione === 'settimanale') {
            // Distribuzione regolare: cerca di bilanciare le ore durante tutte le settimane
            $base = intdiv($ore_annuali_target, $settimane_lezione);
            $base = min($base, $ore_settimanali);
            $remainder = $ore_annuali_target - ($base * $settimane_lezione);
            for ($w = 1; $w <= $settimane_lezione; $w++) {
                $lezioni_per_settimana[$w] = $base;
            }
            // Distribuisci il resto su alcune settimane (una per volta)
            $i = 0;
            while ($remainder > 0 && $i < $settimane_lezione) {
                $idx = intval(round(($i + 0.5) * $settimane_lezione / max(1, $remainder)));
                $idx = max(1, min($settimane_lezione, $idx));
                // Find next available week which is not at capacity
                $attempts = 0;
                $start = $idx;
                while ($lezioni_per_settimana[$idx] >= $ore_settimanali && $attempts < $settimane_lezione) {
                    $idx = ($idx % $settimane_lezione) + 1;
                    $attempts++;
                }
                if ($lezioni_per_settimana[$idx] < $ore_settimanali) {
                    $lezioni_per_settimana[$idx]++;
                    $remainder--;
                }
                $i++;
            }
        } elseif ($distribuzione === 'sparsa') {
            // Sparsa: poche settimane piene e alcune con singole ore se necessario
            $fullWeeks = intdiv($ore_annuali_target, $ore_settimanali);
            $fullWeeks = min($fullWeeks, $settimane_lezione);
            $remainder = $ore_annuali_target - ($fullWeeks * $ore_settimanali);

            // Distribuisci settimane piene in modo uniforme
            for ($i = 0; $i < $fullWeeks; $i++) {
                $idx = intval(round(($i + 0.5) * $settimane_lezione / max(1, $fullWeeks)));
                $idx = max(1, min($settimane_lezione, $idx));
                $attempts = 0;
                while ($lezioni_per_settimana[$idx] >= $ore_settimanali && $attempts < $settimane_lezione) {
                    $idx = ($idx % $settimane_lezione) + 1;
                    $attempts++;
                }
                $lezioni_per_settimana[$idx] = $ore_settimanali;
            }

            // Distribuisci ore residue (una per settimana) preferibilmente vicino alle settimane piene
            $availableWeeks = array_keys($lezioni_per_settimana);
            shuffle($availableWeeks);
            foreach ($availableWeeks as $w) {
                if ($remainder <= 0) break;
                if ($lezioni_per_settimana[$w] < $ore_settimanali) {
                    $lezioni_per_settimana[$w]++;
                    $remainder--;
                }
            }
        } else {
            // casuale
            // Using deterministic seed for reproducibility per assegnazione/year
            $seed = intval($assegnazione['id'] ?? 0) + intval($dati['anno_scolastico']['id'] ?? 0);
            mt_srand($seed);
            $remaining = $ore_annuali_target;
            $iterations = 0;
            while ($remaining > 0 && $iterations < ($settimane_lezione * 10)) {
                $idx = mt_rand(1, $settimane_lezione);
                if ($lezioni_per_settimana[$idx] < $ore_settimanali) {
                    $lezioni_per_settimana[$idx]++;
                    $remaining--;
                }
                $iterations++;
            }
            // Reset seed
            mt_srand();
        }

        return $lezioni_per_settimana;
    }

    private function trovaSlotDisponibile($calendario, $assegnazione, $settimana, $strategia, $max_tentativi) {
        $date_settimana = $this->getDatePerSettimana($calendario, $settimana);
        
        if (empty($date_settimana)) {
            return null;
        }

        $tentativi = 0;

        while ($tentativi < $max_tentativi) {
            foreach ($date_settimana as $data) {
                foreach ($calendario[$data] as $slot_id => $slot) {
                    // Controlla se lo slot è disponibile
                    if (!$slot['disponibile']) {
                        continue;
                    }

                    // Controlla vincoli hard per questo slot
                    if (isset($slot['vincoli_docenti']) && in_array($assegnazione['docente_id'], $slot['vincoli_docenti'])) {
                        continue;
                    }

                    if (isset($slot['vincoli_classi']) && in_array($assegnazione['classe_id'], $slot['vincoli_classi'])) {
                        continue;
                    }

                    // Controlla disponibilità docente, classe e aula
                    if ($this->validator->isDocenteDisponibile($assegnazione['docente_id'], $data, $slot_id) &&
                        $this->validator->isClasseDisponibile($assegnazione['classe_id'], $data, $slot_id)) {
                        
                        $aula_id = $this->trovaAulaAdatta($assegnazione);
                        if ($this->validator->isAulaDisponibile($aula_id, $data, $slot_id)) {
                            return [
                                'data' => $data,
                                'slot_id' => $slot_id,
                                'aula_id' => $aula_id
                            ];
                        }
                    }
                }
            }
            $tentativi++;
        }

        return null;
    }

    private function assegnaSlot(&$calendario, $slot_trovato, $assegnazione) {
        $data = $slot_trovato['data'];
        $slot_id = $slot_trovato['slot_id'];

        $calendario[$data][$slot_id]['disponibile'] = false;
        $calendario[$data][$slot_id]['assegnazioni'][] = [
            'assegnazione_id' => $assegnazione['id'],
            'classe_id' => $assegnazione['classe_id'],
            'docente_id' => $assegnazione['docente_id'],
            'materia_id' => $assegnazione['materia_id'],
            'aula_id' => $slot_trovato['aula_id']
        ];
    }

    private function salvaCalendario($calendario, $dati) {
        $this->log("💾 Salvataggio calendario nel database...");

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO calendario_lezioni 
                (data_lezione, slot_id, classe_id, materia_id, docente_id, aula_id, 
                 assegnazione_id, ore_effettive, stato, modalita, creata_automaticamente, sede_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1.00, 'pianificata', 'presenza', 1, ?)
            ");
            
            $lezioni_salvate = 0;
            
            foreach ($calendario as $data => $slots) {
                foreach ($slots as $slot_id => $slot) {
                    if (!$slot['disponibile'] && !empty($slot['assegnazioni'])) {
                        foreach ($slot['assegnazioni'] as $assegnazione) {
                            // Trova la sede della classe
                            $stmt_sede = $this->db->prepare("SELECT sede_id FROM classi WHERE id = ?");
                            $stmt_sede->execute([$assegnazione['classe_id']]);
                            $classe = $stmt_sede->fetch(PDO::FETCH_ASSOC);
                            $sede_id = $classe['sede_id'] ?? null;
                            
                            $stmt->execute([
                                $data, 
                                $slot_id, 
                                $assegnazione['classe_id'], 
                                $assegnazione['materia_id'], 
                                $assegnazione['docente_id'],
                                $assegnazione['aula_id'], 
                                $assegnazione['assegnazione_id'],
                                $sede_id
                            ]);
                            $lezioni_salvate++;
                        }
                    }
                }
            }

            $this->db->commit();
            $this->log("✅ Calendario salvato con successo: {$lezioni_salvate} lezioni inserite");

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Errore nel salvataggio: " . $e->getMessage());
        }
    }

    private function slotInVincolo($slot_id, $vincolo) {
        // Trova lo slot corrispondente
        $slot = null;
        foreach ($this->slot_orari as $s) {
            if ($s['id'] == $slot_id) {
                $slot = $s;
                break;
            }
        }
        
        if (!$slot) {
            return false;
        }
        
        // Se il vincolo non ha orari specifici, applica a tutto il giorno
        if (!isset($vincolo['ora_inizio']) || !isset($vincolo['ora_fine'])) {
            return true;
        }
        
        // Controlla se l'orario dello slot rientra nel vincolo
        if (isset($slot['ora_inizio']) && isset($slot['ora_fine'])) {
            $slot_inizio = strtotime($slot['ora_inizio']);
            $slot_fine = strtotime($slot['ora_fine']);
            $vincolo_inizio = strtotime($vincolo['ora_inizio']);
            $vincolo_fine = strtotime($vincolo['ora_fine']);
            
            // Verifica sovrapposizione oraria
            return ($slot_inizio < $vincolo_fine && $slot_fine > $vincolo_inizio);
        }
        
        return false;
    }

    private function getDatePerSettimana($calendario, $settimana) {
        $date = array_keys($calendario);
        
        // Calcola la data di inizio della settimana (lunedì)
        $giorni_per_settimana = 5; // Lunedì-Venerdì
        $indice_inizio = ($settimana - 1) * $giorni_per_settimana;
        $indice_fine = $indice_inizio + $giorni_per_settimana;
        
        $date_settimana = [];
        for ($i = $indice_inizio; $i < $indice_fine && $i < count($date); $i++) {
            $date_settimana[] = $date[$i];
        }
        
        return $date_settimana;
    }

    private function trovaAulaAdatta($assegnazione) {
        if (empty($this->aule)) {
            return null;
        }
        
        // Trova la sede della classe
        $stmt = $this->db->prepare("SELECT sede_id FROM classi WHERE id = ?");
        $stmt->execute([$assegnazione['classe_id']]);
        $classe = $stmt->fetch(PDO::FETCH_ASSOC);
        $sede_classe = $classe['sede_id'] ?? null;
        
        $aule_candidate = [];
        foreach ($this->aule as $aula) {
            // Filtra per sede
            if ($aula['sede_id'] != $sede_classe) {
                continue;
            }
            
            // Se la materia richiede laboratorio, seleziona solo aule di tipo laboratorio
            if ($assegnazione['richiede_laboratorio'] && $aula['tipo'] !== 'laboratorio') {
                continue;
            }
            
            // Se la materia non richiede laboratorio, evita laboratori specializzati
            if (!$assegnazione['richiede_laboratorio'] && $aula['tipo'] === 'laboratorio') {
                continue;
            }
            
            $aule_candidate[] = $aula;
        }
        
        // Se non ci sono aule specifiche, usa tutte le aule della sede
        if (empty($aule_candidate)) {
            foreach ($this->aule as $aula) {
                if ($aula['sede_id'] == $sede_classe) {
                    $aule_candidate[] = $aula;
                }
            }
        }
        
        // Ordina per capienza ascendente (preferisci aule più piccole disponibili)
        usort($aule_candidate, function($a, $b) {
            return $a['capienza'] - $b['capienza'];
        });
        
        // Ritorna l'ID della prima aula adatta
        return !empty($aule_candidate) ? $aule_candidate[0]['id'] : null;
    }

    private function registraConflitto($assegnazione, $settimana, $motivo) {
        $stmt = $this->db->prepare("
            INSERT INTO conflitti_orario 
            (tipo, gravita, titolo, descrizione, docente_id, classe_id, data_conflitto)
            VALUES ('vincoli_incompatibili', 'error', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            "Conflitto assegnazione {$assegnazione['id']} - Settimana {$settimana}",
            "Impossibile assegnare lezione per: {$motivo} - Classe: {$assegnazione['classe_nome']}, Materia: {$assegnazione['materia_nome']}, Docente: {$assegnazione['cognome']}",
            $assegnazione['docente_id'],
            $assegnazione['classe_id'],
            date('Y-m-d')
        ]);
    }

    private function aggiornaOreRimanenti($assegnazione, $lezioni_assegnate) {
        $ore_effettuate = $lezioni_assegnate;
        $ore_rimanenti = $assegnazione['ore_annuali_previste'] - $ore_effettuate;

        $stmt = $this->db->prepare("
            UPDATE classi_materie_docenti 
            SET ore_effettuate = ore_effettuate + ?, ore_rimanenti = ?
            WHERE id = ?
        ");
        $stmt->execute([$ore_effettuate, $ore_rimanenti, $assegnazione['id']]);
    }

    private function creaBackup($anno_scolastico_id) {
        $this->log("📦 Creazione backup pre-generazione...");
        
        // Carica l'anno scolastico per ottenere date inizio/fine
        $stmt_anno = $this->db->prepare("SELECT data_inizio, data_fine FROM anni_scolastici WHERE id = ?");
        $stmt_anno->execute([$anno_scolastico_id]);
        $anno = $stmt_anno->fetch(PDO::FETCH_ASSOC);
        
        if (!$anno) {
            $this->log("⚠️ Backup non creato: anno scolastico non trovato");
            return;
        }
        
        // Seleziona TUTTE le lezioni dell'anno scolastico
        $stmt = $this->db->prepare("
            SELECT * FROM calendario_lezioni 
            WHERE data_lezione BETWEEN ? AND ?
        ");
        $stmt->execute([$anno['data_inizio'], $anno['data_fine']]);
        $lezioni_esistenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $backup_data = [
            'lezioni_esistenti' => $lezioni_esistenti,
            'timestamp' => date('Y-m-d H:i:s'),
            'anno_scolastico_id' => $anno_scolastico_id,
            'numero_lezioni' => count($lezioni_esistenti)
        ];

        $stmt = $this->db->prepare("
            INSERT INTO snapshot_calendario 
            (nome, descrizione, tipo, dati_calendario, periodo_inizio, periodo_fine, sede_id)
            VALUES (?, ?, 'pre_modifica', ?, ?, ?, NULL)
        ");
        $stmt->execute([
            "Backup pre-generazione " . date('Y-m-d H:i'),
            "Backup automatico prima della generazione calendario - " . count($lezioni_esistenti) . " lezioni",
            json_encode($backup_data),
            $anno['data_inizio'],
            $anno['data_fine']
        ]);
        
        $this->log("✅ Backup creato: " . count($lezioni_esistenti) . " lezioni salvate");
    }

    private function rilevaConflittiFinali($anno_scolastico_id) {
        $this->log("🔍 Rilevamento conflitti finali...");
        $this->conflittiDetector->detectAllConflitti($anno_scolastico_id);
        
        // Conta conflitti non risolti
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total FROM conflitti_orario 
            WHERE risolto = 0 AND data_conflitto IN (
                SELECT data_inizio FROM anni_scolastici WHERE id = ?
            )
        ");
        $stmt->execute([$anno_scolastico_id]);
        $conflitti = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $this->log("📊 Rilevati {$conflitti} conflitti non risolti");
    }

    private function log($messaggio) {
        $timestamp = date('Y-m-d H:i:s');
        $this->log[] = "[$timestamp] $messaggio";
        error_log($messaggio);
    }

    public function getLog() {
        return $this->log;
    }

    public function getStatistiche() {
        return $this->statistiche;
    }
    
    public function clearCache() {
        $this->validator->clearCache();
    }
}
?>