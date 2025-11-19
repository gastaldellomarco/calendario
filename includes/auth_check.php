<?php
/**
 * Middleware di autenticazione
 * Da includere all'inizio di ogni pagina protetta
 */

// Includi configurazione principale
require_once __DIR__ . '/../config/config.php';

// Timeout sessione (30 minuti)
$timeout_duration = 1800;

if (isset($_SESSION['login_time'])) {
    $elapsed_time = time() - $_SESSION['login_time'];
    if ($elapsed_time > $timeout_duration) {
        // Sessione scaduta
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
        exit;
    } else {
        // Rinnova tempo sessione
        $_SESSION['login_time'] = time();
    }
}

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
                $_SESSION['user_name'] = $user['nome_visualizzato'] ?? $user['username']; // Compatibilità
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

/**
 * Verifica se l'utente ha uno dei ruoli specificati
 * @param array $ruoli_ammessi Array di ruoli consentiti
 * @return bool True se autorizzato, altrimenti redirect a pagina non autorizzata
 */
function checkRole($ruoli_ammessi) {
    if (!isset($_SESSION['ruolo']) || !in_array($_SESSION['ruolo'], $ruoli_ammessi)) {
        header('Location: ' . BASE_URL . '/unauthorized.php');
        exit;
    }
    return true;
}

/**
 * Verifica permesso per azione specifica
 * @param string $azione Azione da verificare
 * @return bool True se autorizzato
 */
function hasPermission($azione) {
    $permessi = [
        'amministratore' => ['*'], // Tutti i permessi
        'preside' => ['gestione_docenti', 'gestione_classi', 'visualizza_report', 'modifica_calendario'],
        'vice_preside' => ['gestione_classi', 'visualizza_report', 'modifica_calendario'],
        'segreteria' => ['visualizza_dati', 'gestione_studenti', 'stampa_documenti'],
        'docente' => ['visualizza_calendario', 'modifica_lezione', 'inserisci_voto']
    ];
    
    $ruolo = $_SESSION['ruolo'];
    
    return isset($permessi[$ruolo]) && 
           (in_array('*', $permessi[$ruolo]) || in_array($azione, $permessi[$ruolo]));
}