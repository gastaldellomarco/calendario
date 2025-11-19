<?php
require_once '../config/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Verifica metodo POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo non consentito');
    }

    // Validazione input di base
    $username = sanitizeInput(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Verifica CSRF token
    if (empty($csrf_token) || !verifyCsrfToken($csrf_token)) {
        throw new Exception('Token CSRF non valido.');
    }

    // Validazione campi obbligatori
    if (empty($username) || empty($password)) {
        throw new Exception('Tutti i campi sono obbligatori');
    }

    // PROVA CONNESSIONE DATABASE
    try {
        $pdo = getPDOConnection();
        error_log("Connessione DB riuscita");
    } catch (Exception $e) {
        error_log("Connessione DB fallita: " . $e->getMessage());
        throw new Exception('Database non raggiungibile. Controlla XAMPP.');
    }

    // Query per trovare l'utente - CORRETTA
    $sql = "SELECT u.*, d.cognome, d.nome 
            FROM utenti u 
            LEFT JOIN docenti d ON u.docente_id = d.id 
            WHERE (u.username = :username OR u.email = :email) 
            AND u.attivo = 1 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':email', $username, PDO::PARAM_STR); // Usa lo stesso valore per entrambi
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("Utente non trovato: " . $username);
        throw new Exception('Credenziali non valide');
    }

    // Verifica password
    if (!password_verify($password, $user['password_hash'])) {
        error_log("Password errata per: " . $username);
        throw new Exception('Credenziali non valide');
    }

    // Login riuscito
    // Rigenera id sessione per evitare session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['ruolo'] = $user['ruolo'];
    $_SESSION['nome_visualizzato'] = $user['nome_visualizzato'] ?? ($user['cognome'] . ' ' . $user['nome']);
    $_SESSION['login_time'] = time();

    // Aggiorna ultimo accesso
    $sql = "UPDATE utenti SET ultimo_accesso = NOW() WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();

    error_log("Login riuscito per: " . $username);

    $response['success'] = true;
    $response['message'] = 'Login effettuato con successo';
    $response['redirect'] = BASE_URL . '/dashboard.php';

    // Se richiesto, genera token "ricordami" e salva nel DB e cookie
    if ($remember_me) {
        try {
            $remember_token = bin2hex(random_bytes(32));
            $sql = "UPDATE utenti SET remember_token = :token WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':token', $remember_token, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
            $stmt->execute();

            // Cookie settato con durata 30 giorni
            setcookie('remember_token', $remember_token, time() + 60*60*24*30, '/', '', isset($_SERVER['HTTPS']), true);
        } catch (Exception $e) {
            error_log('Impossibile impostare il cookie remember: ' . $e->getMessage());
        }
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("ERRORE LOGIN: " . $e->getMessage());
}

echo json_encode($response);