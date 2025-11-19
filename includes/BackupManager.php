<?php
class BackupManager {
    private $backupDir;
    private $maxBackupAge; // giorni
    
    public function __construct() {
        $this->backupDir = __DIR__ . '/../backups/';
        $this->maxBackupAge = 30; // giorni
        
        // Crea directory backup se non esiste
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Crea un backup del database
     */
    public function creaBackup($tipo = 'manuale') {
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = "backup_{$tipo}_{$timestamp}.sql.gz";
        $filePath = $this->backupDir . $fileName;
        
        // Configurazione database
        $dbHost = DB_HOST;
        $dbName = DB_NAME;
        $dbUser = DB_USER;
        $dbPass = DB_PASS;
        
        // Rileva sistema operativo
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Trova mysqldump
        $mysqldump = $this->findMysqldump($isWindows);
        if (!$mysqldump) {
            throw new Exception("mysqldump non trovato. Assicurati che MySQL sia installato e nel PATH.");
        }
        
        // Crea file temporaneo SQL non compresso
        $tempSqlFile = $this->backupDir . "temp_backup_{$timestamp}.sql";
        
        // Costruisci comando mysqldump
        $passwordArg = !empty($dbPass) ? "--password=" . escapeshellarg($dbPass) : "";
        $command = escapeshellarg($mysqldump) . 
                   " --host=" . escapeshellarg($dbHost) . 
                   " --user=" . escapeshellarg($dbUser) . 
                   " " . $passwordArg . 
                   " --single-transaction --routines --triggers" .
                   " " . escapeshellarg($dbName);
        
        // Usa popen per leggere l'output direttamente (funziona meglio su Windows)
        $handle = @popen($command . " 2>&1", "r");
        if (!$handle) {
            throw new Exception("Impossibile eseguire mysqldump. Verifica che MySQL sia installato e accessibile.");
        }
        
        // Apri file di output
        $outputFile = @fopen($tempSqlFile, 'w');
        if (!$outputFile) {
            pclose($handle);
            throw new Exception("Impossibile creare il file di backup temporaneo.");
        }
        
        // Leggi output e scrivi nel file
        $errorOutput = '';
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                // Se la riga inizia con "Warning" o "Error", è un messaggio di errore
                if (stripos($line, 'error') !== false || stripos($line, 'warning') !== false) {
                    $errorOutput .= $line;
                }
                fwrite($outputFile, $line);
            }
        }
        
        // Chiudi handle
        $returnCode = pclose($handle);
        fclose($outputFile);
        
        // Verifica se il file è stato creato
        if (!file_exists($tempSqlFile)) {
            $errorMsg = "File di backup non creato. ";
            if (!empty($errorOutput)) {
                $errorMsg .= "Errore: " . $errorOutput;
            }
            throw new Exception($errorMsg);
        }
        
        // Verifica dimensione file
        if (filesize($tempSqlFile) === 0) {
            unlink($tempSqlFile);
            $errorMsg = "File di backup vuoto. ";
            if (!empty($errorOutput)) {
                $errorMsg .= "Errore: " . $errorOutput;
            }
            if ($returnCode !== 0) {
                $errorMsg .= " (Exit code: $returnCode)";
            }
            throw new Exception($errorMsg);
        }
        
        // Se c'è un errore ma il file è stato creato, verifica se contiene errori
        if ($returnCode !== 0 && !empty($errorOutput)) {
            $content = file_get_contents($tempSqlFile);
            // Se il contenuto contiene solo errori, fallisce
            if (stripos($content, 'CREATE TABLE') === false && stripos($content, 'INSERT INTO') === false) {
                unlink($tempSqlFile);
                throw new Exception("Errore durante il backup: " . $errorOutput);
            }
        }
        
        // Comprimi il file usando gzencode (funziona su tutti i sistemi)
        $sqlContent = file_get_contents($tempSqlFile);
        $compressed = gzencode($sqlContent, 9); // Livello di compressione 9 (massimo)
        
        if ($compressed === false) {
            unlink($tempSqlFile);
            throw new Exception("Errore durante la compressione del backup");
        }
        
        // Salva file compresso
        if (file_put_contents($filePath, $compressed) === false) {
            unlink($tempSqlFile);
            throw new Exception("Errore durante il salvataggio del backup compresso");
        }
        
        // Elimina file temporaneo
        unlink($tempSqlFile);
        
        // Verifica integrità
        if (!$this->verificaIntegritaBackup($filePath)) {
            unlink($filePath);
            throw new Exception("Backup corrotto o non valido");
        }
        
        // Pulisci backup vecchi
        $this->eliminaBackupVecchi();
        
        return [
            'success' => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => filesize($filePath),
            'created_at' => $timestamp
        ];
    }
    
    /**
     * Trova il percorso di mysqldump
     */
    private function findMysqldump($isWindows) {
        // Prova percorsi comuni
        $paths = [];
        
        if ($isWindows) {
            // Percorsi Windows comuni
            $paths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp\\bin\\mysql\\mysql' . $this->getMysqlVersion() . '\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
                'C:\\Program Files (x86)\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            ];
        } else {
            // Percorsi Linux/Unix comuni
            $paths = [
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/opt/mysql/bin/mysqldump',
            ];
        }
        
        // Prova ogni percorso
        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        
        // Prova se mysqldump è nel PATH
        $output = [];
        $returnCode = 0;
        exec('mysqldump --version 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            return 'mysqldump';
        }
        
        return null;
    }
    
    /**
     * Ottiene versione MySQL (semplificato)
     */
    private function getMysqlVersion() {
        try {
            $pdo = getPDOConnection();
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            if (preg_match('/(\d+)\.(\d+)/', $version, $matches)) {
                return $matches[1] . $matches[2];
            }
        } catch (Exception $e) {
            // Ignora errori
        }
        return '80'; // Default
    }
    
    /**
     * Verifica integrità file backup
     */
    public function verificaIntegritaBackup($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Verifica che il file non sia vuoto
        if (filesize($filePath) === 0) {
            return false;
        }
        
        // Prova a decomprimere il file usando gzdecode (funziona su tutti i sistemi)
        $compressedContent = file_get_contents($filePath);
        if ($compressedContent === false) {
            return false;
        }
        
        $content = @gzdecode($compressedContent);
        if ($content === false || empty($content)) {
            return false;
        }
        
        // Verifica che contenga dati SQL
        return strpos($content, 'CREATE TABLE') !== false || 
               strpos($content, 'INSERT INTO') !== false;
    }
    
    /**
     * Elimina backup più vecchi di maxBackupAge giorni
     */
    public function eliminaBackupVecchi() {
        $files = glob($this->backupDir . "backup_*.sql.gz");
        $cutoffTime = time() - ($this->maxBackupAge * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
    
    /**
     * Elimina un backup specifico
     */
    public function eliminaBackup($fileName) {
        $filePath = $this->backupDir . $fileName;
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Verifica che sia un file di backup valido
        if (strpos($fileName, 'backup_') !== 0 || strpos($fileName, '.sql.gz') === false) {
            return false;
        }
        
        return unlink($filePath);
    }
    
    /**
     * Lista tutti i backup disponibili
     */
    public function listaBackup() {
        $files = glob($this->backupDir . "backup_*.sql.gz");
        $backups = [];
        
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'timestamp' => filemtime($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Ordina per data (più recente prima)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }
    
    /**
     * Ripristina database da backup
     */
    public function ripristinaBackup($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File di backup non trovato");
        }
        
        if (!$this->verificaIntegritaBackup($filePath)) {
            throw new Exception("File di backup corrotto o non valido");
        }
        
        // Decomprimi il file
        $compressedContent = file_get_contents($filePath);
        $sqlContent = gzdecode($compressedContent);
        
        if ($sqlContent === false || empty($sqlContent)) {
            throw new Exception("Errore durante la decompressione del backup");
        }
        
        // Usa PDO per eseguire il ripristino (più affidabile su tutti i sistemi)
        try {
            $pdo = getPDOConnection();
            
            // Disabilita controlli temporaneamente per importazione veloce
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->exec("SET AUTOCOMMIT=0");
            $pdo->beginTransaction();
            
            // Esegui il SQL in batch
            $statements = array_filter(
                array_map('trim', explode(';', $sqlContent)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*/', $stmt);
                }
            );
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            
            $pdo->commit();
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $pdo->exec("SET AUTOCOMMIT=1");
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new Exception("Errore durante il ripristino: " . $e->getMessage());
        }
        
        return true;
    }
    
    /**
     * Invia backup via email
     */
    public function inviaBackupEmail($filePath, $destinatari) {
        if (!file_exists($filePath)) {
            throw new Exception("File di backup non trovato");
        }
        
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        
        // Configura email (semplificata - in produzione usare PHPMailer)
        $subject = "Backup Database - " . date('d/m/Y');
        $message = "Backup del database allegato.\n\n";
        $message .= "File: $fileName\n";
        $message .= "Dimensione: " . number_format($fileSize / 1024 / 1024, 2) . " MB\n";
        $message .= "Data: " . date('d/m/Y H:i:s');
        
        $headers = "From: sistema@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"boundary\"\r\n";
        
        $body = "--boundary\r\n";
        $body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $message . "\r\n\r\n";
        $body .= "--boundary\r\n";
        $body .= "Content-Type: application/gzip; name=\"$fileName\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($filePath))) . "\r\n";
        $body .= "--boundary--";
        
        foreach ($destinatari as $email) {
            if (!mail($email, $subject, $body, $headers)) {
                throw new Exception("Errore nell'invio email a: $email");
            }
        }
        
        return true;
    }
}
?>