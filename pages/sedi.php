<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'sedi';
$page_title = 'Gestione Sedi';

$error = '';
$success = '';

// Gestione azioni
if ($_POST) {
    // Verifica CSRF (protezione da request forgery)
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token_post) || !verifyCsrfToken($csrf_token_post)) {
        $error = 'Token CSRF non valido';
    } else {
    if (isset($_POST['aggiungi_sede'])) {
        $nome = sanitizeInput($_POST['nome'] ?? '');
        $codice = sanitizeInput($_POST['codice'] ?? '');
        $indirizzo = sanitizeInput($_POST['indirizzo'] ?? '');
        $citta = sanitizeInput($_POST['citta'] ?? '');
        $cap = sanitizeInput($_POST['cap'] ?? '');
        $provincia = sanitizeInput($_POST['provincia'] ?? '');
        $telefono = sanitizeInput($_POST['telefono'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $attiva = isset($_POST['attiva']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("SELECT id FROM sedi WHERE codice = ?");
            $stmt->execute([$codice]);
            if ($stmt->rowCount() > 0) {
                $error = "Errore: Codice sede già esistente";
            } else {
                $stmt = $pdo->prepare("INSERT INTO sedi (nome, codice, indirizzo, citta, cap, provincia, telefono, email, attiva) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $codice, $indirizzo, $citta, $cap, $provincia, $telefono, $email, $attiva]);
                $success = "Sede aggiunta con successo";
            }
        } catch (PDOException $e) {
            $error = "Errore nel salvataggio: " . $e->getMessage();
        }
    } elseif (isset($_POST['modifica_sede'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nome = sanitizeInput($_POST['nome'] ?? '');
        $codice = sanitizeInput($_POST['codice'] ?? '');
        $indirizzo = sanitizeInput($_POST['indirizzo'] ?? '');
        $citta = sanitizeInput($_POST['citta'] ?? '');
        $cap = sanitizeInput($_POST['cap'] ?? '');
        $provincia = sanitizeInput($_POST['provincia'] ?? '');
        $telefono = sanitizeInput($_POST['telefono'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $attiva = isset($_POST['attiva']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE sedi SET nome = ?, codice = ?, indirizzo = ?, citta = ?, cap = ?, provincia = ?, telefono = ?, email = ?, attiva = ? WHERE id = ?");
            $stmt->execute([$nome, $codice, $indirizzo, $citta, $cap, $provincia, $telefono, $email, $attiva, $id]);
            $success = "Sede modificata con successo";
        } catch (PDOException $e) {
            $error = "Errore nella modifica: " . $e->getMessage();
        }
    } elseif (isset($_POST['elimina_sede'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        $tables = ['aule', 'docenti', 'classi', 'calendario_lezioni'];
        $used = false;
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE sede_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $used = true;
                break;
            }
        }
        
        if ($used) {
            $error = "Impossibile eliminare: la sede è utilizzata in altre tabelle";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM sedi WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Sede eliminata";
            } catch (PDOException $e) {
                $error = "Errore nell'eliminazione: " . $e->getMessage();
            }
        }
    }
    }
    // Fine gestione POST
}

// Carica sedi
try {
    $stmt = $pdo->query("SELECT * FROM sedi ORDER BY nome");
    $sedi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sedi = [];
    $error = "Errore nel caricamento sedi: " . $e->getMessage();
}

// Calcola statistiche
$statistiche = [];
foreach ($sedi as $sede) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM aule WHERE sede_id = ? AND attiva = 1");
    $stmt->execute([$sede['id']]);
    $aule_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM docenti WHERE sede_principale_id = ? AND stato = 'attivo'");
    $stmt->execute([$sede['id']]);
    $docenti_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classi WHERE sede_id = ? AND stato = 'attiva'");
    $stmt->execute([$sede['id']]);
    $classi_count = $stmt->fetchColumn();

    $statistiche[$sede['id']] = [
        'aule' => $aule_count,
        'docenti' => $docenti_count,
        'classi' => $classi_count
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Pagina -->
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Gestione Sedi</h1>
    <button onclick="document.getElementById('formSede').scrollIntoView({behavior: 'smooth'})" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-plus mr-2"></i>Nuova Sede
    </button>
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

<!-- Statistiche -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4 mb-6">
    <?php foreach ($sedi as $sede): ?>
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= htmlspecialchars($sede['nome']) ?></h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= $statistiche[$sede['id']]['aule'] ?></div>
                    <div class="text-xs text-gray-600 mt-1">Aule</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?= $statistiche[$sede['id']]['docenti'] ?></div>
                    <div class="text-xs text-gray-600 mt-1">Docenti</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600"><?= $statistiche[$sede['id']]['classi'] ?></div>
                    <div class="text-xs text-gray-600 mt-1">Classi</div>
                </div>
            </div>
            <div class="mt-4 space-x-2 flex justify-center">
                <button onclick="modificaSede(<?= $sede['id'] ?>)" class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1 rounded text-sm">
                    <i class="fas fa-edit mr-1"></i>Modifica
                </button>
                <?php if (array_sum($statistiche[$sede['id']]) == 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="id" value="<?= $sede['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
                        <button type="submit" name="elimina_sede" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm" onclick="return confirm('Eliminare questa sede?')">
                            <i class="fas fa-trash mr-1"></i>Elimina
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Form Nuova Sede -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6" id="formSede">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Nuova Sede</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? generateCsrfToken()) ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
            <input type="text" name="nome" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Codice *</label>
            <input type="text" name="codice" required maxlength="10" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Indirizzo</label>
            <input type="text" name="indirizzo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Città *</label>
            <input type="text" name="citta" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CAP</label>
            <input type="text" name="cap" maxlength="10" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
            <input type="text" name="provincia" maxlength="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
            <input type="tel" name="telefono" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-center pt-6">
            <input type="checkbox" name="attiva" value="1" id="attiva" class="h-4 w-4 text-indigo-600" checked>
            <label for="attiva" class="ml-2 text-sm text-gray-700">Sede attiva</label>
        </div>
        <div class="flex items-end">
            <button type="submit" name="aggiungi_sede" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium">
                <i class="fas fa-plus mr-2"></i>Aggiungi Sede
            </button>
        </div>
    </form>
</div>

<!-- Tabella Sedi -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codice</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Città</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Indirizzo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefono</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($sedi as $sede): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($sede['codice']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($sede['nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($sede['citta']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($sede['indirizzo'] ?: '-') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($sede['telefono'] ?: '-') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $sede['attiva'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $sede['attiva'] ? 'Attiva' : 'Inattiva' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <button onclick="modificaSede(<?= $sede['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (array_sum($statistiche[$sede['id']]) == 0): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $sede['id'] ?>">
                                <button type="submit" name="elimina_sede" class="text-red-600 hover:text-red-900" onclick="return confirm('Eliminare questa sede?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function modificaSede(id) {
    window.location.href = 'sedi_form.php?id=' + id;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>