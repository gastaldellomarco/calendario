<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Solo per ambiente di sviluppo - disabilita in produzione
if (defined('APP_ENV') && APP_ENV === 'production') {
    http_response_code(403);
    echo 'Registrazione disabilitata in produzione';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !verifyCsrfToken($csrf_token)) {
        throw new Exception('Token CSRF non valido');
    }
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $ruolo = $_POST['ruolo'] ?? 'docente';
        $nome_visualizzato = trim($_POST['nome_visualizzato'] ?? '');

        // Validazioni
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            throw new Exception('Tutti i campi sono obbligatori');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new Exception('Username può contenere solo lettere, numeri, trattini e underscore');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Formato email non valido');
        }

        if (strlen($password) < 8) {
            throw new Exception('La password deve essere di almeno 8 caratteri');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Le password non coincidono');
        }

        if (!in_array($ruolo, ['preside', 'vice_preside', 'segreteria', 'docente', 'amministratore'])) {
            throw new Exception('Ruolo non valido');
        }

        $pdo = getPDOConnection();

        // Verifica se username o email già esistenti
        $sql = "SELECT id FROM utenti WHERE username = :username OR email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->fetch()) {
            throw new Exception('Username o email già in uso');
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Inserimento utente
        $sql = "INSERT INTO utenti (username, email, password_hash, ruolo, nome_visualizzato, attivo, created_at, updated_at) 
                VALUES (:username, :email, :password_hash, :ruolo, :nome_visualizzato, 1, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
        $stmt->bindParam(':ruolo', $ruolo, PDO::PARAM_STR);
        $stmt->bindParam(':nome_visualizzato', $nome_visualizzato, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $success = 'Utente registrato con successo! <a href="login.php" class="text-blue-600 hover:underline">Accedi qui</a>';
        } else {
            throw new Exception('Errore durante la registrazione');
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - Sistema Gestione Scuola</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Registrazione</h2>
            <p class="text-sm text-red-600 mt-2">SOLO PER TESTING - Disabilitare in produzione</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700">Username *</label>
                <input type="text" name="username" required 
                    class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Email *</label>
                <input type="email" name="email" required 
                    class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nome Visualizzato</label>
                <input type="text" name="nome_visualizzato" 
                    class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="<?php echo htmlspecialchars($_POST['nome_visualizzato'] ?? ''); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Ruolo *</label>
                <select name="ruolo" required 
                    class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="docente" <?php echo ($_POST['ruolo'] ?? '') === 'docente' ? 'selected' : ''; ?>>Docente</option>
                    <option value="segreteria" <?php echo ($_POST['ruolo'] ?? '') === 'segreteria' ? 'selected' : ''; ?>>Segreteria</option>
                    <option value="vice_preside" <?php echo ($_POST['ruolo'] ?? '') === 'vice_preside' ? 'selected' : ''; ?>>Vice Preside</option>
                    <option value="preside" <?php echo ($_POST['ruolo'] ?? '') === 'preside' ? 'selected' : ''; ?>>Preside</option>
                    <option value="amministratore" <?php echo ($_POST['ruolo'] ?? '') === 'amministratore' ? 'selected' : ''; ?>>Amministratore</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Password * (min 8 caratteri)</label>
                <input type="password" name="password" required minlength="8"
                    class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Conferma Password *</label>
                <input type="password" name="confirm_password" required minlength="8"
                    class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <button type="submit" 
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Registrati
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="login.php" class="text-blue-600 hover:text-blue-500 text-sm">
                ← Torna al Login
            </a>
        </div>
    </div>
</body>
</html>