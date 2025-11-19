<?php
/**
 * File di configurazione dell'applicazione
 * Imposta costanti, sessioni e ambiente
 */

// Abilita reporting errori per sviluppo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Imposta timezone
date_default_timezone_set('Europe/Rome');

// ✅ CORREZIONE: Calcolo corretto di BASE_URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];
$script_path = dirname($_SERVER['SCRIPT_NAME']);
// Estrae la root directory (es. /scuola da /scuola/dashboard.php)
$root_path = explode('/', trim($script_path, '/'))[0];
if ($root_path) {
    $root_path = '/' . $root_path;
} else {
    $root_path = '';
}

// Costanti di configurazione
define('SITE_NAME', 'Gestione Calendario Scolastico');
define('BASE_URL', $protocol . $host . $root_path); // ✅ CORRETTO
define('VERSION', '2.0.0');
define('APP_ENV', 'development'); // development | production

// Configurazione sessioni sicure (PRIMA di session_start())
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Impostare a 1 in produzione con HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Avvia sessione solo se non è già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Genera un token CSRF globale per tutte le pagine se non presente
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // random_bytes fallisce in ambienti non sicuri; usa fallback
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// 🔧 INCLUDI database.php per ottenere la connessione $db
require_once __DIR__ . '/database.php';

// Garantisci che $db è disponibile globalmente in tutti gli scope
$GLOBALS['db'] = $GLOBALS['pdo'];
$db = $GLOBALS['db'];

// Includi le funzioni di utilità - usato in molteplici pagine
require_once __DIR__ . '/../includes/functions.php';

// Auto-load delle classi (se necessario in futuro)
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ✅ CORREZIONE: Centralizza le funzioni di utility
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('getLoggedUserName')) {
    function getLoggedUserName() {
        return $_SESSION['nome_completo'] ?? $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Utente';
    }
}

if (!function_exists('getLoggedUserRole')) {
    function getLoggedUserRole() {
        return $_SESSION['ruolo'] ?? 'guest';
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        // ✅ Se l'URL inizia con /, usa BASE_URL
        if (strpos($url, '/') === 0) {
            $url = BASE_URL . $url;
        }
        header("Location: " . $url);
        exit;
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Gestione errori personalizzata
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (APP_ENV === 'development') {
        error_log("Errore PHP [$errno]: $errstr in $errfile alla linea $errline");
        // In sviluppo mostra l'errore
        if (ini_get('display_errors')) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
            echo "<strong>Errore:</strong> $errstr<br>";
            echo "<strong>File:</strong> $errfile<br>";
            echo "<strong>Linea:</strong> $errline";
            echo "</div>";
        }
    } else {
        // In produzione, loggare ma non mostrare dettagli all'utente
        error_log("Errore [$errno]: $errstr in $errfile alla linea $errline");
    }
    return true; // Previene l'handler di default
});

// Gestione eccezioni non catturate
set_exception_handler(function($exception) {
    error_log("Eccezione non catturata: " . $exception->getMessage());
    if (APP_ENV === 'development') {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<h3>Eccezione non catturata</h3>";
        echo "<p><strong>Messaggio:</strong> " . $exception->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>Linea:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<p>Si è verificato un errore imprevisto. Si prega di riprovare più tardi.</p>";
        echo "</div>";
    }
});

// Funzione di debug (solo in sviluppo)
function debug($data, $label = null) {
    if (APP_ENV === 'development') {
        echo "<div style='background: #e9ecef; padding: 10px; margin: 10px; border: 1px solid #ced4da; border-radius: 5px;'>";
        if ($label) {
            echo "<strong>$label:</strong><br>";
        }
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        echo "</div>";
    }
}
?>