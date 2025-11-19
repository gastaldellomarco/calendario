<?php
/**
 * VERSIONE CORRETTA - config/database.php
 * Usa SOLO PDO, no MySQLi legacy
 */

// Configurazioni del database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'scuola_calendario');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Funzione per ottenere connessione PDO
 * Pattern Singleton - solo una connessione per pagina
 * @return PDO
 * @throws Exception
 */
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("ERRORE CONNESSIONE DB: " . $e->getMessage());
            throw new Exception('Errore di connessione al database.');
        }
    }
    
    return $pdo;
}

/**
 * Classe Database - Helper per query comuni
 */
class Database {
    private static $pdo = null;
    
    public static function getConnection() {
        if (self::$pdo === null) {
            self::$pdo = getPDOConnection();
        }
        return self::$pdo;
    }
    
    /**
     * Esegue una query con parametri
     * @param string $sql Query SQL con placeholder (?)
     * @param array $params Parametri
     * @return PDOStatement
     */
    public static function query($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Log SQL, params and error message to help debugging
            $params_log = '';
            try {
                $params_log = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (Exception $ex) {
                $params_log = 'unable to json_encode params';
            }
            error_log("Errore query: " . $e->getMessage() . " - SQL: " . $sql . " - Params: " . $params_log);
            // In development mode, rethrow detailed PDO error; in production, keep generic to avoid leaking details
            if (defined('APP_ENV') && APP_ENV === 'development') {
                throw new Exception('Database error: ' . $e->getMessage());
            }
            throw new Exception("Errore durante l'operazione sul database.");
        }
    }
    
    /**
     * Esegue una query e ritorna una riga
     */
    public static function queryOne($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Esegue una query e ritorna tutte le righe
     */
    public static function queryAll($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Esegue una query e ritorna il conteggio di righe
     */
    public static function count($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
}

// ✅ CORRETTO: Crea $pdo globale al primo include
// Dichiara come global subito - sarà disponibile in tutti gli scope
global $pdo;
if (!isset($GLOBALS['pdo'])) {
    $GLOBALS['pdo'] = getPDOConnection();
}
// Assegna a variabile locale per compatibilità
$pdo = $GLOBALS['pdo'];
?>