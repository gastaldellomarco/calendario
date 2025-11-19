<?php
/**
 * SYSTEM VERIFICATION SCRIPT
 * School Calendar Management System
 * 
 * Questo script verifica che il sistema sia configurato correttamente
 * e che tutti i file critici siano presenti.
 * 
 * Accesso: http://localhost/scuola/verify_system.php
 */

// Disable debug output for now
error_reporting(E_ALL);
ini_set('display_errors', 0);

$base_path = __DIR__;
$checks = [];
$all_passed = true;

// Helpers
function addCheck(&$checks, $section, $label, $data) {
    if (!isset($checks[$section])) $checks[$section] = [];
    $checks[$section][$label] = $data;
}

function isCli() { return php_sapi_name() === 'cli'; }

/**
 * Recursively get list of files in a directory
 */
function scanDirectory($dir, $base_path) {
    $files = [];
    if (!is_dir($dir)) return $files;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $path = $f->getPathname();
            // Normalize to project relative path in a robust way
            $rel = substr($path, strlen($base_path) + 1);
            // Normalize to forward slashes for consistency
            $rel = str_replace('\\', '/', $rel);
            $files[] = $rel;
        }
    }
    sort($files);
    return $files;
}

/**
 * Try to lint PHP file with `php -l` if shell_exec is available
 */
function lintPhpFile($file) {
    $result = ['available' => false, 'ok' => null, 'output' => ''];
    if (!function_exists('shell_exec')) {
        return $result;
    }
    // Prefer an explicit CLI PHP binary:
    // 1. Use PHP_BINARY if it's a valid executable
    // 2. Fall back to known XAMPP path on Windows
    // 3. Finally fallback to 'php' in PATH
    $php = null;
    if (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) {
        $php = PHP_BINARY;
    }
    // Try standard XAMPP CLI php.exe if present (Windows)
    $xamppPhp = 'C:\\xampp\\php\\php.exe';
    if (!$php && file_exists($xamppPhp)) {
        $php = $xamppPhp;
    }
    if (!$php) {
        $php = 'php';
    }
    $cmd = $php . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $out = @shell_exec($cmd);
    if ($out === null) return $result;
    $result['available'] = true;
    $result['output'] = trim($out);
    // If the CLI returns Apache errors (AH02965) treat as lint not available (wrong binary)
    if (stripos($out, 'AH02965') !== false || stripos($out, 'Unable to retrieve my generation from the parent') !== false) {
        $result['available'] = false;
        $result['ok'] = null;
        $result['output'] = trim($out) . ' (lint returned Apache child process error - check PHP binary or use CLI php)';
        return $result;
    }
    $result['ok'] = stripos($out, 'No syntax errors detected') !== false;
    return $result;
}

/**
 * Check if a given file contains backticks in PHP code blocks
 */
function hasBacktickInPhpCode($file) {
    $content = @file_get_contents($file);
    if ($content === false) return false;
    preg_match_all('/<\?(?:php)?(.*?)\?>/is', $content, $m);
    foreach ($m[1] as $block) {
        if (strpos($block, '`') !== false) return true;
    }
    return false;
}

/**
 * Find occurrences of debug/dangerous patterns
 */
function findPatternsInFile($file, $patterns) {
    $found = [];
    $content = @file_get_contents($file);
    if ($content === false) return $found;
    foreach ($patterns as $name => $pattern) {
        if (is_string($pattern) && strlen($pattern) > 1 && $pattern[0] === '/' && $pattern[strlen($pattern)-1] === '/') {
            // regex
            if (preg_match($pattern, $content)) $found[$name] = true;
        } else {
            // plain substring
            if (strpos($content, $pattern) !== false) $found[$name] = true;
        }
    }
    return $found;
}

/**
 * Search a regex pattern inside a file and return line numbers + snippet
 */
function findPatternWithContext($file, $pattern, $maxLines = 5) {
    $found = [];
    $lines = @file($file);
    if ($lines === false) return $found;
    foreach ($lines as $ln => $text) {
        if (preg_match($pattern, $text, $m)) {
            $start = max(0, $ln - 2);
            $end = min(count($lines) - 1, $ln + 2);
            $ctx = array_slice($lines, $start, $end - $start + 1, true);
            $found[] = ['line' => $ln + 1, 'snippet' => trim($text), 'context' => array_map('trim', $ctx)];
            if (count($found) >= $maxLines) break;
        }
    }
    return $found;
}

/**
 * Extract asset references from a file (src/href)
 */
function extractAssetReferences($file) {
    $content = @file_get_contents($file);
    if ($content === false) return [];
    $refs = [];
    // img src, script src, link href
    preg_match_all('/(?:src|href)=["\']([^"\']+)["\']/i', $content, $m);
    foreach ($m[1] ?? [] as $ref) {
        // Ignore absolute URLs
        if (preg_match('#^https?://#i', $ref)) continue;
        // Normalize relative paths (strip query params)
        $ref = preg_replace('/\?.*$/', '', $ref);
        $refs[] = $ref;
    }
    return array_values(array_unique($refs));
}

/**
 * Parse SQL file to extract expected tables and columns
 */
function parseSqlSchema($sqlPath) {
    $schema = [];
    if (!file_exists($sqlPath)) return $schema;
    $content = file_get_contents($sqlPath);
    // Find all CREATE TABLE blocks
    $matches = [];
    preg_match_all('/CREATE\s+TABLE\s+`?([0-9a-zA-Z_]+)`?\s*\((.*?)\)\s*(ENGINE|DEFAULT|COMMENT|;)/is', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $table = $m[1];
        $colsBlock = $m[2];
        $cols = [];
        // For each line in block, capture backtick-enclosed column names
        $lines = preg_split('/\r?\n/', $colsBlock);
        foreach ($lines as $line) {
            if (preg_match('/^\s*`([^`]+)`\s+([^,]+)/', trim($line), $colMatch)) {
                $cols[] = $colMatch[1];
            }
        }
        $schema[$table] = $cols;
    }
    return $schema;
}

// Core verification function
function runAllChecks($base_path) {
    $checks = [];
    $all_passed = true;

    // 1. File Verification
$files_to_check = [
    'pages/docenti.php' => 'Docenti List Page',
    'pages/materie.php' => 'Materie List Page',
    'pages/aule.php' => 'Aule List Page',
    'pages/classi.php' => 'Classi List Page',
    'pages/sedi.php' => 'Sedi List Page',
    'pages/anni_scolastici.php' => 'Academic Years',
    'pages/giorni_chiusura.php' => 'Closure Days',
    'pages/aula_form.php' => 'Room Form (NEW)',
    'pages/sedi_form.php' => 'Location Form (NEW)',
    'pages/classe_form.php' => 'Class Form',
    'pages/giorni_chiusura_form.php' => 'Closure Form (NEW)',
    'pages/aula_disponibilita.php' => 'Room Availability (NEW)',
    'api/docenti_api.php' => 'Docenti API',
    'api/materie_api.php' => 'Materie API',
    'api/aule_api.php' => 'Aule API',
    'api/classi_api.php' => 'Classi API',
    'config/database.php' => 'Database Config',
    'includes/header.php' => 'Header Template',
    'includes/footer.php' => 'Footer Template',
];

foreach ($files_to_check as $file => $label) {
    $full_path = $base_path . '/' . $file;
    $exists = file_exists($full_path);
    $checks['files'][$label] = ['exists' => $exists, 'path' => $file];
    if (!$exists) $all_passed = false;
}

// Perform a full recursive scan of the project for additional checks
$directories_to_scan = [
    'pages', 'api', 'includes', 'components', 'assets', 'auth', 'cron', 'data', 'algorithm', 'templates', 'reports'
];

$all_scanned_files = [];
foreach ($directories_to_scan as $d) {
    $full = $base_path . '/' . $d;
    $scanned = scanDirectory($full, $base_path);
    foreach ($scanned as $f) {
        $all_scanned_files[] = $f;
    }
}

// Unique and sorted list
$all_scanned_files = array_unique($all_scanned_files);
sort($all_scanned_files);

// Code patterns to detect
$debug_error_patterns = [
    'var_dump' => 'var_dump(',
    'print_r' => 'print_r(',
    'debug_function' => 'debug(',
    'eval' => 'eval(',
    'shell_exec' => 'shell_exec(',
    'exec' => '/(?<!->|::)\bexec\s*\(/i',
    'system' => 'system(',
];

$debug_warning_patterns = [
    'die' => 'die(',
    'exit' => 'exit(',
];

$todo_patterns = ['/TODO:/i', '/FIXME:/i', '/XXX/i', '/@todo/i'];

$asset_refs_missing = [];
$debug_found = [];
$lint_issues = [];

foreach ($all_scanned_files as $rel) {
    $full_path = $base_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    $stat = @stat($full_path);
    $size = $stat['size'] ?? 0;
    $readable = is_readable($full_path);
    $checks['scanned_files'][$rel] = [
        'size' => $size,
        'readable' => $readable,
        'path' => $rel,
        'ext' => $ext,
    ];

    // Lint php files when possible
    if ($ext === 'php') {
        $lint = lintPhpFile($full_path);
        $checks['scanned_files'][$rel]['php_lint'] = $lint;
        if ($lint['available'] && $lint['ok'] === false) {
            $lint_issues[$rel] = $lint;
            $all_passed = false;
        }

        // Find debug/dangerous patterns (errors)
        $p = findPatternsInFile($full_path, $debug_error_patterns);
        if (!empty($p)) {
            $debug_found[$rel] = array_keys($p);
            $checks['scanned_files'][$rel]['debug_patterns'] = array_keys($p);
            $all_passed = false; // treat these as blocking issues
        }
        // Find warning patterns (die/exit/short tags) - keep as warnings
        $pw = findPatternsInFile($full_path, $debug_warning_patterns);
        if (!empty($pw)) {
            $checks['scanned_files'][$rel]['debug_warnings'] = array_keys($pw);
            // Do not flip $all_passed for warnings, only record them
            $checks['code_quality']['warnings'][$rel] = array_keys($pw);
        }

        // TODO/FIXME (capture context)
        $todo_res = findPatternWithContext($full_path, '/TODO:|FIXME:|XXX|@todo/i', 50);
        if (!empty($todo_res)) {
            $checks['scanned_files'][$rel]['todos'] = $todo_res;
            $checks['code_quality']['todos'][$rel] = $todo_res;
            $all_passed = false;
        }
        // Asset references inside php/html files
        $refs = extractAssetReferences($full_path);
        if (!empty($refs)) {
            foreach ($refs as $r) {
                // Ignore external links and mailto/tel
                if (preg_match('#^(https?:)?//#i', $r) || preg_match('#^(mailto|tel):#i', $r)) continue;
                // Try to resolve absolute paths (starting with /) vs relative to the current file
                if (strpos($r, '/') === 0) {
                    $candidate = $base_path . DIRECTORY_SEPARATOR . ltrim($r, '/');
                    $target = realpath($candidate) ?: $candidate;
                } else {
                    // resolve relative to the referring file
                    $candidate = dirname($full_path) . DIRECTORY_SEPARATOR . $r;
                    $target = realpath($candidate) ?: $candidate;
                }
                if (!file_exists($target)) {
                    $asset_refs_missing[] = ['file' => $rel, 'ref' => $r, 'target' => $target];
                    $all_passed = false;
                }
            }
        }

        // Additional dangerous and legacy function checks (with context)
        $dangerous_patterns = [
            'mysql_*' => '/\bmysql_\w+\b/i',
            'unserialize' => '/\bunserialize\s*\(/i',
            'create_function' => '/\bcreate_function\s*\(/i',
            'assert' => '/\bassert\s*\(/i'
        ];
        foreach ($dangerous_patterns as $name => $pat) {
            $res = findPatternWithContext($full_path, $pat, 10);
            if (!empty($res)) {
                $checks['code_quality']['dangerous_patterns'][$rel][$name] = $res;
                $all_passed = false;
            }
        }
        // Check for PHP backtick operator "`cmd`" usage ONLY inside PHP code blocks (avoid JS template literals)
        if (hasBacktickInPhpCode($full_path)) {
            $resb = findPatternWithContext($full_path, '/\`[^`]*\`/m', 10);
            if (!empty($resb)) {
                $checks['code_quality']['dangerous_patterns'][$rel]['backtick_op'] = $resb;
                $all_passed = false;
            }
        }
    }
}

if (!empty($lint_issues)) {
    $checks['code_quality']['php_lint'] = $lint_issues;
}
if (!empty($debug_found)) {
    $checks['code_quality']['debug_patterns'] = $debug_found;
}
if (!empty($asset_refs_missing)) {
    $checks['assets']['missing_references'] = $asset_refs_missing;
}

// 2. Database Connection Check
try {
    require_once 'config/database.php';
    $pdo = getPDOConnection();
    $checks['database']['connection'] = ['success' => true, 'message' => 'Connected'];
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_tables = ['docenti', 'materie', 'aule', 'classi', 'sedi', 'anni_scolastici', 'giorni_chiusura'];
    
    foreach ($required_tables as $table) {
        $exists = in_array($table, $tables);
        $checks['database']['tables'][$table] = ['exists' => $exists];
        if (!$exists) $all_passed = false;
    }
    // If we have the SQL schema file, parse expected schema and compare
    $sql_schema_path = $base_path . '/scuola_db_v2.sql';
    if (file_exists($sql_schema_path)) {
        $expected_schema = parseSqlSchema($sql_schema_path);
        foreach ($expected_schema as $table => $cols) {
            $table_exists = in_array($table, $tables);
            $checks['database']['tables'][$table]['exists'] = $table_exists;
            if ($table_exists) {
                $stmtc = $pdo->query("SHOW COLUMNS FROM `" . $table . "`");
                $actual_cols = $stmtc->fetchAll(PDO::FETCH_COLUMN);
                $missing_cols = array_diff($cols, $actual_cols ?: []);
                if (!empty($missing_cols)) {
                    $checks['database']['tables'][$table]['missing_columns'] = array_values($missing_cols);
                    $all_passed = false;
                }
            } else {
                $all_passed = false;
            }
        }
    } else {
        $checks['database']['schema_file'] = ['exists' => false, 'path' => 'scuola_db_v2.sql'];
    }
    // Check configured DB schema version matches application version (if available)
    try {
        $stmtv = $pdo->prepare("SELECT valore FROM configurazioni WHERE chiave = 'versione_database' LIMIT 1");
        $stmtv->execute();
        $rowv = $stmtv->fetch(PDO::FETCH_ASSOC);
        $db_ver = $rowv['valore'] ?? null;
        $checks['database']['versione_database'] = $db_ver;
        // Compare with application VERSION constant (if defined)
        if (defined('VERSION')) {
            $app_ver = VERSION;
            $checks['system']['app_version'] = $app_ver;
            if (strpos($app_ver, $db_ver) === false) {
                $checks['database']['version_mismatch'] = ['app_version' => $app_ver, 'db_version' => $db_ver];
                $all_passed = false;
            }
        }
    } catch (Exception $e) {
        // Ignore if table not present
    }
} catch (Exception $e) {
    $checks['database']['connection'] = ['success' => false, 'message' => $e->getMessage()];
    $all_passed = false;
}

// 3. Permissions Check
$writable_dirs = [
    'data' => 'Data Directory',
    'logs' => 'Logs Directory',
];

foreach ($writable_dirs as $dir => $label) {
    $full_path = $base_path . '/' . $dir;
    if (is_dir($full_path)) {
        $writable = is_writable($full_path);
        $checks['permissions'][$label] = ['writable' => $writable, 'path' => $dir];
    }
}

// Additional security & config checks
$config_path = $base_path . '/config/config.php';
if (file_exists($config_path)) {
    $cfg = file_get_contents($config_path);
    preg_match("/define\s*\(\s*'APP_ENV'\s*,\s*'([^']+)'\s*\)/", $cfg, $m);
    $env = $m[1] ?? null;
    $checks['system']['config']['APP_ENV'] = $env;
    if ($env === 'production') {
        // If production, ensure display_errors is off
        if (ini_get('display_errors')) {
            $checks['system']['config']['display_errors'] = ['value' => ini_get('display_errors'), 'ok' => false];
            $all_passed = false;
        } else {
            $checks['system']['config']['display_errors'] = ['value' => ini_get('display_errors'), 'ok' => true];
        }
    }
}

// .htaccess check
$htaccess = $base_path . '/.htaccess';
if (file_exists($htaccess)) {
    $content = file_get_contents($htaccess);
    $checks['system']['htaccess'] = ['exists' => true, 'content' => substr($content, 0, 512)];
    if (stripos($content, 'Options -Indexes') === false) {
        $checks['system']['htaccess']['ok'] = false;
        $all_passed = false;
    } else {
        $checks['system']['htaccess']['ok'] = true;
    }
} else {
    $checks['system']['htaccess'] = ['exists' => false];
    $all_passed = false;
}

// Check DB credentials in config
$dbconf = $base_path . '/config/database.php';
if (file_exists($dbconf)) {
    $content = file_get_contents($dbconf);
    preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']*)'\s*\)/", $content, $m);
    $db_user = $m[1] ?? null;
    preg_match("/define\s*\(\s*'DB_PASS'\s*,\s*'([^']*)'\s*\)/", $content, $m2);
    $db_pass = $m2[1] ?? null;
    $checks['system']['db_conf'] = ['DB_USER' => $db_user, 'DB_PASS_set' => !empty($db_pass)];
    if ($db_user === 'root' && empty($db_pass)) {
        $checks['system']['db_conf']['ok'] = false;
        $all_passed = false;
    }
}

// Check admin users presence
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT id, username, attivo FROM utenti WHERE ruolo = 'amministratore'");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $checks['database']['admins'] = $admins;
        if (empty($admins)) {
            $checks['database']['admins_warning'] = 'No admin user found';
            $all_passed = false;
        }
    }
} catch (Exception $e) {
    // ignore if no connection
}

// Check uploads and other writable directories
$writable_dirs_more = ['assets/uploads' => 'Uploads', 'uploads' => 'Uploads Root', 'data' => 'Data Root'];
foreach ($writable_dirs_more as $p => $label) {
    $full = $base_path . '/' . $p;
    if (is_dir($full)) {
        $checks['permissions'][$label] = ['writable' => is_writable($full), 'path' => $p];
        if (!is_writable($full)) $all_passed = false;
    }
}

// 4. PHP Version Check
$php_version = phpversion();
$php_ok = version_compare($php_version, '7.0', '>=');
$checks['system']['php_version'] = ['version' => $php_version, 'compatible' => $php_ok];
if (!$php_ok) $all_passed = false;

// 5. Extensions Check
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $checks['system']['extensions'][$ext] = ['loaded' => $loaded];
    if (!$loaded) $all_passed = false;
}

// Composer and node checks
$composerJson = $base_path . '/composer.json';
$composerLock = $base_path . '/composer.lock';
if (file_exists($composerJson)) {
    $checks['system']['composer']['composer.json'] = ['exists' => true];
    $vendorExists = is_dir($base_path . '/vendor');
    $checks['system']['composer']['vendor'] = ['exists' => $vendorExists];
    if (!$vendorExists) $all_passed = false;
} else {
    $checks['system']['composer']['composer.json'] = ['exists' => false];
}

$packageJson = $base_path . '/package.json';
if (file_exists($packageJson)) {
    $checks['system']['node']['package.json'] = ['exists' => true];
    $nodeExists = is_dir($base_path . '/node_modules');
    $checks['system']['node']['node_modules'] = ['exists' => $nodeExists];
    if (!$nodeExists) $all_passed = false;
} else {
    $checks['system']['node']['package.json'] = ['exists' => false];
}

    // 6. Critical Error Patterns
$critical_patterns = [
    'call_on_non_object' => '\$this->db',
    'undefined_function' => '->query\(\$',
];

$pattern_found = false;
foreach (glob($base_path . '/pages/*.php') as $file) {
    $content = file_get_contents($file);
    foreach ($critical_patterns as $name => $pattern) {
        if (strpos($content, $pattern) !== false) {
            $checks['errors']['critical_patterns'][$name] = true;
            $pattern_found = true;
            $all_passed = false;
        }
    }
}

if (!$pattern_found) {
    $checks['errors']['critical_patterns']['status'] = 'clean';
}

// HTML Output
// If the request asks JSON (or running from CLI), provide machine-readable output
    return ['checks' => $checks, 'all_passed' => $all_passed, 'scanned_files' => $all_scanned_files];
}

// Run the checks
// Execute verification
$result = runAllChecks($base_path);
$checks = $result['checks'];
$all_passed = $result['all_passed'];
$all_scanned_files = $result['scanned_files'] ?? [];

// If the request asks JSON (or running from CLI), provide machine-readable output
if (isset($_GET['format']) && strtolower($_GET['format']) === 'json') {
    header('Content-Type: application/json');
    echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isCli()) {
    // Print a compact summary for CLI execution
    echo "\nSystem Verification Summary\n";
    echo "===========================\n";
    echo "All passed: " . ($all_passed ? 'YES' : 'NO') . "\n";
    echo "Files scanned: " . count($all_scanned_files) . "\n";
    if (!empty($checks['code_quality']['php_lint'])) echo "PHP Lint Issues: " . count($checks['code_quality']['php_lint']) . "\n";
    if (!empty($checks['code_quality']['debug_patterns'])) echo "Debug Patterns: " . count($checks['code_quality']['debug_patterns']) . "\n";
    if (!empty($checks['assets']['missing_references'])) echo "Missing assets: " . count($checks['assets']['missing_references']) . "\n";
    echo "\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge { @apply inline-flex items-center px-3 py-1 rounded-full text-sm font-medium; }
        .status-success { @apply bg-green-100 text-green-800; }
        .status-error { @apply bg-red-100 text-red-800; }
        .status-warning { @apply bg-yellow-100 text-yellow-800; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">System Verification Report</h1>
            <p class="text-gray-600">School Calendar Management System</p>
        </div>

        <!-- Overall Status -->
        <div class="mb-8 bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Overall Status</h2>
                    <p class="text-gray-600">Last checked: <?= date('d/m/Y H:i:s') ?></p>
                </div>
                <div>
                    <?php if ($all_passed): ?>
                        <div class="status-badge status-success">
                            <i class="fas fa-check-circle mr-2"></i>
                            ALL CHECKS PASSED
                        </div>
                    <?php else: ?>
                        <div class="status-badge status-error">
                            <i class="fas fa-times-circle mr-2"></i>
                            ISSUES FOUND
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- File Checks -->
        <div class="mb-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-folder mr-2"></i>Critical Files
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($checks['files'] as $label => $data): ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded">
                        <div class="flex items-center space-x-2">
                            <?php if ($data['exists']): ?>
                                <i class="fas fa-check text-green-600"></i>
                                <span class="text-gray-700"><?= $label ?></span>
                            <?php else: ?>
                                <i class="fas fa-times text-red-600"></i>
                                <span class="text-gray-700"><?= $label ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-gray-500"><?= $data['path'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Database Checks -->
        <?php if (isset($checks['database'])): ?>
        <div class="mb-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-database mr-2"></i>Database
            </h2>
            
            <!-- Connection -->
            <div class="mb-4 p-3 border border-gray-200 rounded">
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Database Connection</span>
                    <?php if ($checks['database']['connection']['success']): ?>
                        <span class="status-badge status-success">
                            <i class="fas fa-check-circle mr-1"></i>Connected
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-error">
                            <i class="fas fa-times-circle mr-1"></i>Error: <?= $checks['database']['connection']['message'] ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tables -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($checks['database']['tables'] ?? [] as $table => $data): ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded">
                        <span class="text-gray-700">Table: <code><?= $table ?></code></span>
                        <?php if ($data['exists']): ?>
                            <i class="fas fa-check text-green-600"></i>
                        <?php else: ?>
                            <i class="fas fa-times text-red-600"></i>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Database Schema Details -->
        <?php if (!empty($checks['database']['tables'])): ?>
            <div class="mb-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Database Schema Details</h3>
                <?php foreach ($checks['database']['tables'] as $tbl => $tdata): ?>
                    <div class="mb-2 p-3 border border-gray-100 rounded">
                        <div class="flex items-center justify-between">
                            <div>
                                <strong>Table: <?= $tbl ?></strong>
                                <?php if (!empty($tdata['missing_columns'])): ?>
                                    <div class="text-sm text-red-600">Missing columns: <?= implode(', ', $tdata['missing_columns']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if (!empty($tdata['exists'])): ?>
                                    <i class="fas fa-check text-green-600"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-red-600"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Permissions -->
        <div class="mb-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Permissions</h3>
            <?php if (!empty($checks['permissions'])): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($checks['permissions'] as $label => $pdata): ?>
                    <div class="p-3 border border-gray-100 rounded">
                        <div class="flex items-center justify-between">
                            <div>
                                <strong><?= htmlspecialchars($label) ?></strong>
                                <div class="text-sm text-gray-600">Path: <?= htmlspecialchars($pdata['path'] ?? '') ?></div>
                            </div>
                            <div>
                                <?php if (!empty($pdata['writable'])): ?>
                                    <i class="fas fa-check text-green-600"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-red-600"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Checks -->
        <div class="mb-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-server mr-2"></i>System Requirements
            </h2>
            
            <!-- PHP Version -->
            <div class="mb-4 p-3 border border-gray-200 rounded flex items-center justify-between">
                <span class="text-gray-700">PHP Version</span>
                <div class="text-right">
                    <span class="text-gray-600 mr-2"><?= $checks['system']['php_version']['version'] ?></span>
                    <?php if ($checks['system']['php_version']['compatible']): ?>
                        <i class="fas fa-check text-green-600"></i>
                    <?php else: ?>
                        <i class="fas fa-times text-red-600"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Extensions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($checks['system']['extensions'] ?? [] as $ext => $data): ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded">
                        <span class="text-gray-700"><?= ucfirst($ext) ?> Extension</span>
                        <?php if ($data['loaded']): ?>
                            <i class="fas fa-check text-green-600"></i>
                        <?php else: ?>
                            <i class="fas fa-times text-red-600"></i>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 p-3 border border-gray-200 rounded">
                <h4 class="text-sm font-medium text-gray-700">Config & Security</h4>
                <div class="text-sm text-gray-600">APP_ENV: <?= htmlspecialchars($checks['system']['config']['APP_ENV'] ?? 'not found') ?></div>
                <div class="text-sm text-gray-600">display_errors: <?= htmlspecialchars($checks['system']['config']['display_errors']['value'] ?? ini_get('display_errors')) ?></div>
                <div class="text-sm text-gray-600">DB User: <?= htmlspecialchars($checks['system']['db_conf']['DB_USER'] ?? '') ?>; Password set: <?= ($checks['system']['db_conf']['DB_PASS_set'] ?? false) ? 'yes' : 'no' ?></div>
            </div>

            <div class="my-4 p-3 border border-gray-200 rounded">
                <h4 class="text-sm font-medium text-gray-700">Composer / Node</h4>
                <div class="text-sm text-gray-600">composer.json: <?= $checks['system']['composer']['composer.json']['exists'] ? 'present' : 'missing' ?>; vendor: <?= ($checks['system']['composer']['vendor']['exists'] ?? false) ? 'installed' : 'not found' ?></div>
                <div class="text-sm text-gray-600">package.json: <?= $checks['system']['node']['package.json']['exists'] ? 'present' : 'missing' ?>; node_modules: <?= ($checks['system']['node']['node_modules']['exists'] ?? false) ? 'installed' : 'not found' ?></div>
            </div>
        </div>

        <!-- Code Quality -->
        <div class="mb-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-code mr-2"></i>Code Quality
            </h2>
            <?php if ($checks['errors']['critical_patterns']['status'] ?? false === 'clean'): ?>
                <div class="p-4 bg-green-50 border border-green-200 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-green-900">No Critical Errors Found</h3>
                            <p class="text-sm text-green-800">All PHP files are free of critical error patterns</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-times-circle text-red-600 mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-red-900">Critical Errors Detected</h3>
                            <p class="text-sm text-red-800">Please review error logs</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Code Quality Details -->
        <div class="mb-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Code Quality Details</h3>
            <?php if (!empty($checks['code_quality']['php_lint'])): ?>
                <div class="mb-8 flex items-center justify-between">
                    <h4 class="text-sm font-medium text-gray-700">PHP Lint Issues</h4>
                    <div>
                        <a href="?format=json" class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 rounded text-sm">JSON</a>
                    </div>
                    <ul class="list-disc pl-6 text-sm text-red-700">
                        <?php foreach ($checks['code_quality']['php_lint'] as $file => $lint): ?>
                            <li><strong><?= $file ?></strong>: <?= htmlspecialchars(substr($lint['output'] ?? '', 0, 200)) ?><?php if (isset($lint['ok']) && $lint['ok'] === false) echo ' (syntax error)'; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($checks['code_quality']['debug_patterns'])): ?>
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700">Debug/Dangerous Functions</h4>
                    <ul class="list-disc pl-6 text-sm text-yellow-800">
                        <?php foreach ($checks['code_quality']['debug_patterns'] as $file => $pats): ?>
                            <li><strong><?= $file ?></strong>: <?= implode(', ', $pats) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($checks['code_quality']['dangerous_patterns'])): ?>
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700">Dangerous / Legacy Patterns</h4>
                    <?php foreach ($checks['code_quality']['dangerous_patterns'] as $file => $patterns): ?>
                        <div class="mb-2">
                            <div class="text-sm font-medium text-gray-600"><?= $file ?></div>
                            <ul class="list-disc pl-6 text-xs text-gray-800">
                                <?php foreach ($patterns as $pname => $entries): ?>
                                    <?php foreach ($entries as $e): ?>
                                        <li><span class="font-mono">L<?= $e['line'] ?></span> <strong><?= htmlspecialchars($pname) ?></strong>: <?= htmlspecialchars($e['snippet']) ?></li>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($checks['assets']['missing_references'])): ?>
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700">Missing Asset References</h4>
                    <ul class="list-disc pl-6 text-sm text-red-700">
                        <?php foreach ($checks['assets']['missing_references'] as $a): ?>
                            <li><strong><?= $a['file'] ?></strong> refers to <code><?= $a['ref'] ?></code> (expected <code><?= $a['target'] ?></code>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($checks['code_quality']['todos'])): ?>
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700">TODO / FIXME</h4>
                    <?php foreach ($checks['code_quality']['todos'] as $file => $todos): ?>
                        <div class="mb-2">
                            <div class="text-sm font-medium text-gray-600"><?= $file ?></div>
                            <ul class="list-disc pl-6 text-xs text-gray-800">
                                <?php foreach ($todos as $t): ?>
                                    <li><span class="font-mono">L<?= $t['line'] ?></span>: <?= htmlspecialchars($t['snippet']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($checks['code_quality']['warnings'])): ?>
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700">Warnings (die/exit/short tag)</h4>
                    <?php foreach ($checks['code_quality']['warnings'] as $file => $ws): ?>
                        <div class="mb-2">
                            <div class="text-sm font-medium text-gray-600"><?= $file ?></div>
                            <ul class="list-disc pl-6 text-xs text-gray-800">
                                <?php foreach ($ws as $w): ?>
                                    <li><?= htmlspecialchars($w) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700">Scanned Files</h4>
                <p class="text-sm text-gray-600">Total scanned files: <?= count($all_scanned_files) ?></p>
            </div>
        </div>

        <!-- Summary -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Summary</h2>
            <div class="space-y-3">
                <div class="flex justify-between text-gray-700">
                    <span>Files Present:</span>
                    <strong><?php $filtered = array_filter($checks['files'], function($c) { return $c['exists']; }); echo count($filtered) . '/' . count($checks['files']); ?></strong>
                </div>
                <div class="flex justify-between text-gray-700">
                    <span>Database Tables:</span>
                    <strong><?php $filtered = array_filter($checks['database']['tables'] ?? [], function($c) { return $c['exists']; }); echo count($filtered) . '/' . count($checks['database']['tables'] ?? []); ?></strong>
                </div>
                <div class="flex justify-between text-gray-700">
                    <span>PHP Extensions:</span>
                    <strong><?php $filtered = array_filter($checks['system']['extensions'] ?? [], function($c) { return $c['loaded']; }); echo count($filtered) . '/' . count($checks['system']['extensions'] ?? []); ?></strong>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-200">
                <?php if ($all_passed): ?>
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-green-800 font-semibold">
                            <i class="fas fa-thumbs-up mr-2"></i>
                            System is fully operational and ready for use
                        </p>
                    </div>
                <?php else: ?>
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-red-800 font-semibold">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Please resolve issues before deploying to production
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-12 text-center text-gray-600 text-sm">
            <p>School Calendar Management System - Verification Report</p>
            <p><?= date('d/m/Y H:i:s') ?></p>
        </div>
    </div>
</body>
</html>
