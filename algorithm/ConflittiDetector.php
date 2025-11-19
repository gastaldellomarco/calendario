<?php
class ConflittiDetector {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function detectAllConflitti($anno_scolastico_id) {
        $this->detectDoppiAssegnazioni($anno_scolastico_id);
        $this->detectVincoliNonRispettati($anno_scolastico_id);
        $this->detectSuperamentoOre($anno_scolastico_id);
    }

    public function detectDoppiAssegnazioni($anno_scolastico_id) {
        // Doppia assegnazione docente stesso slot
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT cl1.id as lezione1_id, cl2.id as lezione2_id,
                   cl1.docente_id, cl1.data_lezione, cl1.slot_id,
                   d.cognome, d.nome
            FROM calendario_lezioni cl1
            JOIN calendario_lezioni cl2 ON 
                cl1.docente_id = cl2.docente_id AND 
                cl1.data_lezione = cl2.data_lezione AND 
                cl1.slot_id = cl2.slot_id AND 
                cl1.id != cl2.id
            JOIN docenti d ON cl1.docente_id = d.id
            WHERE cl1.data_lezione IN (
                SELECT data_inizio FROM anni_scolastici WHERE id = ?
            )
            GROUP BY cl1.docente_id, cl1.data_lezione, cl1.slot_id
        ");
        $stmt->execute([$anno_scolastico_id]);
        $conflitti = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($conflitti as $conflitto) {
            $this->registraConflitto([
                'tipo' => 'doppia_assegnazione_docente',
                'gravita' => 'error',
                'titolo' => "Doppia assegnazione docente: {$conflitto['cognome']} {$conflitto['nome']}",
                'descrizione' => "Il docente è assegnato a due lezioni nello stesso slot orario",
                'docente_id' => $conflitto['docente_id'],
                'lezione_id' => $conflitto['lezione1_id'],
                'data_conflitto' => $conflitto['data_lezione'],
                'slot_id' => $conflitto['slot_id']
            ]);
        }

        // Doppia assegnazione aula stesso slot
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT cl1.id as lezione1_id, cl2.id as lezione2_id,
                   cl1.aula_id, cl1.data_lezione, cl1.slot_id,
                   a.nome as aula_nome
            FROM calendario_lezioni cl1
            JOIN calendario_lezioni cl2 ON 
                cl1.aula_id = cl2.aula_id AND 
                cl1.data_lezione = cl2.data_lezione AND 
                cl1.slot_id = cl2.slot_id AND 
                cl1.id != cl2.id
            JOIN aule a ON cl1.aula_id = a.id
            WHERE cl1.aula_id IS NOT NULL AND 
                  cl1.data_lezione IN (
                    SELECT data_inizio FROM anni_scolastici WHERE id = ?
                  )
            GROUP BY cl1.aula_id, cl1.data_lezione, cl1.slot_id
        ");
        $stmt->execute([$anno_scolastico_id]);
        $conflitti_aula = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($conflitti_aula as $conflitto) {
            $this->registraConflitto([
                'tipo' => 'doppia_aula',
                'gravita' => 'error',
                'titolo' => "Doppia assegnazione aula: {$conflitto['aula_nome']}",
                'descrizione' => "L'aula è assegnata a two lezioni nello stesso slot orario",
                'aula_id' => $conflitto['aula_id'],
                'lezione_id' => $conflitto['lezione1_id'],
                'data_conflitto' => $conflitto['data_lezione'],
                'slot_id' => $conflitto['slot_id']
            ]);
        }
    }

    public function detectVincoliNonRispettati($anno_scolastico_id) {
        // Controlla vincoli docenti non rispettati
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT vd.*, d.cognome, d.nome, cl.data_lezione, cl.slot_id
            FROM vincoli_docenti vd
            JOIN docenti d ON vd.docente_id = d.id
            JOIN calendario_lezioni cl ON 
                vd.docente_id = cl.docente_id AND 
                DAYOFWEEK(cl.data_lezione) = vd.giorno_settimana
            WHERE vd.attivo = 1 AND 
                  cl.data_lezione IN (
                    SELECT data_inizio FROM anni_scolastici WHERE id = ?
                  )
        ");
        $stmt->execute([$anno_scolastico_id]);
        $vincoli_violati = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vincoli_violati as $violazione) {
            $this->registraConflitto([
                'tipo' => 'vincolo_docente',
                'gravita' => 'error',
                'titolo' => "Vincolo docente violato: {$violazione['cognome']} {$violazione['nome']}",
                'descrizione' => "Il docente ha una lezione in un periodo di indisponibilità",
                'docente_id' => $violazione['docente_id'],
                'lezione_id' => $violazione['id'],
                'data_conflitto' => $violazione['data_lezione'],
                'slot_id' => $violazione['slot_id']
            ]);
        }
    }

    public function detectSuperamentoOre($anno_scolastico_id) {
        // Superamento ore giornaliere docenti
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT cl.docente_id, cl.data_lezione, 
                   COUNT(*) as ore_giorno, d.max_ore_giorno,
                   d.cognome, d.nome
            FROM calendario_lezioni cl
            JOIN docenti d ON cl.docente_id = d.id
            WHERE cl.data_lezione IN (
                SELECT data_inizio FROM anni_scolastici WHERE id = ?
            )
            GROUP BY cl.docente_id, cl.data_lezione
            HAVING ore_giorno > d.max_ore_giorno
        ");
        $stmt->execute([$anno_scolastico_id]);
        $superamento_ore = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($superamento_ore as $sovra) {
            $this->registraConflitto([
                'tipo' => 'superamento_ore',
                'gravita' => 'warning',
                'titolo' => "Superamento ore giornaliere: {$sovra['cognome']} {$sovra['nome']}",
                'descrizione' => "Il docente ha {$sovra['ore_giorno']} ore il {$sovra['data_lezione']} (max: {$sovra['max_ore_giorno']})",
                'docente_id' => $sovra['docente_id'],
                'data_conflitto' => $sovra['data_lezione']
            ]);
        }
    }

    private function registraConflitto($dati) {
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            INSERT INTO conflitti_orario 
            (tipo, gravita, titolo, descrizione, dati_conflitto, 
             docente_id, classe_id, aula_id, lezione_id, data_conflitto, slot_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $dati['tipo'],
            $dati['gravita'],
            $dati['titolo'],
            $dati['descrizione'],
            json_encode($dati),
            $dati['docente_id'] ?? null,
            $dati['classe_id'] ?? null,
            $dati['aula_id'] ?? null,
            $dati['lezione_id'] ?? null,
            $dati['data_conflitto'],
            $dati['slot_id'] ?? null
        ]);
    }

    public function getConflittiNonRisolti($anno_scolastico_id) {
        // ✅ CORREZIONE: Usa prepare() invece di query() con parametri
        $stmt = $this->db->prepare("
            SELECT * FROM conflitti_orario 
            WHERE risolto = 0 AND data_conflitto IN (
                SELECT data_inizio FROM anni_scolastici WHERE id = ?
            )
            ORDER BY gravita DESC, data_conflitto
        ");
        $stmt->execute([$anno_scolastico_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>