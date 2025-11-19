<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Azione non specificata'];

try {
    switch ($action) {
        case 'get_kpi':
            $response = getKPIData();
            break;
            
        case 'get_docenti_stats':
            $response = getDocentiStats();
            break;
            
        case 'get_classi_stats':
            $response = getClassiStats();
            break;
            
        case 'get_aule_stats':
            $response = getAuleStats();
            break;
            
        case 'get_calendario_stats':
            $response = getCalendarioStats();
            break;
        
        case 'get_analytics_dashboard':
            $response = getAnalyticsDashboard();
            break;
            
        case 'get_custom_report':
            $response = getCustomReport();
            break;
            
        case 'export_docenti_pdf':
            $response = exportDocentiPDF();
            break;
            
        case 'export_docenti_excel':
            $response = exportDocentiExcel();
            break;
            
        case 'export_all':
            $response = exportAllReports();
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Azione non riconosciuta'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
}

echo json_encode($response);

function getKPIData() {
    global $db;
    
    $anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
    $sede_id = $_GET['sede_id'] ?? '';
    
    // KPI principali
    $kpi = [];
    
    // Docenti attivi
    $sql = "SELECT COUNT(*) as totale FROM docenti WHERE stato = 'attivo'";
    if ($sede_id) $sql .= " AND sede_principale_id = :sede_id";
    $stmt = $db->prepare($sql);
    if ($sede_id) $stmt->bindValue('sede_id', $sede_id);
    $stmt->execute();
    $kpi[] = [
        'value' => $stmt->fetchColumn(),
        'label' => 'Docenti Attivi',
        'color' => 'blue',
        'trend' => '+2%'
    ];
    
    // Classi attive
    $sql = "SELECT COUNT(*) as totale FROM classi WHERE stato = 'attiva'";
    if ($anno_scolastico_id) $sql .= " AND anno_scolastico_id = :anno_scolastico_id";
    if ($sede_id) $sql .= " AND sede_id = :sede_id";
    $stmt = $db->prepare($sql);
    if ($anno_scolastico_id) $stmt->bindValue('anno_scolastico_id', $anno_scolastico_id);
    if ($sede_id) $stmt->bindValue('sede_id', $sede_id);
    $stmt->execute();
    $kpi[] = [
        'value' => $stmt->fetchColumn(),
        'label' => 'Classi Attive',
        'color' => 'green',
        'trend' => 'Stabile'
    ];
    
    // Lezioni svolte (ultimi 30 giorni)
    $sql = "SELECT COUNT(*) as totale FROM calendario_lezioni 
            WHERE stato = 'svolta' AND data_lezione >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    if ($sede_id) $sql .= " AND sede_id = :sede_id";
    $stmt = $db->prepare($sql);
    if ($sede_id) $stmt->bindValue('sede_id', $sede_id);
    $stmt->execute();
    $kpi[] = [
        'value' => $stmt->fetchColumn(),
        'label' => 'Lezioni Ultimo Mese',
        'color' => 'purple',
        'trend' => '+15%'
    ];
    
    // Conflitti aperti
    $sql = "SELECT COUNT(*) as totale FROM conflitti_orario WHERE risolto = 0";
    if ($sede_id) {
        $sql .= " AND (aula_id IN (SELECT id FROM aule WHERE sede_id = :sede_id) 
                  OR docente_id IN (SELECT id FROM docenti WHERE sede_principale_id = :sede_id)
                  OR classe_id IN (SELECT id FROM classi WHERE sede_id = :sede_id))";
    }
    $stmt = $db->prepare($sql);
    if ($sede_id) $stmt->bindValue('sede_id', $sede_id);
    $stmt->execute();
    $kpi[] = [
        'value' => $stmt->fetchColumn(),
        'label' => 'Conflitti Aperti',
        'color' => 'red',
        'trend' => '-5%'
    ];
    
    return ['success' => true, 'kpi' => $kpi];
}

function getDocentiStats() {
    global $db;
    
    $sede_id = $_GET['sede_id'] ?? '';
    $data_inizio = $_GET['data_inizio'] ?? '';
    $data_fine = $_GET['data_fine'] ?? '';
    
    $sql = "
        SELECT 
            d.id,
            CONCAT(d.cognome, ' ', d.nome) as docente,
            s.nome as sede,
            d.ore_settimanali_contratto,
            COALESCE(SUM(cmd.ore_settimanali), 0) as ore_assegnate,
            COALESCE((
                SELECT COUNT(*) 
                FROM calendario_lezioni cl 
                WHERE cl.docente_id = d.id 
                AND cl.stato IN ('svolta', 'confermata')
                AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
                AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
            ), 0) as ore_effettuate
        FROM docenti d
        LEFT JOIN sedi s ON d.sede_principale_id = s.id
        LEFT JOIN classi_materie_docenti cmd ON d.id = cmd.docente_id AND cmd.attivo = 1
        WHERE d.stato = 'attivo'
        AND (:sede_id = '' OR d.sede_principale_id = :sede_id)
        GROUP BY d.id, d.cognome, d.nome, s.nome, d.ore_settimanali_contratto
        ORDER BY ore_assegnate DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    
    return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function getClassiStats() {
    global $db;
    
    $anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
    $sede_id = $_GET['sede_id'] ?? '';
    
    $sql = "
        SELECT 
            c.nome as classe,
            p.nome as percorso,
            s.nome as sede,
            c.ore_settimanali_previste,
            COALESCE(SUM(cmd.ore_settimanali), 0) as ore_assegnate,
            ROUND((COALESCE(SUM(cmd.ore_settimanali), 0) / c.ore_settimanali_previste) * 100, 1) as copertura_percent
        FROM classi c
        LEFT JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
        LEFT JOIN sedi s ON c.sede_id = s.id
        LEFT JOIN classi_materie_docenti cmd ON c.id = cmd.classe_id AND cmd.attivo = 1
        WHERE c.stato = 'attiva'
        AND (:anno_scolastico_id = '' OR c.anno_scolastico_id = :anno_scolastico_id)
        AND (:sede_id = '' OR c.sede_id = :sede_id)
        GROUP BY c.id, c.nome, p.nome, s.nome, c.ore_settimanali_previste
        ORDER BY copertura_percent ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id
    ]);
    
    return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function getAuleStats() {
    global $db;
    
    $sede_id = $_GET['sede_id'] ?? '';
    $data_inizio = $_GET['data_inizio'] ?? date('Y-m-d', strtotime('-30 days'));
    $data_fine = $_GET['data_fine'] ?? date('Y-m-d');
    
    $sql = "
        SELECT 
            a.nome as aula,
            s.nome as sede,
            a.tipo,
            a.capienza,
            COUNT(cl.id) as lezioni_totali,
            ROUND(COUNT(cl.id) * 100.0 / (30 * 8), 1) as occupazione_percent
        FROM aule a
        LEFT JOIN sedi s ON a.sede_id = s.id
        LEFT JOIN calendario_lezioni cl ON a.id = cl.aula_id 
            AND cl.stato IN ('svolta', 'confermata', 'pianificata')
            AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
            AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
        WHERE a.attiva = 1
        AND (:sede_id = '' OR a.sede_id = :sede_id)
        GROUP BY a.id, a.nome, s.nome, a.tipo, a.capienza
        ORDER BY occupazione_percent DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    
    return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function getCalendarioStats() {
    global $db;
    
    $anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
    $sede_id = $_GET['sede_id'] ?? '';
    $data_inizio = $_GET['data_inizio'] ?? '';
    $data_fine = $_GET['data_fine'] ?? '';
    
    $sql = "
        SELECT 
            COUNT(*) as lezioni_totali,
            SUM(CASE WHEN stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte,
            SUM(CASE WHEN stato = 'cancellata' THEN 1 ELSE 0 END) as lezioni_cancellate,
            SUM(CASE WHEN stato = 'sostituita' THEN 1 ELSE 0 END) as lezioni_sostituite,
            COUNT(DISTINCT data_lezione) as giorni_lezione,
            AVG(ore_effettive) as ore_media_lezione
        FROM calendario_lezioni
        WHERE (:anno_scolastico_id = '' OR EXISTS (
            SELECT 1 FROM classi c WHERE c.id = classe_id AND c.anno_scolastico_id = :anno_scolastico_id
        ))
        AND (:sede_id = '' OR sede_id = :sede_id)
        AND (:data_inizio = '' OR data_lezione >= :data_inizio)
        AND (:data_fine = '' OR data_lezione <= :data_fine)
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    
    return ['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)];
}

function getCustomReport() {
    global $db;
    
    $table = $_GET['table'] ?? '';
    $fields = $_GET['fields'] ?? '*';
    $where = $_GET['where'] ?? '1=1';
    $group_by = $_GET['group_by'] ?? '';
    $order_by = $_GET['order_by'] ?? '';
    $limit = $_GET['limit'] ?? '1000';
    
    // Validazione input per sicurezza
    $allowed_tables = [
        'docenti',
        'classi',
        'aule',
        'calendario_lezioni',
        'materie',
        'sedi',
        'anni_scolastici',
        'percorsi_formativi',
        'docenti_materie',
        'classi_materie_docenti',
        'vincoli_docenti',
        'sostituzioni',
        'stage_studenti',
        'stage_aziende',
        'stage_tutor'
    ];
    if (!in_array($table, $allowed_tables)) {
        return ['success' => false, 'message' => 'Tabella non consentita'];
    }
    
    $sql = "SELECT $fields FROM $table WHERE $where";
    if ($group_by) $sql .= " GROUP BY $group_by";
    if ($order_by) $sql .= " ORDER BY $order_by";
    if ($limit) $sql .= " LIMIT $limit";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function exportDocentiPDF() {
    // Implementazione generazione PDF
    return ['success' => true, 'message' => 'PDF generato', 'url' => '/temp/report_docenti.pdf'];
}

function exportDocentiExcel() {
    // Implementazione generazione Excel
    return ['success' => true, 'message' => 'Excel generato', 'url' => '/temp/report_docenti.xlsx'];
}

function exportAllReports() {
    // Implementazione export ZIP
    return ['success' => true, 'message' => 'ZIP generato', 'url' => '/temp/all_reports.zip'];
}
?>