<?php
session_start();
require_once '../config/database.php';

// Distrugge tutti i dati della sessione
$_SESSION = [];

// Se si desidera distruggere completamente la sessione, cancella anche il cookie di sessione
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Distruggi sessione
session_destroy();

// Cancella cookie "Ricordami" se esiste
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    
    // Opzionale: rimuovi token dal database
    try {
        $pdo = getPDOConnection();
        $sql = "UPDATE utenti SET remember_token = NULL WHERE remember_token = :token";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':token', $_COOKIE['remember_token'], PDO::PARAM_STR);
        $stmt->execute();
    } catch (Exception $e) {
        // Log errore ma procedi comunque con il logout
        error_log("Errore durante la pulizia del token remember: " . $e->getMessage());
    }
}

// Redirect alla pagina di login
header('Location: login.php');
exit;