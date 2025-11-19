<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'orari_slot';
$page_title = 'Gestione Orari Slot';

$error = '';
$success = '';

// Gestione azioni
if ($_POST) {
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token_post) || !verifyCsrfToken($csrf_token_post)) {
        $error = 'Token CSRF non valido';
    } else {
    if (isset($_POST['aggiungi_slot'])) {
        $numero_slot = isset($_POST['numero_slot']) ? (int)$_POST['numero_slot'] : 0;
        $ora_inizio = sanitizeInput($_POST['ora_inizio'] ?? '');
        $ora_fine = sanitizeInput($_POST['ora_fine'] ?? '');
        $tipo = sanitizeInput($_POST['tipo'] ?? '');
        $durata_minuti = isset($_POST['durata_minuti']) ? (int)$_POST['durata_minuti'] : 0;
        $attivo = isset($_POST['attivo']) ? 1 : 0;

        $stmt = $pdo->prepare("SELECT * FROM orari_slot WHERE (? BETWEEN ora_inizio AND ora_fine) OR (? BETWEEN ora_inizio AND ora_fine)");
        $stmt->execute([$ora_inizio, $ora_fine]);
        if ($stmt->rowCount() > 0) {
            $error = "Errore: Lo slot si sovrappone con un altro slot esistente";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO orari_slot (numero_slot, ora_inizio, ora_fine, tipo, durata_minuti, attivo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$numero_slot, $ora_inizio, $ora_fine, $tipo, $durata_minuti, $attivo]);
                $success = "Slot orario aggiunto con successo";
            } catch (PDOException $e) {
                $error = "Errore nel salvataggio: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['elimina_slot'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM calendario_lezioni WHERE slot_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Impossibile eliminare: lo slot è utilizzato in lezioni esistenti";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM orari_slot WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Slot orario eliminato";
            } catch (PDOException $e) {
                $error = "Errore nell'eliminazione: " . $e->getMessage();
            }
        }
    }
    }
}

// Carica slot
try {
    $stmt = $pdo->query("SELECT * FROM orari_slot ORDER BY numero_slot");
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $slots = [];
    $error = "Errore nel caricamento slot: " . $e->getMessage();
}

$colori_tipo = [
    'lezione' => 'bg-green-100 text-green-800 border-l-4 border-green-500',
    'intervallo' => 'bg-amber-100 text-amber-800 border-l-4 border-amber-500',
    'pausa_pranzo' => 'bg-red-100 text-red-800 border-l-4 border-red-500'
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Gestione Orari Slot</h1>
</div>

<!-- Messaggi -->
<?php if ($error): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex">
            <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
            <p class="text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex">
            <i class="fas fa-check-circle text-green-400 mr-3"></i>
            <p class="text-sm text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    </div>
<?php endif; ?>

<!-- Form Nuovo Slot -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Nuovo Slot Orario</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Numero Slot *</label>
            <input type="number" name="numero_slot" min="1" max="20" required 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ora Inizio *</label>
            <input type="time" name="ora_inizio" id="ora_inizio" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ora Fine *</label>
            <input type="time" name="ora_fine" id="ora_fine" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
            <select name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Seleziona tipo</option>
                <option value="lezione">Lezione</option>
                <option value="intervallo">Intervallo</option>
                <option value="pausa_pranzo">Pausa Pranzo</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Durata (minuti)</label>
            <input type="number" name="durata_minuti" id="durata_minuti" readonly
                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
        </div>
        <div class="flex items-center pt-6">
            <input type="checkbox" name="attivo" value="1" id="attivo" class="h-4 w-4 text-indigo-600" checked>
            <label for="attivo" class="ml-2 text-sm text-gray-700">Attivo</label>
        </div>
        <div class="md:col-span-3 flex items-end">
            <button type="submit" name="aggiungi_slot" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium">
                <i class="fas fa-plus mr-2"></i>Aggiungi Slot
            </button>
        </div>
    </form>
</div>

<!-- Timeline Slot -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Timeline Slot Orari</h2>
    <div class="space-y-3">
        <?php foreach ($slots as $slot): ?>
            <div class="p-4 rounded-lg <?= $colori_tipo[$slot['tipo']] ?? 'bg-gray-100 text-gray-800' ?>">
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <div class="font-semibold">Slot <?= $slot['numero_slot'] ?></div>
                        <div class="text-sm opacity-90">
                            <?= substr($slot['ora_inizio'], 0, 5) ?> - <?= substr($slot['ora_fine'], 0, 5) ?>
                            (<?= $slot['durata_minuti'] ?> min - <?= ucfirst(str_replace('_', ' ', $slot['tipo'])) ?>)
                        </div>
                    </div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                        <button type="submit" name="elimina_slot" class="text-red-600 hover:text-red-800 font-medium text-sm"
                                onclick="return confirm('Eliminare questo slot?')">
                            <i class="fas fa-trash mr-1"></i>Elimina
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Tabella Slot -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slot</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ora Inizio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ora Fine</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durata</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($slots as $slot): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $slot['numero_slot'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $slot['ora_inizio'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $slot['ora_fine'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            <?= $colori_tipo[$slot['tipo']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst(str_replace('_', ' ', $slot['tipo'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $slot['durata_minuti'] ?> min</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $slot['attivo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $slot['attivo'] ? 'Attivo' : 'Inattivo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                            <button type="submit" name="elimina_slot" class="text-red-600 hover:text-red-900"
                                    onclick="return confirm('Eliminare questo slot?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Calcolo automatico durata
document.getElementById('ora_inizio')?.addEventListener('change', calcolaDurata);
document.getElementById('ora_fine')?.addEventListener('change', calcolaDurata);

function calcolaDurata() {
    const inizio = document.getElementById('ora_inizio').value;
    const fine = document.getElementById('ora_fine').value;
    
    if (inizio && fine) {
        const start = new Date('2000-01-01T' + inizio);
        const end = new Date('2000-01-01T' + fine);
        const diff = (end - start) / (1000 * 60);
        
        if (diff > 0) {
            document.getElementById('durata_minuti').value = diff;
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>