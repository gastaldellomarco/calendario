<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'aule';
$page_title = 'Gestione Aule';

$error = '';
$success = '';
$sede_filtro = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : '';

// Inizializza connessione database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    error_log("Errore connessione: " . $e->getMessage());
    die("Errore di connessione al database");
}

// Gestione azioni
if ($_POST) {
    if (isset($_POST['aggiungi_aula'])) {
        $sede_id = isset($_POST['sede_id']) ? (int)$_POST['sede_id'] : 0;
        $nome = sanitizeInput($_POST['nome'] ?? '');
        $codice = sanitizeInput($_POST['codice'] ?? '');
        $tipo = sanitizeInput($_POST['tipo'] ?? '');
        $capienza = isset($_POST['capienza']) ? (int)$_POST['capienza'] : 25;
        $piano = sanitizeInput($_POST['piano'] ?? '');
        $attrezzature = sanitizeInput($_POST['attrezzature'] ?? '');
        $note = sanitizeInput($_POST['note'] ?? '');
        $attiva = isset($_POST['attiva']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("SELECT id FROM aule WHERE sede_id = ? AND codice = ?");
            $stmt->execute([$sede_id, $codice]);
            if ($stmt->rowCount() > 0) {
                $error = "Errore: Codice aula già esistente per questa sede";
            } else {
                $stmt = $pdo->prepare("INSERT INTO aule (sede_id, nome, codice, tipo, capienza, piano, attrezzature, note, attiva) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sede_id, $nome, $codice, $tipo, $capienza, $piano, $attrezzature, $note, $attiva]);
                $success = "Aula aggiunta con successo";
            }
        } catch (PDOException $e) {
            $error = "Errore nel salvataggio: " . $e->getMessage();
        }
    }
}

// Carica aule con filtro
try {
    $sql = "SELECT a.*, s.nome as sede_nome FROM aule a JOIN sedi s ON a.sede_id = s.id";
    $params = [];
    if ($sede_filtro) {
        $sql .= " WHERE a.sede_id = ?";
        $params[] = $sede_filtro;
    }
    $sql .= " ORDER BY s.nome, a.codice";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $aule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $aule = [];
    $error = "Errore nel caricamento aule: " . $e->getMessage();
}

// Calcola disponibilità
$disponibilita = [];
$oggi = date('Y-m-d');
foreach ($aule as $aula) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM calendario_lezioni WHERE aula_id = ? AND data_lezione = ?");
    $stmt->execute([$aula['id'], $oggi]);
    $disponibilita[$aula['id']] = $stmt->fetchColumn();
}

// Definisci colori tipo aula
$colori_tipo = [
    'normale' => 'bg-blue-100 text-blue-800',
    'laboratorio' => 'bg-purple-100 text-purple-800',
    'palestra' => 'bg-red-100 text-red-800',
    'aula_magna' => 'bg-indigo-100 text-indigo-800',
    'altro' => 'bg-gray-100 text-gray-800'
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Gestione Aule</h1>
    <button onclick="document.getElementById('formAula').scrollIntoView({behavior: 'smooth'})" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-plus mr-2"></i>Nuova Aula
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

<!-- Filtro Sede -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="GET" class="flex items-end space-x-4">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Filtra per Sede</label>
            <select name="sede_id" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option value="">Tutte le sedi</option>
                <?php foreach ($sedi as $sede): ?>
                    <option value="<?= $sede['id'] ?>" <?= $sede_filtro == $sede['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sede['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<!-- Form Nuova Aula -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6" id="formAula">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Nuova Aula</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sede *</label>
            <select name="sede_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Seleziona sede</option>
                <?php foreach ($sedi as $sede): ?>
                    <option value="<?= $sede['id'] ?>"><?= htmlspecialchars($sede['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
            <input type="text" name="nome" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Codice *</label>
            <input type="text" name="codice" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
            <select name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Seleziona tipo</option>
                <option value="normale">Normale</option>
                <option value="laboratorio">Laboratorio</option>
                <option value="palestra">Palestra</option>
                <option value="aula_magna">Aula Magna</option>
                <option value="altro">Altro</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Capienza</label>
            <input type="number" name="capienza" min="1" max="500" value="25" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Piano</label>
            <input type="text" name="piano" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Attrezzature</label>
            <textarea name="attrezzature" rows="2" placeholder="Separare con virgole" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
            <textarea name="note" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div class="flex items-center pt-2">
            <input type="checkbox" name="attiva" value="1" id="attiva" class="h-4 w-4 text-indigo-600" checked>
            <label for="attiva" class="ml-2 text-sm text-gray-700">Aula attiva</label>
        </div>
        <div class="md:col-span-3 flex items-end">
            <button type="submit" name="aggiungi_aula" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium">
                <i class="fas fa-plus mr-2"></i>Aggiungi Aula
            </button>
        </div>
    </form>
</div>

<!-- Tabella Aule -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codice</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sede</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capienza</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Piano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore Occupate (Oggi)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($aule as $aula): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($aula['codice']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($aula['nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($aula['sede_nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $colori_tipo[$aula['tipo']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst(str_replace('_', ' ', $aula['tipo'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $aula['capienza'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($aula['piano'] ?: '-') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $disponibilita[$aula['id']] > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                            <?= $disponibilita[$aula['id']] ?> ore
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $aula['attiva'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $aula['attiva'] ? 'Attiva' : 'Inattiva' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <a href="aula_form.php?id=<?= $aula['id'] ?>" class="text-blue-600 hover:text-blue-900" title="Modifica">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="visualizzaDisponibilita(<?= $aula['id'] ?>)" class="text-green-600 hover:text-green-900" title="Disponibilità">
                            <i class="fas fa-calendar"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function visualizzaDisponibilita(aulaId) {
    window.location.href = 'aula_disponibilita.php?id=' + aulaId;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>