<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'aule';
$page_title = 'Modifica Aula';

// Verifica permessi
checkRole(['amministratore', 'preside', 'vice_preside']);

$aula_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$aula = null;

try {
    $pdo = getPDOConnection();
    
    // Carica aula se in modifica
    if ($aula_id) {
        $stmt = $pdo->prepare("SELECT a.*, s.nome as sede_nome FROM aule a JOIN sedi s ON a.sede_id = s.id WHERE a.id = ?");
        $stmt->execute([$aula_id]);
        $aula = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$aula) {
            $_SESSION['error'] = "Aula non trovata";
            header('Location: aule.php');
            exit;
        }
    }
    
    // Carica sedi
    $stmt = $pdo->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome");
    $sedi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Errore: " . $e->getMessage());
    $_SESSION['error'] = "Errore nel caricamento dati";
    header('Location: aule.php');
    exit;
}

// Gestione form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sede_id = (int)$_POST['sede_id'];
        $nome = sanitizeInput($_POST['nome'] ?? '');
        $codice = sanitizeInput($_POST['codice'] ?? '');
        $tipo = sanitizeInput($_POST['tipo'] ?? '');
        $capienza = (int)($_POST['capienza'] ?? 25);
        $piano = sanitizeInput($_POST['piano'] ?? '');
        $attrezzature = sanitizeInput($_POST['attrezzature'] ?? '');
        $note = sanitizeInput($_POST['note'] ?? '');
        $attiva = isset($_POST['attiva']) ? 1 : 0;
        
        if (empty($nome) || empty($codice) || $sede_id <= 0) {
            $error = "Compilare tutti i campi obbligatori";
        } else {
            if ($aula_id) {
                // Modifica
                $stmt = $pdo->prepare("UPDATE aule SET sede_id = ?, nome = ?, codice = ?, tipo = ?, capienza = ?, piano = ?, attrezzature = ?, note = ?, attiva = ? WHERE id = ?");
                $stmt->execute([$sede_id, $nome, $codice, $tipo, $capienza, $piano, $attrezzature, $note, $attiva, $aula_id]);
                $success = "Aula modificata con successo";
            } else {
                // Inserimento
                $stmt = $pdo->prepare("INSERT INTO aule (sede_id, nome, codice, tipo, capienza, piano, attrezzature, note, attiva) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sede_id, $nome, $codice, $tipo, $capienza, $piano, $attrezzature, $note, $attiva]);
                $success = "Aula aggiunta con successo";
                $aula_id = $pdo->lastInsertId();
                $aula = [
                    'id' => $aula_id,
                    'sede_id' => $sede_id,
                    'nome' => $nome,
                    'codice' => $codice,
                    'tipo' => $tipo,
                    'capienza' => $capienza,
                    'piano' => $piano,
                    'attrezzature' => $attrezzature,
                    'note' => $note,
                    'attiva' => $attiva
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Errore salvataggio: " . $e->getMessage());
        $error = "Errore nel salvataggio: " . $e->getMessage();
    }
}

// Se eliminazione
if (isset($_GET['delete']) && $_GET['delete'] == 1 && $aula_id) {
    try {
        // Verifica se aula ha lezioni
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM calendario_lezioni WHERE aula_id = ?");
        $stmt->execute([$aula_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $error = "Non è possibile eliminare un'aula con lezioni assegnate ($count)";
        } else {
            $stmt = $pdo->prepare("DELETE FROM aule WHERE id = ?");
            $stmt->execute([$aula_id]);
            $_SESSION['success'] = "Aula eliminata con successo";
            header('Location: aule.php');
            exit;
        }
    } catch (PDOException $e) {
        $error = "Errore nell'eliminazione: " . $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <?= $aula ? 'Modifica Aula' : 'Nuova Aula' ?>
            </h1>
            <p class="text-gray-600 mt-1">
                <?= $aula ? htmlspecialchars($aula['nome']) : 'Aggiungi una nuova aula' ?>
            </p>
        </div>
        <a href="aule.php" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>Torna alle Aule
        </a>
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
            <!-- Riga 1: Sede e Nome -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sede *</label>
                    <select name="sede_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleziona sede</option>
                        <?php foreach ($sedi as $sede): ?>
                            <option value="<?= $sede['id'] ?>" <?= ($aula && $aula['sede_id'] == $sede['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sede['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome Aula *</label>
                    <input type="text" name="nome" value="<?= $aula ? htmlspecialchars($aula['nome']) : '' ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: Aula Laboratorio A">
                </div>
            </div>

            <!-- Riga 2: Codice e Tipo -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Codice *</label>
                    <input type="text" name="codice" value="<?= $aula ? htmlspecialchars($aula['codice']) : '' ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: LAB-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo di Aula</label>
                    <select name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleziona tipo</option>
                        <option value="normale" <?= ($aula && $aula['tipo'] == 'normale') ? 'selected' : '' ?>>Normale</option>
                        <option value="laboratorio" <?= ($aula && $aula['tipo'] == 'laboratorio') ? 'selected' : '' ?>>Laboratorio</option>
                        <option value="palestra" <?= ($aula && $aula['tipo'] == 'palestra') ? 'selected' : '' ?>>Palestra</option>
                        <option value="aula_magna" <?= ($aula && $aula['tipo'] == 'aula_magna') ? 'selected' : '' ?>>Aula Magna</option>
                        <option value="altro" <?= ($aula && $aula['tipo'] == 'altro') ? 'selected' : '' ?>>Altro</option>
                    </select>
                </div>
            </div>

            <!-- Riga 3: Capienza e Piano -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capienza (posti)</label>
                    <input type="number" name="capienza" min="1" max="500" value="<?= $aula ? $aula['capienza'] : 25 ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Piano</label>
                    <input type="text" name="piano" value="<?= $aula ? htmlspecialchars($aula['piano']) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Es: 1°, 2°, 3°, etc">
                </div>
            </div>

            <!-- Attrezzature -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Attrezzature (separate da virgola)</label>
                <textarea name="attrezzature" rows="2"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Es: Proiettore, Lavagna interattiva, Computer"><?= $aula ? htmlspecialchars($aula['attrezzature']) : '' ?></textarea>
            </div>

            <!-- Note -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                <textarea name="note" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Note aggiuntive..."><?= $aula ? htmlspecialchars($aula['note']) : '' ?></textarea>
            </div>

            <!-- Checkbox Attiva -->
            <div class="flex items-center">
                <input type="checkbox" name="attiva" id="attiva" class="h-4 w-4 text-blue-600 rounded"
                       <?= (!$aula || $aula['attiva']) ? 'checked' : '' ?>>
                <label for="attiva" class="ml-2 text-sm text-gray-700">Aula attiva</label>
            </div>

            <!-- Bottoni -->
            <div class="flex justify-between items-center pt-6 border-t">
                <div class="flex gap-2">
                    <?php if ($aula): ?>
                        <a href="?id=<?= $aula['id'] ?>&delete=1" 
                           onclick="return confirm('Eliminare questa aula? I dati non potranno essere recuperati.')"
                           class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                            <i class="fas fa-trash mr-2"></i>Elimina
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="flex gap-2">
                    <a href="aule.php" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                        Annulla
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        <i class="fas fa-save mr-2"></i><?= $aula ? 'Salva Modifiche' : 'Crea Aula' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
