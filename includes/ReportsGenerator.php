<?php
require_once 'tcpdf/tcpdf.php';

/**
 * Generatore report PDF per docenti, classi e l'intero istituto
 *
 * Usa TCPDF per generare report in formato PDF. Le funzioni pubbliche ritorneranno
 * il path del file PDF generato.
 */
class ReportsGenerator {
    private $db;
    private $pdf;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initPDF();
    }
    
    private function initPDF() {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Impostazioni documento
        $this->pdf->SetCreator('School Calendar System');
        $this->pdf->SetAuthor('School Calendar System');
        $this->pdf->SetTitle('Report Scuola');
        $this->pdf->SetSubject('Report Statistiche');
        $this->pdf->SetKeywords('scuola, report, statistiche');
        
        // Impostazioni header e footer
        $this->pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $this->pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->pdf->SetMargins(15, 25, 15);
        $this->pdf->SetHeaderMargin(10);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(TRUE, 25);
        
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $this->pdf->SetFont('helvetica', '', 10);
    }
    
    /**
     * Genera un report PDF per un docente specifico
     *
     * @param int $docente_id ID del docente
     * @param array|null $periodo Array con chiavi data_inizio e data_fine (Y-m-d)
     * @return string Path del file PDF generato
     */
    public function generaReportDocente($docente_id, $periodo = null) {
        $this->pdf->AddPage();
        
        // Header
        $this->addHeader('REPORT DOCENTE');
        
        // Dati docente
        $docente = $this->getDatiDocente($docente_id);
        $statistiche = $this->getStatisticheDocente($docente_id, $periodo);
        
        // Contenuto
        $html = $this->getHTMLReportDocente($docente, $statistiche);
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->salvaPDF("report_docente_{$docente_id}.pdf");
    }
    
    /**
     * Genera un report PDF per una classe
     *
     * @param int $classe_id
     * @param array|null $periodo
     * @return string Path del file PDF generato
     */
    public function generaReportClasse($classe_id, $periodo = null) {
        $this->pdf->AddPage();
        
        // Header
        $this->addHeader('REPORT CLASSE');
        
        // Dati classe
        $classe = $this->getDatiClasse($classe_id);
        $statistiche = $this->getStatisticheClasse($classe_id, $periodo);
        
        // Contenuto
        $html = $this->getHTMLReportClasse($classe, $statistiche);
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->salvaPDF("report_classe_{$classe_id}.pdf");
    }
    
    /**
     * Genera un report PDF per una sede
     *
     * @param int $sede_id
     * @param array|null $periodo
     * @return string Path del file PDF generato
     */
    public function generaReportSede($sede_id, $periodo = null) {
        $this->pdf->AddPage();
        
        // Header
        $this->addHeader('REPORT SEDE');
        
        // Dati sede
        $sede = $this->getDatiSede($sede_id);
        $statistiche = $this->getStatisticheSede($sede_id, $periodo);
        
        // Contenuto
        $html = $this->getHTMLReportSede($sede, $statistiche);
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->salvaPDF("report_sede_{$sede_id}.pdf");
    }
    
    /**
     * Genera un report PDF completo per l'anno scolastico
     *
     * @param int $anno_scolastico_id
     * @return string Path del file PDF generato
     */
    public function generaReportCompleto($anno_scolastico_id) {
        $this->pdf->AddPage();
        
        // Header
        $this->addHeader('REPORT COMPLETO SCUOLA');
        
        // Dati completi
        $statistiche = $this->getStatisticheComplete($anno_scolastico_id);
        
        // Contenuto
        $html = $this->getHTMLReportCompleto($statistiche);
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this->salvaPDF("report_completo_{$anno_scolastico_id}.pdf");
    }
    
    /**
     * Aggiunge l'header di pagina per i PDF
     *
     * @param string $titolo Titolo da mostrare nell'header
     */
    private function addHeader($titolo) {
        $html = '
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
            <h1 style="color: #2c3e50; font-size: 24px; margin: 0;">' . $titolo . '</h1>
            <p style="color: #7f8c8d; font-size: 12px; margin: 5px 0;">
                Scuola Calendar Management System - ' . date('d/m/Y H:i') . '
            </p>
        </div>
        ';
        $this->pdf->writeHTML($html, true, false, true, false, '');
    }
    
    /**
     * Recupera le informazioni di anagrafica del docente
     *
     * @param int $docente_id
     * @return array Associative array con campi docente
     */
    private function getDatiDocente($docente_id) {
        $sql = "
            SELECT d.*, s.nome as sede_nome 
            FROM docenti d 
            LEFT JOIN sedi s ON d.sede_principale_id = s.id 
            WHERE d.id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$docente_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Recupera le statistiche del docente nel periodo indicato
     *
     * @param int $docente_id
     * @param array|null $periodo
     * @return array Statistiche (lezioni_totali, lezioni_svolte, etc.)
     */
    private function getStatisticheDocente($docente_id, $periodo) {
        $where_periodo = "";
        $params = [$docente_id];
        
        if ($periodo) {
            $where_periodo = "AND cl.data_lezione BETWEEN ? AND ?";
            $params[] = $periodo['data_inizio'];
            $params[] = $periodo['data_fine'];
        }
        
        $sql = "
            SELECT 
                COUNT(*) as lezioni_totali,
                SUM(CASE WHEN cl.stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte,
                SUM(CASE WHEN cl.stato = 'cancellata' THEN 1 ELSE 0 END) as lezioni_cancellate,
                COUNT(DISTINCT cl.classe_id) as classi_seguite,
                COUNT(DISTINCT cl.materia_id) as materie_insegnate,
                AVG(cl.ore_effettive) as ore_media_lezione,
                SUM(cl.ore_effettive) as ore_totali_effettuate
            FROM calendario_lezioni cl
            WHERE cl.docente_id = ? $where_periodo
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $statistiche = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Carico lavoro vs contratto
        $sql_contratto = "SELECT ore_settimanali_contratto FROM docenti WHERE id = ?";
        $stmt = $this->db->prepare($sql_contratto);
        $stmt->execute([$docente_id]);
        $contratto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $statistiche['ore_contratto_settimanali'] = $contratto['ore_settimanali_contratto'];
        $statistiche['percentuale_carico'] = $contratto['ore_settimanali_contratto'] > 0 ? 
            round(($statistiche['ore_totali_effettuate'] / $contratto['ore_settimanali_contratto']) * 100, 1) : 0;
        
        return $statistiche;
    }
    
    /**
     * Costruisce l'HTML del report per il docente
     *
     * @param array $docente
     * @param array $statistiche
     * @return string HTML per il PDF
     */
    private function getHTMLReportDocente($docente, $statistiche) {
        $html = '
        <style>
            .section { margin-bottom: 20px; }
            .section-title { background-color: #f8f9fa; padding: 10px; font-weight: bold; border-left: 4px solid #3498db; }
            .stats-grid { display: table; width: 100%; border-collapse: collapse; }
            .stat-item { display: table-cell; padding: 10px; text-align: center; border: 1px solid #ddd; }
            .stat-value { font-size: 18px; font-weight: bold; color: #2c3e50; }
            .stat-label { font-size: 11px; color: #7f8c8d; }
            .table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            .table th { background-color: #34495e; color: white; padding: 8px; text-align: left; }
            .table td { padding: 8px; border: 1px solid #ddd; }
            .warning { color: #e74c3c; font-weight: bold; }
            .success { color: #27ae60; font-weight: bold; }
        </style>
        
        <div class="section">
            <div class="section-title">INFORMAZIONI DOCENTE</div>
            <table class="table">
                <tr>
                    <td><strong>Nome:</strong> ' . htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']) . '</td>
                    <td><strong>Sede:</strong> ' . htmlspecialchars($docente['sede_nome']) . '</td>
                </tr>
                <tr>
                    <td><strong>Email:</strong> ' . htmlspecialchars($docente['email'] ?? 'N/A') . '</td>
                    <td><strong>Telefono:</strong> ' . htmlspecialchars($docente['telefono'] ?? 'N/A') . '</td>
                </tr>
                <tr>
                    <td><strong>Ore Contratto:</strong> ' . $docente['ore_settimanali_contratto'] . ' ore/settimana</td>
                    <td><strong>Stato:</strong> ' . strtoupper($docente['stato']) . '</td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">STATISTICHE PERFORMANCE</div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['lezioni_totali'] . '</div>
                    <div class="stat-label">LEZIONI TOTALI</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['lezioni_svolte'] . '</div>
                    <div class="stat-label">LEZIONI SVOLTE</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['classi_seguite'] . '</div>
                    <div class="stat-label">CLASSI SEGUITE</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['materie_insegnate'] . '</div>
                    <div class="stat-label">MATERIE INSEGNATE</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">CARICO DI LAVORO</div>
            <table class="table">
                <tr>
                    <th>Metrica</th>
                    <th>Valore</th>
                    <th>Stato</th>
                </tr>
                <tr>
                    <td>Ore Contratto Settimanali</td>
                    <td>' . $statistiche['ore_contratto_settimanali'] . ' ore</td>
                    <td>Contrattuale</td>
                </tr>
                <tr>
                    <td>Ore Totali Effettuate</td>
                    <td>' . round($statistiche['ore_totali_effettuate'], 1) . ' ore</td>
                    <td>Effettive</td>
                </tr>
                <tr>
                    <td>Percentuale Carico</td>
                    <td>' . $statistiche['percentuale_carico'] . '%</td>
                    <td class="' . ($statistiche['percentuale_carico'] > 110 ? 'warning' : 'success') . '">
                        ' . ($statistiche['percentuale_carico'] > 110 ? 'SOVRACCARICO' : 'NORMALE') . '
                    </td>
                </tr>
                <tr>
                    <td>Ore Media per Lezione</td>
                    <td>' . round($statistiche['ore_media_lezione'], 2) . ' ore</td>
                    <td>' . ($statistiche['ore_media_lezione'] > 1.2 ? 'ALTA' : 'STANDARD') . '</td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">LEZIONI CANCELLATE</div>
            <p>Totale lezioni cancellate: <strong>' . $statistiche['lezioni_cancellate'] . '</strong></p>
            <p>Tasso di successo: <strong>' . 
                round(($statistiche['lezioni_svolte'] / $statistiche['lezioni_totali']) * 100, 1) . '%</strong></p>
        </div>
        ';
        
        return $html;
    }
    
    private function getDatiClasse($classe_id) {
        $sql = "
            SELECT c.*, p.nome as percorso_nome, s.nome as sede_nome, a.nome as aula_nome
            FROM classi c
            LEFT JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
            LEFT JOIN sedi s ON c.sede_id = s.id
            LEFT JOIN aule a ON c.aula_preferenziale_id = a.id
            WHERE c.id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classe_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getStatisticheClasse($classe_id, $periodo) {
        $where_periodo = "";
        $params = [$classe_id];
        
        if ($periodo) {
            $where_periodo = "AND cl.data_lezione BETWEEN ? AND ?";
            $params[] = $periodo['data_inizio'];
            $params[] = $periodo['data_fine'];
        }
        
        $sql = "
            SELECT 
                COUNT(*) as lezioni_totali,
                SUM(CASE WHEN cl.stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte,
                SUM(CASE WHEN cl.stato = 'cancellata' THEN 1 ELSE 0 END) as lezioni_cancellate,
                COUNT(DISTINCT cl.docente_id) as docenti_assegnati,
                COUNT(DISTINCT cl.materia_id) as materie_attive,
                AVG(cl.ore_effettive) as ore_media_lezione,
                SUM(cl.ore_effettive) as ore_totali_effettuate
            FROM calendario_lezioni cl
            WHERE cl.classe_id = ? $where_periodo
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getHTMLReportClasse($classe, $statistiche) {
        $html = '
        <div class="section">
            <div class="section-title">INFORMAZIONI CLASSE</div>
            <table class="table">
                <tr>
                    <td><strong>Classe:</strong> ' . htmlspecialchars($classe['nome']) . '</td>
                    <td><strong>Percorso:</strong> ' . htmlspecialchars($classe['percorso_nome']) . '</td>
                </tr>
                <tr>
                    <td><strong>Sede:</strong> ' . htmlspecialchars($classe['sede_nome']) . '</td>
                    <td><strong>Aula Preferenziale:</strong> ' . htmlspecialchars($classe['aula_nome'] ?? 'N/A') . '</td>
                </tr>
                <tr>
                    <td><strong>Anno Corso:</strong> ' . $classe['anno_corso'] . '</td>
                    <td><strong>Numero Studenti:</strong> ' . $classe['numero_studenti'] . '</td>
                </tr>
                <tr>
                    <td><strong>Ore Settimanali Previste:</strong> ' . $classe['ore_settimanali_previste'] . '</td>
                    <td><strong>Stato:</strong> ' . strtoupper($classe['stato']) . '</td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">ATTIVITÀ DIDATTICA</div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['lezioni_totali'] . '</div>
                    <div class="stat-label">LEZIONI TOTALI</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['lezioni_svolte'] . '</div>
                    <div class="stat-label">LEZIONI SVOLTE</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['docenti_assegnati'] . '</div>
                    <div class="stat-label">DOCENTI</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['materie_attive'] . '</div>
                    <div class="stat-label">MATERIE</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">ORE E FREQUENZA</div>
            <table class="table">
                <tr>
                    <th>Metrica</th>
                    <th>Valore</th>
                    <th>Note</th>
                </tr>
                <tr>
                    <td>Ore Totali Effettuate</td>
                    <td>' . round($statistiche['ore_totali_effettuate'], 1) . ' ore</td>
                    <td>Periodo selezionato</td>
                </tr>
                <tr>
                    <td>Ore Media per Lezione</td>
                    <td>' . round($statistiche['ore_media_lezione'], 2) . ' ore</td>
                    <td>Durata media</td>
                </tr>
                <tr>
                    <td>Tasso di Successo</td>
                    <td>' . round(($statistiche['lezioni_svolte'] / $statistiche['lezioni_totali']) * 100, 1) . '%</td>
                    <td>Lezioni svolte/totali</td>
                </tr>
                <tr>
                    <td>Lezioni Cancellate</td>
                    <td>' . $statistiche['lezioni_cancellate'] . '</td>
                    <td>Da recuperare</td>
                </tr>
            </table>
        </div>
        ';
        
        return $html;
    }
    
    private function getStatisticheComplete($anno_scolastico_id) {
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM docenti WHERE stato = 'attivo') as docenti_totali,
                (SELECT COUNT(*) FROM classi WHERE stato = 'attiva' AND anno_scolastico_id = ?) as classi_totali,
                (SELECT COUNT(*) FROM aule WHERE attiva = 1) as aule_totali,
                (SELECT COUNT(*) FROM calendario_lezioni cl 
                 WHERE EXISTS (SELECT 1 FROM classi c WHERE c.id = cl.classe_id AND c.anno_scolastico_id = ?)) as lezioni_totali,
                (SELECT COUNT(*) FROM calendario_lezioni cl 
                 WHERE cl.stato = 'svolta' AND EXISTS (SELECT 1 FROM classi c WHERE c.id = cl.classe_id AND c.anno_scolastico_id = ?)) as lezioni_svolte,
                (SELECT COUNT(*) FROM conflitti_orario WHERE risolto = 0) as conflitti_aperti
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$anno_scolastico_id, $anno_scolastico_id, $anno_scolastico_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getHTMLReportCompleto($statistiche) {
        $tasso_successo = $statistiche['lezioni_totali'] > 0 ? 
            round(($statistiche['lezioni_svolte'] / $statistiche['lezioni_totali']) * 100, 1) : 0;
            
        $html = '
        <div class="section">
            <div class="section-title">PANORAMICA GENERALE</div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['docenti_totali'] . '</div>
                    <div class="stat-label">DOCENTI ATTIVI</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['classi_totali'] . '</div>
                    <div class="stat-label">CLASSI ATTIVE</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['aule_totali'] . '</div>
                    <div class="stat-label">AULE DISPONIBILI</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['lezioni_totali'] . '</div>
                    <div class="stat-label">LEZIONI TOTALI</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">INDICATORI DI PERFORMANCE</div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['lezioni_svolte'] . '</div>
                    <div class="stat-label">LEZIONI SVOLTE</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $tasso_successo . '%</div>
                    <div class="stat-label">TASSO SUCCESSO</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . $statistiche['conflitti_aperti'] . '</div>
                    <div class="stat-label">CONFLITTI APERTI</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">' . ($statistiche['lezioni_totali'] - $statistiche['lezioni_svolte']) . '</div>
                    <div class="stat-label">LEZIONI DA RECUPERARE</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">ANALISI E RACCOMANDAZIONI</div>
            <table class="table">
                <tr>
                    <th>Area</th>
                    <th>Stato</th>
                    <th>Raccomandazione</th>
                </tr>
                <tr>
                    <td>Performance Lezioni</td>
                    <td class="' . ($tasso_successo > 90 ? 'success' : 'warning') . '">
                        ' . ($tasso_successo > 90 ? 'OTTIMO' : 'DA MIGLIORARE') . '
                    </td>
                    <td>' . ($tasso_successo > 90 ? 
                        'Mantenere il livello attuale' : 
                        'Analizzare cause cancellazioni lezioni') . '</td>
                </tr>
                <tr>
                    <td>Gestione Conflitti</td>
                    <td class="' . ($statistiche['conflitti_aperti'] == 0 ? 'success' : 'warning') . '">
                        ' . ($statistiche['conflitti_aperti'] == 0 ? 'OTTIMO' : 'ATTENZIONE') . '
                    </td>
                    <td>' . ($statistiche['conflitti_aperti'] == 0 ? 
                        'Nessun conflitto da risolvere' : 
                        'Risolvere i conflitti pendenti') . '</td>
                </tr>
                <tr>
                    <td>Risorse Docenti</td>
                    <td class="success">SUFFICIENTE</td>
                    <td>Rapporto docenti/classi: ' . 
                        round($statistiche['docenti_totali'] / max($statistiche['classi_totali'], 1), 1) . '</td>
                </tr>
                <tr>
                    <td>Infrastruttura</td>
                    <td class="success">ADEGUATA</td>
                    <td>Rapporto aule/classi: ' . 
                        round($statistiche['aule_totali'] / max($statistiche['classi_totali'], 1), 1) . '</td>
                </tr>
            </table>
        </div>
        ';
        
        return $html;
    }
    
    private function salvaPDF($filename) {
        $path = '../temp/' . $filename;
        $this->pdf->Output($path, 'F');
        return $path;
    }
}
?>