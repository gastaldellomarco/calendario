<?php
class SystemChecker {
    
    /**
     * Controlla salute sistema completo
     */
    public function getSystemInfo() {
        return [
            'php_version' => $this->checkPHPVersion(),
            'database' => $this->checkDatabaseConnection(),
            'extensions' => $this->checkRequiredExtensions(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemoryUsage(),
            'permissions' => $this->checkPermissions(),
            'cron_jobs' => $this->checkCronJobs()
        ];
    }
    
    /**
     * Verifica versione PHP
     */
    public function checkPHPVersion() {
        return [
            'version' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'error',
            'min_required' => '8.0.0'
        ];
    }
    
    /**
     * Verifica connessione database
     */
    public function checkDatabaseConnection() {
        try {
            $pdo = getPDOConnection();
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            
            // Verifica tabelle essenziali
            $essentialTables = ['utenti', 'docenti', 'classi', 'materie', 'calendario_lezioni'];
            $missingTables = [];
            
            foreach ($essentialTables as $table) {
                $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
                if (!$exists) {
                    $missingTables[] = $table;
                }
            }
            
            return [
                'status' => empty($missingTables) ? 'ok' : 'warning',
                'version' => $version,
                'missing_tables' => $missingTables,
                'connection' => 'ok'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'connection' => 'failed'
            ];
        }
    }
    
    /**
     * Verifica estensioni PHP richieste
     */
    public function checkRequiredExtensions() {
        $required = [
            'pdo_mysql' => 'PDO MySQL',
            'json' => 'JSON',
            'mbstring' => 'Multibyte String',
            'openssl' => 'OpenSSL',
            'session' => 'Session'
        ];
        
        $results = [];
        foreach ($required as $ext => $name) {
            $results[$ext] = [
                'name' => $name,
                'loaded' => extension_loaded($ext),
                'status' => extension_loaded($ext) ? 'ok' : 'error'
            ];
        }
        
        return $results;
    }
    
    /**
     * Verifica spazio disco
     */
    public function checkDiskSpace() {
        $free = disk_free_space(__DIR__);
        $total = disk_total_space(__DIR__);
        $used = $total - $free;
        $percent = round(($used / $total) * 100, 2);
        
        return [
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_percent' => $percent,
            'status' => $percent > 90 ? 'warning' : ($percent > 95 ? 'error' : 'ok')
        ];
    }
    
    /**
     * Verifica utilizzo memoria
     */
    public function checkMemoryUsage() {
        $memory_limit = ini_get('memory_limit');
        $current_usage = memory_get_usage(true);
        $peak_usage = memory_get_peak_usage(true);
        
        // Convert memory limit to bytes
        $limit_bytes = $this->convertToBytes($memory_limit);
        $usage_percent = round(($current_usage / $limit_bytes) * 100, 2);
        
        return [
            'memory_limit' => $memory_limit,
            'current_usage_mb' => round($current_usage / 1024 / 1024, 2),
            'peak_usage_mb' => round($peak_usage / 1024 / 1024, 2),
            'usage_percent' => $usage_percent,
            'status' => $usage_percent > 80 ? 'warning' : 'ok'
        ];
    }
    
    /**
     * Verifica permessi directory
     */
    public function checkPermissions() {
        $directories = [
            '../backups/' => 'Backups',
            '../uploads/' => 'Uploads',
            '../cache/' => 'Cache',
            '../logs/' => 'Logs'
        ];
        
        $results = [];
        foreach ($directories as $path => $name) {
            $fullPath = __DIR__ . '/' . $path;
            $exists = file_exists($fullPath);
            $writable = is_writable($fullPath);
            $readable = is_readable($fullPath);
            
            $results[$path] = [
                'name' => $name,
                'exists' => $exists,
                'writable' => $writable,
                'readable' => $readable,
                'status' => ($exists && $writable && $readable) ? 'ok' : 'error'
            ];
        }
        
        return $results;
    }
    
    /**
     * Verifica stato cron jobs
     */
    public function checkCronJobs() {
        $cronFiles = [
            '../cron/backup_automatico.php' => 'Backup Automatico',
            '../cron/check_conflitti.php' => 'Controllo Conflitti',
            '../cron/notifiche_giornaliere.php' => 'Notifiche Giornaliere'
        ];
        
        $results = [];
        foreach ($cronFiles as $file => $name) {
            $fullPath = __DIR__ . '/' . $file;
            $exists = file_exists($fullPath);
            
            // Verifica ultima esecuzione (semplificato)
            $lastRun = $this->getLastCronExecution($file);
            
            $results[$file] = [
                'name' => $name,
                'exists' => $exists,
                'last_execution' => $lastRun,
                'status' => $exists ? ($lastRun && time() - strtotime($lastRun) < 86400 ? 'ok' : 'warning') : 'error'
            ];
        }
        
        return $results;
    }
    
    /**
     * Helper: convert memory string to bytes
     */
    private function convertToBytes($memory_limit) {
        if ($memory_limit == '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) substr($memory_limit, 0, -1);
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
    
    /**
     * Ottieni ultima esecuzione cron (semplificato)
     */
    private function getLastCronExecution($cronFile) {
        // In un sistema reale, si leggerebbe da log o database
        // Qui restituiamo una data fittizia per demo
        $logFile = __DIR__ . '/../logs/cron.log';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            if ($lines !== false && is_array($lines)) {
                foreach (array_reverse($lines) as $line) {
                    if (strpos($line, basename($cronFile)) !== false) {
                        preg_match('/\[(.*?)\]/', $line, $matches);
                        return $matches[1] ?? null;
                    }
                }
            }
        }
        
        return null;
    }
}
?>