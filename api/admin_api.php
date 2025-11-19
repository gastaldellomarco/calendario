<?php
require_once '../config/config.php';
requireAuth('preside');

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_user':
            $id = intval($_GET['id']);
            $user = Database::queryOne("SELECT * FROM utenti WHERE id = ?", [$id]);
            echo json_encode($user ?: ['error' => 'Utente non trovato']);
            break;

        case 'backup_database':
            require_once '../includes/BackupManager.php';
            $backupManager = new BackupManager();
            $result = $backupManager->creaBackup('manuale');
            
            logActivity('backup', 'sistema', 0, "Backup manuale creato: " . $result['file_name']);
            echo json_encode(['success' => true, 'message' => 'Backup creato con successo']);
            break;

        case 'download_backup':
            $file = $_GET['file'] ?? '';
            if (!$file) {
                http_response_code(400);
                echo json_encode(['error' => 'File non specificato']);
                exit;
            }
            
            $backupDir = '../backups/';
            $filePath = $backupDir . $file;
            
            if (!file_exists($filePath) || !is_readable($filePath)) {
                http_response_code(404);
                echo json_encode(['error' => 'File non trovato']);
                exit;
            }
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;

        case 'clear_cache':
            // Pulisci cache sessione e altri dati temporanei
            session_start();
            session_regenerate_id(true);
            
            // Pulisci directory cache se esiste
            $cacheDir = '../cache/';
            if (is_dir($cacheDir)) {
                array_map('unlink', glob("$cacheDir/*"));
            }
            
            logActivity('maintenance', 'sistema', 0, "Cache pulita");
            echo json_encode(['success' => true, 'message' => 'Cache pulita con successo']);
            break;

        case 'run_maintenance':
            // Ottimizza tabelle database
            $tables = Database::queryAll("SHOW TABLES");
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                Database::query("OPTIMIZE TABLE `$tableName`");
            }
            
            logActivity('maintenance', 'sistema', 0, "Manutenzione database eseguita");
            echo json_encode(['success' => true, 'message' => 'Manutenzione database completata']);
            break;

        case 'check_updates':
            // Verifica aggiornamenti (simulato)
            $currentVersion = '2.0.0';
            $latestVersion = '2.0.0'; // In produzione, fare richiesta a API esterna
            
            echo json_encode([
                'success' => true,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => version_compare($latestVersion, $currentVersion, '>')
            ]);
            break;

        case 'get_system_info':
            require_once '../includes/SystemChecker.php';
            $checker = new SystemChecker();
            echo json_encode($checker->getSystemInfo());
            break;

        case 'get_db_size':
            $size = 0;
            $tables = Database::queryAll("SHOW TABLE STATUS");
            foreach ($tables as $table) {
                $size += $table['Data_length'] + $table['Index_length'];
            }
            
            echo json_encode([
                'size_bytes' => $size,
                'size_mb' => round($size / 1024 / 1024, 2),
                'size_gb' => round($size / 1024 / 1024 / 1024, 2)
            ]);
            break;

        case 'get_log_details':
            $id = intval($_GET['id']);
            $log = Database::queryOne("SELECT * FROM log_attivita WHERE id = ?", [$id]);
            echo json_encode($log ?: []);
            break;

        case 'crea_snapshot':
            $data = json_decode(file_get_contents('php://input'), true);
            $nome = $data['nome'] ?? '';
            $descrizione = $data['descrizione'] ?? '';
            $tipo = $data['tipo'] ?? 'manuale';
            
            if (!$nome) {
                echo json_encode(['success' => false, 'message' => 'Nome snapshot richiesto']);
                break;
            }
            
            // Backup calendario lezioni
            $lezioni = Database::queryAll("SELECT * FROM calendario_lezioni");
            $dati_calendario = json_encode($lezioni);
            
            Database::query("INSERT INTO snapshot_calendario (nome, descrizione, tipo, dati_calendario) VALUES (?, ?, ?, ?)", 
                          [$nome, $descrizione, $tipo, $dati_calendario]);
            
            logActivity('snapshot', 'calendario', 0, "Snapshot calendario creato: $nome");
            echo json_encode(['success' => true, 'message' => 'Snapshot creato con successo']);
            break;

        case 'ripristina_snapshot':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id']);
            
            $snapshot = Database::queryOne("SELECT * FROM snapshot_calendario WHERE id = ?", [$id]);
            if (!$snapshot) {
                echo json_encode(['success' => false, 'message' => 'Snapshot non trovato']);
                break;
            }
            
            // Ripristina calendario
            Database::query("DELETE FROM calendario_lezioni");
            
            $lezioni = json_decode($snapshot['dati_calendario'], true);
            foreach ($lezioni as $lezione) {
                $fields = array_keys($lezione);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                Database::query("INSERT INTO calendario_lezioni (" . implode(',', $fields) . ") VALUES ($placeholders)", array_values($lezione));
            }
            
            logActivity('snapshot', 'calendario', 0, "Calendario ripristinato da snapshot: {$snapshot['nome']}");
            echo json_encode(['success' => true, 'message' => 'Calendario ripristinato con successo']);
            break;

        case 'elimina_snapshot':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id']);
            
            Database::query("DELETE FROM snapshot_calendario WHERE id = ?", [$id]);
            logActivity('delete', 'snapshot', $id, "Snapshot eliminato");
            echo json_encode(['success' => true, 'message' => 'Snapshot eliminato']);
            break;

        case 'config_backup_auto':
            // Salva configurazioni backup automatici
            $configs = [
                'backup_automatico_enabled' => $_POST['backup_automatico_enabled'] ?? '0',
                'backup_frequency' => $_POST['backup_frequency'] ?? 'daily',
                'backup_time' => $_POST['backup_time'] ?? '02:00',
                'backup_retention_days' => $_POST['backup_retention_days'] ?? '30'
            ];
            
            foreach ($configs as $chiave => $valore) {
                Database::query("UPDATE configurazioni SET valore = ? WHERE chiave = ?", [$valore, $chiave]);
            }
            
            logActivity('update', 'configurazioni', 0, "Configurazione backup automatici aggiornata");
            echo json_encode(['success' => true, 'message' => 'Configurazione salvata']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    logActivity('error', 'admin_api', 0, "Errore API admin: " . $e->getMessage());
}
?>