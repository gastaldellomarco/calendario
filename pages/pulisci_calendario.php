<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

// Solo amministratori possono pulire il calendario
if (getLoggedUserRole() !== 'amministratore') {
    $_SESSION['error'] = "Non hai i permessi per eseguire questa operazione";
    redirect('/scuola/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token_post) || !verifyCsrfToken($csrf_token_post)) {
        $_SESSION['error'] = 'Token CSRF non valido';
        redirect('/scuola/pages/calendario.php');
    }
    try {
        $db->beginTransaction();

        // Conta lezioni prima della pulizia
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM calendario_lezioni");
        $stmt->execute();
        $lezioni_prima = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM conflitti_orario");
        $stmt->execute();
        $conflitti_prima = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Elimina i dati
        $db->exec("DELETE FROM calendario_lezioni");
        $db->exec("DELETE FROM conflitti_orario");

        // Resetta auto-increment
        $db->exec("ALTER TABLE calendario_lezioni AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE conflitti_orario AUTO_INCREMENT = 1");

        $db->commit();

        $_SESSION['success'] = "Calendario pulito con successo! Eliminate: " . 
                             $lezioni_prima . " lezioni e " . 
                             $conflitti_prima . " conflitti";

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Errore durante la pulizia: " . $e->getMessage();
    }

    redirect('/scuola/pages/calendario.php');
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulisci Calendario - Sistema Gestione Scuola</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <h1>🧹 Pulizia Calendario</h1>

        <div class="alert alert-warning">
            <h4>⚠️ Attenzione!</h4>
            <p>Questa operazione eliminerà <strong>TUTTE</strong> le lezioni e i conflitti dal calendario.</p>
            <p>L'operazione non può essere annullata.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Statistiche Attuali</h3>
            </div>
            <div class="card-body">
                <?php
                $stmt_lezione = $db->prepare("SELECT COUNT(*) as total FROM calendario_lezioni");
                $stmt_lezione->execute();
                $lezioni_totali = $stmt_lezione->fetch(PDO::FETCH_ASSOC)['total'];

                $stmt_conflitti = $db->prepare("SELECT COUNT(*) as total FROM conflitti_orario");
                $stmt_conflitti->execute();
                $conflitti_totali = $stmt_conflitti->fetch(PDO::FETCH_ASSOC)['total'];
                ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box">
                            <h4>Lezioni nel calendario</h4>
                            <div class="number"><?php echo $lezioni_totali; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <h4>Conflitti registrati</h4>
                            <div class="number"><?php echo $conflitti_totali; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare TUTTE le lezioni e i conflitti?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="conferma" required>
                    Confermo di voler eliminare tutti i dati del calendario
                </label>
            </div>

            <button type="submit" class="btn btn-danger btn-lg">
                🗑️ ELIMINA TUTTI I DATI
            </button>

            <a href="calendario.php" class="btn btn-secondary">Annulla</a>
        </form>
    </div>
</body>
</html>