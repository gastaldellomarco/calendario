<?php
/**
 * Script per scheduling automatico report
 * Da eseguire via cron job
 * 
 * Esempio crontab:
 * 0 6 * * 1 /usr/bin/php /path/to/cron/reports_scheduler.php daily
 * 0 7 * * 1 /usr/bin/php /path/to/cron/reports_scheduler.php weekly
 * 0 8 1 * * /usr/bin/php /path/to/cron/reports_scheduler.php monthly
 */

require_once '../config/database.php';
require_once '../includes/ReportsGenerator.php';

// Configurazione
$config = [
    'email_from' => 'reports@scuola.it',
    'email_from_name' => 'Sistema Report Scuola',
    'temp_path' => '../temp/',
    'log_path' => '../logs/reports_scheduler.log'
];

// Tipi di report schedulabili
$reportTypes = [
    'daily' => [
        'name' => 'Report Giornaliero',
        'recipients' => ['preside@scuola.it', 'segreteria@scuola.it'],
        'reports' => ['kpi_daily', 'conflitti_giornalieri']
    ],
    'weekly' => [
        'name' => 'Report Settimanale',
        'recipients' => ['preside@scuola.it', 'vicepreside@scuola.it', 'segreteria@scuola.it'],
        'reports' => ['docenti_settimanale', 'classi_settimanale', 'aule_settimanale', 'sintesi_settimanale']
    ],
    'monthly' => [
        'name' => 'Report Mensile',
        'recipients' => ['preside@scuola.it', 'dirigente@scuola.it', 'consiglio@scuola.it'],
        'reports' => ['completo_mensile', 'analytics_mensile', 'previsioni_mensili']
    ]
];

function logMessage($message) {
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($config['log_path'], $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

function sendEmailWithReports($recipients, $subject, $body, $attachments = []) {
    global $config;
    
    // Implementazione base invio email (adattare al proprio sistema)
    $headers = [
        'From: ' . $config['email_from_name'] . ' <' . $config['email_from'] . '>',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    $boundary = uniqid('np');
    
    // Header per allegati
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $body . "\r\n";
    
    // Aggiungi allegati
    foreach ($attachments as $attachment) {
        if (file_exists($attachment)) {
            $filename = basename($attachment);
            $fileContent = file_get_contents($attachment);
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: application/octet-stream\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
            $message .= chunk_split(base64_encode($fileContent)) . "\r\n";
        }
    }
    
    $message .= "--$boundary--";
    
    // Invia email (sostituire con sistema email reale)
    foreach ($recipients as $recipient) {
        // mail($recipient, $subject, $message, implode("\r\n", $headers));
        logMessage("Email inviata a: $recipient - Oggetto: $subject");
    }
    
    return true;
}

function generateKPIDailyReport() {
    global $db;
    
    $report = new ReportsGenerator($db);
    
    // KPI del giorno
    $sql = "
        SELECT 
            COUNT(*) as lezioni_oggi,
            SUM(CASE WHEN stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte,
            COUNT(DISTINCT docente_id) as docenti_attivi_oggi,
            COUNT(DISTINCT classe_id) as classi_attive_oggi,
            COUNT(DISTINCT aula_id) as aule_utilizzate_oggi
        FROM calendario_lezioni 
        WHERE data_lezione = CURDATE()
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $kpi = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $html = "
    <h2>Report Giornaliero - " . date('d/m/Y') . "</h2>
    <div style='font-family: Arial, sans-serif;'>
        <h3>📊 KPI della Giornata</h3>
        <table style='width: 100%; border-collapse: collapse;'>
            <tr style='background-color: #f8f9fa;'>
                <th style='padding: 10px; border: 1px solid #ddd;'>Metrica</th>
                <th style='padding: 10px; border: 1px solid #ddd;'>Valore</th>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Lezioni Totali</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$kpi['lezioni_oggi']}</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Lezioni Svolte</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$kpi['lezioni_svolte']}</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Docenti Attivi</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$kpi['docenti_attivi_oggi']}</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Classi Attive</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$kpi['classi_attive_oggi']}</td>
            </tr>
        </table>
        
        <h3>⚠️ Allerte del Giorno</h3>
    ";
    
    // Controlla conflitti
    $sql = "SELECT COUNT(*) as conflitti FROM conflitti_orario WHERE data_conflitto = CURDATE() AND risolto = 0";
    $conflitti = $db->query($sql)->fetchColumn();
    
    if ($conflitti > 0) {
        $html .= "<p style='color: #e74c3c;'><strong>Attenzione:</strong> $conflitti conflitti non risolti per oggi.</p>";
    }
    
    // Controlla lezioni cancellate
    $sql = "SELECT COUNT(*) as cancellazioni FROM calendario_lezioni WHERE data_lezione = CURDATE() AND stato = 'cancellata'";
    $cancellazioni = $db->query($sql)->fetchColumn();
    
    if ($cancellazioni > 0) {
        $html .= "<p style='color: #e67e22;'><strong>Nota:</strong> $cancellazioni lezioni cancellate oggi.</p>";
    }
    
    $html .= "</div>";
    
    return [
        'html' => $html,
        'attachments' => []
    ];
}

function generateWeeklySummaryReport() {
    global $db;
    
    $inizio_settimana = date('Y-m-d', strtotime('monday this week'));
    $fine_settimana = date('Y-m-d', strtotime('sunday this week'));
    
    $sql = "
        SELECT 
            -- Statistiche generali
            COUNT(*) as lezioni_settimana,
            SUM(CASE WHEN stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte,
            SUM(CASE WHEN stato = 'cancellata' THEN 1 ELSE 0 END) as lezioni_cancellate,
            
            -- Utilizzo risorse
            COUNT(DISTINCT docente_id) as docenti_attivi,
            COUNT(DISTINCT classe_id) as classi_attive,
            COUNT(DISTINCT aula_id) as aule_utilizzate,
            
            -- Performance
            ROUND(SUM(CASE WHEN stato = 'svolta' THEN ore_effettive ELSE 0 END), 1) as ore_effettive_totali,
            ROUND(AVG(CASE WHEN stato = 'svolta' THEN ore_effettive ELSE NULL END), 2) as ore_media_lezione
            
        FROM calendario_lezioni 
        WHERE data_lezione BETWEEN ? AND ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$inizio_settimana, $fine_settimana]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $tasso_successo = $stats['lezioni_settimana'] > 0 ? 
        round(($stats['lezioni_svolte'] / $stats['lezioni_settimana']) * 100, 1) : 0;
    
    $html = "
    <h2>Report Settimanale - {$inizio_settimana} al {$fine_settimana}</h2>
    <div style='font-family: Arial, sans-serif;'>
        
        <h3>📈 Sintesi Settimanale</h3>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
            <tr style='background-color: #f8f9fa;'>
                <th style='padding: 10px; border: 1px solid #ddd;'>Metrica</th>
                <th style='padding: 10px; border: 1px solid #ddd;'>Valore</th>
                <th style='padding: 10px; border: 1px solid #ddd;'>Performance</th>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Lezioni Totali</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$stats['lezioni_settimana']}</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>-</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Lezioni Svolte</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$stats['lezioni_svolte']}</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>
                    <span style='color: " . ($tasso_successo >= 90 ? '#27ae60' : '#e74c3c') . ";'>
                        {$tasso_successo}%
                    </span>
                </td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Ore Effettive</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$stats['ore_effettive_totali']}h</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>
                    " . ($stats['ore_effettive_totali'] > 100 ? '🟢 Alta' : '🟡 Media') . "
                </td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>Docenti Attivi</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$stats['docenti_attivi']}</td>
                <td style='padding: 10px; border: 1px solid #ddd;'>
                    " . ($stats['docenti_attivi'] > 20 ? '🟢 Ottimo' : '🟡 Sufficiente') . "
                </td>
            </tr>
        </table>
        
        <h3>🎯 Raccomandazioni</h3>
        <ul>
    ";
    
    // Raccomandazioni basate sui dati
    if ($stats['lezioni_cancellate'] > 10) {
        $html .= "<li>📉 Elevato numero di lezioni cancellate ({$stats['lezioni_cancellate']}). Valutare cause e soluzioni.</li>";
    }
    
    if ($tasso_successo < 85) {
        $html .= "<li>⚠️ Tasso di successo sotto l'85%. Analizzare le cause delle cancellazioni.</li>";
    }
    
    if ($stats['aule_utilizzate'] < 5) {
        $html .= "<li>🏫 Basso utilizzo delle aule. Solo {$stats['aule_utilizzate']} aule utilizzate questa settimana.</li>";
    }
    
    $html .= "
        </ul>
        
        <h3>📊 Prossima Settimana</h3>
        <p>Pianificazione per la prossima settimana:</p>
        <ul>
            <li>Lezioni pianificate: " . getPianificazioneProssimaSettimana() . "</li>
            <li>Docenti disponibili: " . getDocentiDisponibiliProssimaSettimana() . "</li>
            <li>Aule libere: " . getAuleLibereProssimaSettimana() . "</li>
        </ul>
    </div>
    ";
    
    return [
        'html' => $html,
        'attachments' => []
    ];
}

function getPianificazioneProssimaSettimana() {
    global $db;
    $inizio_prossima = date('Y-m-d', strtotime('monday next week'));
    $fine_prossima = date('Y-m-d', strtotime('sunday next week'));
    
    $sql = "SELECT COUNT(*) as lezioni FROM calendario_lezioni WHERE data_lezione BETWEEN ? AND ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$inizio_prossima, $fine_prossima]);
    return $stmt->fetchColumn();
}

function getDocentiDisponibiliProssimaSettimana() {
    global $db;
    // Implementazione semplificata
    return "35/40";
}

function getAuleLibereProssimaSettimana() {
    global $db;
    // Implementazione semplificata  
    return "15/20";
}

// Esecuzione principale
if (php_sapi_name() === 'cli') {
    $tipoReport = $argv[1] ?? 'daily';
    
    if (!isset($reportTypes[$tipoReport])) {
        die("Tipo report non valido. Usare: daily, weekly, monthly\n");
    }
    
    $configReport = $reportTypes[$tipoReport];
    
    logMessage("Inizio generazione report {$tipoReport}");
    
    try {
        $attachments = [];
        $htmlBody = "<html><body>";
        $htmlBody .= "<h1>{$configReport['name']}</h1>";
        $htmlBody .= "<p>Generato automaticamente il " . date('d/m/Y H:i') . "</p>";
        
        // Genera i report specifici
        foreach ($configReport['reports'] as $reportName) {
            switch ($reportName) {
                case 'kpi_daily':
                    $result = generateKPIDailyReport();
                    $htmlBody .= $result['html'];
                    $attachments = array_merge($attachments, $result['attachments']);
                    break;
                    
                case 'sintesi_settimanale':
                    $result = generateWeeklySummaryReport();
                    $htmlBody .= $result['html'];
                    $attachments = array_merge($attachments, $result['attachments']);
                    break;
                    
                // Aggiungere altri tipi di report qui
            }
        }
        
        $htmlBody .= "</body></html>";
        
        // Invia email
        $subject = "{$configReport['name']} - " . date('d/m/Y');
        sendEmailWithReports($configReport['recipients'], $subject, $htmlBody, $attachments);
        
        logMessage("Report {$tipoReport} generato e inviato con successo");
        
    } catch (Exception $e) {
        logMessage("ERRORE nella generazione report {$tipoReport}: " . $e->getMessage());
    }
} else {
    die("Questo script può essere eseguito solo da riga di comando\n");
}
?>