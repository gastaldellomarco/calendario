<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'sedi_form';
$page_title = 'Modifica Sede';

// Verifica permessi
checkRole(['amministratore', 'preside']);

$sede_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sede = null;

try {
    $pdo = getPDOConnection();
    
    // Carica sede se in modifica
    if ($sede_id) {
        $stmt = $pdo->prepare("SELECT * FROM sedi WHERE id = ?");
        $stmt->execute([$sede_id]);
        $sede = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sede) {
            $_SESSION['error'] = "Sede non trovata";
            header('Location: sedi.php');
            exit;
        }
    }
    
} catch (Exception $e) {
    error_log("Errore: " . $e->getMessage());
    $_SESSION['error'] = "Errore nel caricamento dati";
    header('Location: sedi.php');
    exit;
}

// Gestione form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verifica CSRF token per richieste POST
        $csrf_token_post = $_POST['csrf_token'] ?? '';
        if (empty($csrf_token_post) || !verifyCsrfToken($csrf_token_post)) {
            throw new Exception('Token CSRF non valido');
        }
        $nome = sanitizeInput($_POST['nome'] ?? '');
        $indirizzo = sanitizeInput($_POST['indirizzo'] ?? '');
        $citta = sanitizeInput($_POST['citta'] ?? '');
        $provincia = sanitizeInput($_POST['provincia'] ?? '');
        $cap = sanitizeInput($_POST['cap'] ?? '');
        $telefono = sanitizeInput($_POST['telefono'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $responsabile = sanitizeInput($_POST['responsabile'] ?? '');
        $note = sanitizeInput($_POST['note'] ?? '');
        $attiva = (int)($_POST['attiva'] ?? 1);
        
        if (empty($nome) || empty($citta)) {
            $error = "Nome e Città sono obbligatori";
        } else {
            if ($sede_id) {
                // Modifica
                $stmt = $pdo->prepare("UPDATE sedi SET nome = ?, indirizzo = ?, citta = ?, provincia = ?, cap = ?, telefono = ?, email = ?, responsabile = ?, note = ?, attiva = ? WHERE id = ?");
                $stmt->execute([$nome, $indirizzo, $citta, $provincia, $cap, $telefono, $email, $responsabile, $note, $attiva, $sede_id]);
                $success = "Sede modificata con successo";
            } else {
                // Inserimento
                $stmt = $pdo->prepare("INSERT INTO sedi (nome, indirizzo, citta, provincia, cap, telefono, email, responsabile, note, attiva) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $indirizzo, $citta, $provincia, $cap, $telefono, $email, $responsabile, $note, $attiva]);
                $success = "Sede aggiunta con successo";
                $sede_id = $pdo->lastInsertId();
            }
        }
    } catch (PDOException $e) {
        error_log("Errore salvataggio: " . $e->getMessage());
        $error = "Errore nel salvataggio: " . $e->getMessage();
    }
}

// Eliminazione: preferisci POST per sicurezza, fallback GET per compatibilità
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && $_POST['delete'] == 1 && $sede_id) {
    try {
        // Verifica se sede ha classi
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classi WHERE sede_id = ?");
        $stmt->execute([$sede_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $error = "Non è possibile eliminare: $count classi assegnate";
        } else {
            $stmt = $pdo->prepare("DELETE FROM sedi WHERE id = ?");
            $stmt->execute([$sede_id]);
            $_SESSION['success'] = "Sede eliminata con successo";
            header('Location: sedi.php');
            exit;
        }
    } catch (PDOException $e) {
        $error = "Errore: " . $e->getMessage();
        } catch (PDOException $e) {
            $error = "Errore: " . $e->getMessage();
        }
    } elseif (isset($_GET['delete']) && $_GET['delete'] == 1 && $sede_id) {
        // Fallback con GET (legacy). Mantenuto per compatibilità ma sconsigliato.
        try {
            // Verifica se sede ha classi
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classi WHERE sede_id = ?");
            $stmt->execute([$sede_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
            if ($count > 0) {
                $error = "Non è possibile eliminare: $count classi assegnate";
            } else {
                $stmt = $pdo->prepare("DELETE FROM sedi WHERE id = ?");
                $stmt->execute([$sede_id]);
                $_SESSION['success'] = "Sede eliminata con successo";
                header('Location: sedi.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Errore: " . $e->getMessage();
        }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= $sede ? 'Modifica Sede' : 'Nuova Sede' ?></h1>
            <p class="text-gray-600 mt-1"><?= $sede ? htmlspecialchars($sede['nome']) : 'Crea una nuova sede' ?></p>
        </div>
        <a href="sedi.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-2"></i>Torna</a>
    </div>

    <!-- Messaggi -->
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
            <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
            <span class="text-red-800"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
            <i class="fas fa-check-circle text-green-400 mr-2"></i>
            <span class="text-green-800"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" class="space-y-6">
            <!-- Riga 1: Nome e Città -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome Sede *</label>
                    <input type="text" name="nome" value="<?= $sede ? htmlspecialchars($sede['nome']) : '' ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: Sede Centrale">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Città *</label>
                    <input type="text" name="citta" value="<?= $sede ? htmlspecialchars($sede['citta']) : '' ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: Milano">
                </div>
            </div>

            <!-- Riga 2: Indirizzo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Indirizzo</label>
                <input type="text" name="indirizzo" value="<?= $sede ? htmlspecialchars($sede['indirizzo']) : '' ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="Es: Via Roma 10">
            </div>

            <!-- Riga 3: Provincia e CAP -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
                    <input type="text" name="provincia" value="<?= $sede ? htmlspecialchars($sede['provincia']) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: MI" maxlength="2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CAP</label>
                    <input type="text" name="cap" value="<?= $sede ? htmlspecialchars($sede['cap']) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: 20100">
                </div>
            </div>

            <!-- Riga 4: Telefono e Email -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                    <input type="tel" name="telefono" value="<?= $sede ? htmlspecialchars($sede['telefono']) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: 02 1234 5678">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= $sede ? htmlspecialchars($sede['email']) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: info@scuola.it">
                </div>
            </div>

            <!-- Riga 5: Responsabile -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Responsabile Sede</label>
                <input type="text" name="responsabile" value="<?= $sede ? htmlspecialchars($sede['responsabile']) : '' ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="Es: Prof. Rossi">
            </div>

            <!-- Note -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                <textarea name="note" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Note aggiuntive sulla sede..."><?= $sede ? htmlspecialchars($sede['note']) : '' ?></textarea>
            </div>

            <!-- Stato -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <input type="checkbox" name="attiva" value="1" <?= (!$sede || $sede['attiva']) ? 'checked' : '' ?> class="mr-2">
                    Sede Attiva
                </label>
            </div>

            <!-- Bottoni -->
            <div class="flex justify-between items-center pt-6 border-t">
                <div>
                    <?php if ($sede): ?>
                        <form method="POST" onsubmit="return confirm('Eliminare questa sede?')" style="display:inline">
                            <input type="hidden" name="delete" value="1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
                            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                                <i class="fas fa-trash mr-2"></i>Elimina
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="space-x-2">
                    <a href="sedi.php" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">Annulla</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        <i class="fas fa-save mr-2"></i><?= $sede ? 'Salva' : 'Crea' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
