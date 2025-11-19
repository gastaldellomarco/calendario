<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'giorni_chiusura_form';
$page_title = 'Modifica Giorno di Chiusura';

// Verifica permessi
checkRole(['amministratore', 'preside', 'vice_preside']);

$giorno_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$giorno = null;

try {
    $pdo = getPDOConnection();
    
    // Carica giorno se in modifica
    if ($giorno_id) {
        $stmt = $pdo->prepare("SELECT * FROM giorni_chiusura WHERE id = ?");
        $stmt->execute([$giorno_id]);
        $giorno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$giorno) {
            $_SESSION['error'] = "Giorno di chiusura non trovato";
            header('Location: giorni_chiusura.php');
            exit;
        }
    }
    
} catch (Exception $e) {
    error_log("Errore: " . $e->getMessage());
    $_SESSION['error'] = "Errore nel caricamento dati";
    header('Location: giorni_chiusura.php');
    exit;
}

// Gestione form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data_chiusura = sanitizeInput($_POST['data_chiusura'] ?? '');
        $motivo = sanitizeInput($_POST['motivo'] ?? '');
        $tipo = sanitizeInput($_POST['tipo'] ?? 'giornata');
        $ricorrente_annuale = isset($_POST['ricorrente_annuale']) ? 1 : 0;
        $note = sanitizeInput($_POST['note'] ?? '');
        
        if (empty($data_chiusura) || empty($motivo)) {
            $error = "Data e Motivo sono obbligatori";
        } else {
            if ($giorno_id) {
                // Modifica
                $stmt = $pdo->prepare("UPDATE giorni_chiusura SET data_chiusura = ?, motivo = ?, tipo = ?, ricorrente_annuale = ?, note = ? WHERE id = ?");
                $stmt->execute([$data_chiusura, $motivo, $tipo, $ricorrente_annuale, $note, $giorno_id]);
                $success = "Giorno di chiusura modificato con successo";
            } else {
                // Inserimento
                $stmt = $pdo->prepare("INSERT INTO giorni_chiusura (data_chiusura, motivo, tipo, ricorrente_annuale, note) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$data_chiusura, $motivo, $tipo, $ricorrente_annuale, $note]);
                $success = "Giorno di chiusura aggiunto con successo";
                $giorno_id = $pdo->lastInsertId();
            }
        }
    } catch (PDOException $e) {
        error_log("Errore salvataggio: " . $e->getMessage());
        $error = "Errore nel salvataggio: " . $e->getMessage();
    }
}

// Eliminazione
if (isset($_GET['delete']) && $_GET['delete'] == 1 && $giorno_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM giorni_chiusura WHERE id = ?");
        $stmt->execute([$giorno_id]);
        $_SESSION['success'] = "Giorno di chiusura eliminato con successo";
        header('Location: giorni_chiusura.php');
        exit;
    } catch (PDOException $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= $giorno ? 'Modifica Giorno di Chiusura' : 'Nuovo Giorno di Chiusura' ?></h1>
            <p class="text-gray-600 mt-1"><?= $giorno ? htmlspecialchars($giorno['motivo']) : 'Aggiungi un nuovo giorno di chiusura' ?></p>
        </div>
        <a href="giorni_chiusura.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-2"></i>Torna</a>
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
            <!-- Data -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data di Chiusura *</label>
                <input type="date" name="data_chiusura" value="<?= $giorno ? htmlspecialchars($giorno['data_chiusura']) : '' ?>" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-sm text-gray-500 mt-1">Formato: AAAA-MM-GG</p>
            </div>

            <!-- Motivo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo della Chiusura *</label>
                <input type="text" name="motivo" value="<?= $giorno ? htmlspecialchars($giorno['motivo']) : '' ?>" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="Es: Festività, Ponte, Chiusura Amministrativa">
            </div>

            <!-- Tipo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo di Chiusura</label>
                <select name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="giornata" <?= (!$giorno || $giorno['tipo'] == 'giornata') ? 'selected' : '' ?>>Giornata Intera</option>
                    <option value="mezza" <?= ($giorno && $giorno['tipo'] == 'mezza') ? 'selected' : '' ?>>Mezza Giornata</option>
                    <option value="periodo" <?= ($giorno && $giorno['tipo'] == 'periodo') ? 'selected' : '' ?>>Periodo</option>
                    <option value="festivo" <?= ($giorno && $giorno['tipo'] == 'festivo') ? 'selected' : '' ?>>Festivo</option>
                </select>
            </div>

            <!-- Ricorrente -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <input type="checkbox" name="ricorrente_annuale" value="1" <?= ($giorno && $giorno['ricorrente_annuale']) ? 'checked' : '' ?> class="mr-2">
                    Si ripete ogni anno (Es: Capodanno, Pasqua)
                </label>
            </div>

            <!-- Note -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                <textarea name="note" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Note aggiuntive..."><?= $giorno ? htmlspecialchars($giorno['note']) : '' ?></textarea>
            </div>

            <!-- Bottoni -->
            <div class="flex justify-between items-center pt-6 border-t">
                <div>
                    <?php if ($giorno): ?>
                        <a href="?id=<?= $giorno['id'] ?>&delete=1" 
                           onclick="return confirm('Eliminare questo giorno di chiusura?')"
                           class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                            <i class="fas fa-trash mr-2"></i>Elimina
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="space-x-2">
                    <a href="giorni_chiusura.php" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">Annulla</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        <i class="fas fa-save mr-2"></i><?= $giorno ? 'Salva' : 'Crea' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
