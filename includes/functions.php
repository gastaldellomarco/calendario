<?php
/**
 * Funzioni di utilità per l'autenticazione e sicurezza
 * Sono protette da if (!function_exists(...)) per evitare ridefinizioni
 */

/**
 * Verifica se l'utente è loggato
 * @return bool
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

/**
 * Restituisce i dati dell'utente corrente
 * @return array|null Array con dati utente o null se non loggato
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (!isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'ruolo' => $_SESSION['ruolo'],
            'nome_visualizzato' => $_SESSION['nome_visualizzato'] ?? $_SESSION['username']
        ];
    }
}

/**
 * Verifica se l'utente ha un determinato ruolo
 * @param string|array $ruolo Ruolo o array di ruoli da verificare
 * @return bool
 */
if (!function_exists('hasRole')) {
    function hasRole($ruolo) {
        if (!isset($_SESSION['ruolo'])) {
            return false;
        }
        
        if (is_array($ruolo)) {
            return in_array($_SESSION['ruolo'], $ruolo);
        }
        
        return $_SESSION['ruolo'] === $ruolo;
    }
}

/**
 * Sanitizza input per prevenire XSS
 * @param mixed $data Dati da sanitizzare
 * @return mixed Dati sanitizzati
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        
        // Gestisci null e valori non stringa
        if ($data === null) {
            return '';
        }
        
        if (!is_string($data)) {
            $data = (string) $data;
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Redirect a URL specifico
 * @param string $url URL di destinazione
 */
if (!function_exists('redirectTo')) {
    function redirectTo($url) {
        header('Location: ' . $url);
        exit;
    }
}

// Alias compatibile: se redirect() non esiste, crealo come alias di redirectTo
if (!function_exists('redirect')) {
    function redirect($url) {
        redirectTo($url);
    }
}

/**
 * Genera token CSRF
 * @return string Token CSRF
 */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verifica token CSRF
 * @param string $token Token da verificare
 * @return bool True se valido
 */
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('requireCsrfToken')) {
    function requireCsrfToken($token) {
        if (empty($token) || !verifyCsrfToken($token)) {
            throw new Exception('Token CSRF non valido');
        }
    }
}

/**
 * Log attività utente
 * @param string $azione Azione compiuta
 * @param string $dettagli Dettagli aggiuntivi
 */
if (!function_exists('logActivity')) {
    function logActivity($azione, $dettagli = '') {
        $user = getCurrentUser();
        $logMessage = sprintf(
            "[%s] User: %s (ID: %d) - Action: %s - Details: %s - IP: %s",
            date('Y-m-d H:i:s'),
            $user['username'] ?? 'Unknown',
            $user['id'] ?? 0,
            $azione,
            $dettagli,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        );
        
        error_log($logMessage);
    }
}

/**
 * Verifica forza password
 * @param string $password Password da verificare
 * @return array Risultato verifica
 */
if (!function_exists('checkPasswordStrength')) {
    function checkPasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "La password deve essere di almeno 8 caratteri";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "La password deve contenere almeno una lettera maiuscola";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "La password deve contenere almeno una lettera minuscola";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "La password deve contenere almeno un numero";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "La password deve contenere almeno un carattere speciale";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

/**
 * Ottiene il ruolo in formato leggibile
 * @param string $ruolo Codice ruolo
 * @return string Ruolo formattato
 */
if (!function_exists('getRoleDisplayName')) {
    function getRoleDisplayName($ruolo) {
        $roles = [
            'amministratore' => 'Amministratore Sistema',
            'preside' => 'Preside',
            'vice_preside' => 'Vice Preside',
            'segreteria' => 'Segreteria',
            'docente' => 'Docente'
        ];
        
        return $roles[$ruolo] ?? $ruolo;
    }
}

/**
 * Richiede autenticazione e verifica ruolo specifico
 * @param string|array $ruolo_richiesto Ruolo o array di ruoli richiesti
 * @return void Redirect se non autenticato o non autorizzato
 */
if (!function_exists('requireAuth')) {
    function requireAuth($ruolo_richiesto = null) {
        // Verifica autenticazione base
        if (!isset($_SESSION['user_id'])) {
            // Verifica cookie "Ricordami"
            if (isset($_COOKIE['remember_token'])) {
                require_once __DIR__ . '/../config/database.php';
                
                try {
                    $pdo = getPDOConnection();
                    $sql = "SELECT u.* FROM utenti u WHERE u.remember_token = :token AND u.attivo = 1 LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':token', $_COOKIE['remember_token'], PDO::PARAM_STR);
                    $stmt->execute();
                    
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        // Rigenera sessione
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['ruolo'] = $user['ruolo'];
                        $_SESSION['nome_visualizzato'] = $user['nome_visualizzato'];
                        $_SESSION['user_name'] = $user['nome_visualizzato'] ?? $user['username'];
                        $_SESSION['login_time'] = time();
                        
                        // Aggiorna ultimo accesso
                        $sql = "UPDATE utenti SET ultimo_accesso = NOW() WHERE id = :user_id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                        $stmt->execute();
                    } else {
                        // Token non valido, cancella cookie
                        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                        header('Location: ' . BASE_URL . '/auth/login.php');
                        exit;
                    }
                } catch (Exception $e) {
                    error_log("Errore verifica remember token: " . $e->getMessage());
                    header('Location: ' . BASE_URL . '/auth/login.php');
                    exit;
                }
            } else {
                // Non autenticato e nessun cookie remember
                header('Location: ' . BASE_URL . '/auth/login.php');
                exit;
            }
        }
        
        // Verifica timeout sessione (30 minuti)
        if (isset($_SESSION['login_time'])) {
            $timeout_duration = 1800;
            $elapsed_time = time() - $_SESSION['login_time'];
            if ($elapsed_time > $timeout_duration) {
                session_unset();
                session_destroy();
                header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
                exit;
            } else {
                // Rinnova tempo sessione
                $_SESSION['login_time'] = time();
            }
        }
        
        // Se è specificato un ruolo, verifica che l'utente lo abbia
        if ($ruolo_richiesto !== null) {
            $ruoli_richiesti = is_array($ruolo_richiesto) ? $ruolo_richiesto : [$ruolo_richiesto];
            
            // Gli amministratori hanno accesso a tutto
            if (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'amministratore') {
                return;
            }
            
            // Verifica se l'utente ha uno dei ruoli richiesti
            if (!isset($_SESSION['ruolo']) || !in_array($_SESSION['ruolo'], $ruoli_richiesti)) {
                header('Location: ' . BASE_URL . '/unauthorized.php');
                exit;
            }
        }
    }
}