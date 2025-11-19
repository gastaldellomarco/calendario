<?php
// File di test per verificare la connessione al database
require_once 'config/database.php';

echo "<h2>Test Connessione Database</h2>";

try {
    $pdo = getPDOConnection();
    echo "<p style='color:green'>✓ Connessione al database riuscita!</p>";
    
    // Test query utenti
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM utenti");
    $result = $stmt->fetch();
    echo "<p>Utenti nel database: " . $result['total'] . "</p>";
    
    // Test utente admin
    $stmt = $pdo->query("SELECT id, username, email, ruolo FROM utenti WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color:green'>✓ Utente admin trovato:</p>";
        echo "<pre>" . print_r($admin, true) . "</pre>";
    } else {
        echo "<p style='color:red'>✗ Utente admin non trovato</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Errore connessione: " . $e->getMessage() . "</p>";
    echo "<p>Verifica che:<br>
          - XAMPP sia avviato (Apache e MySQL)<br>
          - Il database 'scuola_calendario' esista<br>
          - Le credenziali siano corrette (root / password vuota)</p>";
}
?>