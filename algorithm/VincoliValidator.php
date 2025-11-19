<?php
class VincoliValidator {
    private $db;
    private $cache = [];

    public function __construct($db) {
        $this->db = $db;
    }

    public function isDocenteDisponibile($docente_id, $data, $slot_id) {
        $cache_key = "docente_{$docente_id}_{$data}_{$slot_id}";
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        // Controlla vincoli temporali
        $giorno_settimana = date('N', strtotime($data));
        
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT * FROM vincoli_docenti 
            WHERE docente_id = ? AND giorno_settimana = ? AND attivo = 1
        ");
        $stmt->execute([$docente_id, $giorno_settimana]);
        $vincoli = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vincoli as $vincolo) {
            if ($this->slotInVincolo($slot_id, $vincolo)) {
                $this->cache[$cache_key] = false;
                return false;
            }
        }

        // Controlla se già assegnato in altro slot stesso giorno
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM calendario_lezioni 
            WHERE docente_id = ? AND data_lezione = ? AND slot_id = ?
        ");
        $stmt->execute([$docente_id, $data, $slot_id]);
        $gia_assegnato = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($gia_assegnato > 0) {
            $this->cache[$cache_key] = false;
            return false;
        }

        // Controlla max ore giornaliere
        if (!$this->checkMaxOreGiorno($docente_id, $data)) {
            $this->cache[$cache_key] = false;
            return false;
        }

        $this->cache[$cache_key] = true;
        return true;
    }

    public function isClasseDisponibile($classe_id, $data, $slot_id) {
        $cache_key = "classe_{$classe_id}_{$data}_{$slot_id}";
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        // Controlla vincoli classe
        $giorno_settimana = date('N', strtotime($data));
        
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT * FROM vincoli_classi 
            WHERE classe_id = ? AND giorno_settimana = ? AND attivo = 1
        ");
        $stmt->execute([$classe_id, $giorno_settimana]);
        $vincoli = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vincoli as $vincolo) {
            if ($vincolo['tipo'] == 'no_lezioni') {
                $this->cache[$cache_key] = false;
                return false;
            }
        }

        // Controlla se già ha lezione in questo slot
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM calendario_lezioni 
            WHERE classe_id = ? AND data_lezione = ? AND slot_id = ?
        ");
        $stmt->execute([$classe_id, $data, $slot_id]);
        $gia_assegnato = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $this->cache[$cache_key] = ($gia_assegnato == 0);
        return ($gia_assegnato == 0);
    }

    public function isAulaDisponibile($aula_id, $data, $slot_id) {
        if ($aula_id === null) return true; // Aula non specificata

        $cache_key = "aula_{$aula_id}_{$data}_{$slot_id}";
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM calendario_lezioni 
            WHERE aula_id = ? AND data_lezione = ? AND slot_id = ?
        ");
        $stmt->execute([$aula_id, $data, $slot_id]);
        $gia_assegnata = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $this->cache[$cache_key] = ($gia_assegnata == 0);
        return ($gia_assegnata == 0);
    }

    public function checkMaxOreGiorno($docente_id, $data) {
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as ore FROM calendario_lezioni 
            WHERE docente_id = ? AND data_lezione = ?
        ");
        $stmt->execute([$docente_id, $data]);
        $ore_oggi = $stmt->fetch(PDO::FETCH_ASSOC)['ore'];

        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT max_ore_giorno FROM docenti WHERE id = ?
        ");
        $stmt->execute([$docente_id]);
        $max_ore_giorno = $stmt->fetch(PDO::FETCH_ASSOC)['max_ore_giorno'];

        return $ore_oggi < $max_ore_giorno;
    }

    public function checkMaxOreSettimana($docente_id, $data_riferimento) {
        $inizio_settimana = date('Y-m-d', strtotime('monday this week', strtotime($data_riferimento)));
        $fine_settimana = date('Y-m-d', strtotime('sunday this week', strtotime($data_riferimento)));

        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as ore FROM calendario_lezioni 
            WHERE docente_id = ? AND data_lezione BETWEEN ? AND ?
        ");
        $stmt->execute([$docente_id, $inizio_settimana, $fine_settimana]);
        $ore_settimana = $stmt->fetch(PDO::FETCH_ASSOC)['ore'];

        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT max_ore_settimana FROM docenti WHERE id = ?
        ");
        $stmt->execute([$docente_id]);
        $max_ore_settimana = $stmt->fetch(PDO::FETCH_ASSOC)['max_ore_settimana'];

        return $ore_settimana < $max_ore_settimana;
    }

    public function isGiornoLavorativo($data) {
        $giorno_settimana = date('N', strtotime($data));
        if ($giorno_settimana == 7) return false; // Domenica

        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM giorni_chiusura 
            WHERE data_chiusura = ?
        ");
        $stmt->execute([$data]);
        $chiusura = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return $chiusura == 0;
    }

    private function slotInVincolo($slot_id, $vincolo) {
        // Implementa la logica per verificare se lo slot rientra nel periodo del vincolo
        // Considera ora_inizio e ora_fine del vincolo
        
        if (!isset($vincolo['ora_inizio']) || !isset($vincolo['ora_fine'])) {
            return false;
        }
        
        // Carica informazioni sullo slot
        $stmt = $this->db->prepare("SELECT * FROM orari_slot WHERE id = ?");
        $stmt->execute([$slot_id]);
        $slot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$slot) {
            return false;
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

    public function clearCache() {
        $this->cache = [];
    }
}
?>