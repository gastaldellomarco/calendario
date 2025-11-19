<?php
/**
 * Script per backup automatici del database
 * Da eseguire via cron job
 * 
 * Esempio cron:
 * 0 2 * * * /usr/bin/php /path/to/scuola/cron/backup_automatico.php
 */

require_once '../config/config.php';
require_once '../includes/BackupManager.php';
require_once '../includes/Logger.php';

// Solo esecuzione da command line
if (php_sapi_name() !== 'cli') {
    die('Accesso negato');
}

try {
    echo "[" . date('Y-m-d H:i:s') . "] Avvio backup automatico...\n";
    
    // Carica configurazioni
    $configs = [];
    $configRows = Database::queryAll("SELECT chiave, valore FROM configurazioni");
    foreach ($configRows as $row) {
        $configs[$row['chiave']] = $row['valore'];
    }
    
    // Verifica se backup automatici sono abilitati
    if (($configs['backup_automatico_enabled'] ?? '0') !== '1') {
        echo "Backup automatici disabilitati nelle configurazioni\n";
        exit(0);
    }
    
    $backupManager = new BackupManager();
    
    // Esegui backup
    $result = $backupManager->creaBackup('automatico');
    
    echo "Backup creato: " . $result['file_name'] . " (" . number_format($result['file_size'] / 1024 / 1024, 2) . " MB)\n";
    
    // Elimina backup vecchi
    $backupManager->eliminaBackupVecchi();
    
    // Log successo
    Logger::logBackup('automatico', "Backup automatico completato: {$result['file_name']}");
    
    // Invia notifica email se configurato
    if (!empty($configs['email_notifiche_backup'])) {
        $emails = explode(',', $configs['email_notifiche_backup']);
        $backupManager->inviaBackupEmail($result['file_path'], $emails);
        echo "Notifica email inviata a: " . implode(', ', $emails) . "\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Backup automatico completato con successo\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRORE: " . $e->getMessage() . "\n";
    
    // Log errore
    Logger::logError("Backup automatico fallito: " . $e->getMessage());
    
    // Invia notifica errore
    if (!empty($configs['email_notifiche_backup'])) {
        $emails = explode(',', $configs['email_notifiche_backup']);
        $subject = "ERRORE Backup Database - " . date('d/m/Y');
        $message = "Il backup automatico del database è fallito.\n\n";
        $message .= "Errore: " . $e->getMessage() . "\n";
        $message .= "Data: " . date('d/m/Y H:i:s') . "\n";
        
        foreach ($emails as $email) {
            mail($email, $subject, $message);
        }
    }
    
    exit(1);
}
?>