<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'giorni_chiusura';
$page_title = 'Gestione Giorni Chiusura';

$error = '';
$success = '';

// Gestione azioni
if ($_POST) {
    if (isset($_POST['aggiungi_chiusura'])) {
        $data_chiusura = sanitizeInput($_POST['data_chiusura'] ?? '');
        $descrizione = sanitizeInput($_POST['descrizione'] ?? '');
        $tipo = sanitizeInput($_POST['tipo'] ?? '');
        $ripete_annualmente = isset($_POST['ripete_annualmente']) ? 1 : 0;
        $sede_id = isset($_POST['sede_id']) && $_POST['sede_id'] ? (int)$_POST['sede_id'] : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO giorni_chiusura (data_chiusura, descrizione, tipo, ripete_annualmente, sede_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data_chiusura, $descrizione, $tipo, $ripete_annualmente, $sede_id]);
            $success = "Giorno di chiusura aggiunto con successo";
        } catch (PDOException $e) {
            $error = "Errore nel salvataggio: " . $e->getMessage();
        }
    } elseif (isset($_POST['importa_festivita'])) {
        $anno = isset($_POST['anno_import']) ? (int)$_POST['anno_import'] : date('Y');
        
        $festivita = [
            [$anno . '-01-01', 'Capodanno', 'festivo', 1],
            [$anno . '-01-06', 'Epifania', 'festivo', 1],
            [$anno . '-04-25', 'Liberazione', 'festivo', 1],
            [$anno . '-05-01', 'Festa del Lavoro', 'festivo', 1],
            [$anno . '-06-02', 'Festa della Repubblica', 'festivo', 1],
            [$anno . '-08-15', 'Ferragosto', 'festivo', 1],
            [$anno . '-11-01', 'Ognissanti', 'festivo', 1],
            [$anno . '-12-08', 'Immacolata Concezione', 'festivo', 1],
            [$anno . '-12-25', 'Natale', 'festivo', 1],
            [$anno . '-12-26', 'Santo Stefano', 'festivo', 1],
        ];

        $importate = 0;
        foreach ($festivita as $fest) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO giorni_chiusura (data_chiusura, descrizione, tipo, ripete_annualmente) VALUES (?, ?, ?, ?)");
                $stmt->execute($fest);
                if ($stmt->rowCount() > 0) {
                    $importate++;
                }
            } catch (PDOException $e) {
                // Ignora duplicati
            }
        }
        $success = "Importate $importate festività nazionali per l'anno $anno";
    }
}

// Carica sedi
try {
    $stmt = $pdo->query("SELECT * FROM sedi WHERE attiva = 1 ORDER BY nome");
    $sedi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sedi = [];
}

// Carica giorni chiusura
try {
    $stmt = $pdo->query("SELECT gc.*, s.nome as sede_nome FROM giorni_chiusura gc LEFT JOIN sedi s ON gc.sede_id = s.id ORDER BY gc.data_chiusura DESC");
    $chiusure = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $chiusure = [];
    $error = "Errore nel caricamento chiusure: " . $e->getMessage();
}

// Raggruppa per mese
$calendario = [];
foreach ($chiusure as $chiusura) {
    $mese = date('Y-m', strtotime($chiusura['data_chiusura']));
    $calendario[$mese][] = $chiusura;
}

$colori_tipo = [
    'festivo' => 'bg-amber-100 text-amber-800',
    'vacanza' => 'bg-green-100 text-green-800',
    'chiusura' => 'bg-red-100 text-red-800',
    'ponte' => 'bg-blue-100 text-blue-800'
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Gestione Giorni Chiusura</h1>
    <button onclick="document.getElementById('formChiusura').scrollIntoView({behavior: 'smooth'})" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-plus mr-2"></i>Nuovo Giorno
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

<!-- Form Nuovo Giorno -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6" id="formChiusura">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Nuovo Giorno di Chiusura</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Data *</label>
            <input type="date" name="data_chiusura" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descrizione *</label>
            <input type="text" name="descrizione" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
            <select name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Seleziona tipo</option>
                <option value="festivo">Festivo</option>
                <option value="vacanza">Vacanza</option>
                <option value="chiusura">Chiusura</option>
                <option value="ponte">Ponte</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sede (opzionale)</label>
            <select name="sede_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Tutte le sedi</option>
                <?php foreach ($sedi as $sede): ?>
                    <option value="<?= $sede['id'] ?>"><?= htmlspecialchars($sede['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center pt-6">
            <input type="checkbox" name="ripete_annualmente" value="1" id="ripete" class="h-4 w-4 text-indigo-600">
            <label for="ripete" class="ml-2 text-sm text-gray-700">Ripete annualmente</label>
        </div>
        <div class="flex items-end">
            <button type="submit" name="aggiungi_chiusura" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium">
                <i class="fas fa-plus mr-2"></i>Aggiungi
            </button>
        </div>
    </form>
</div>

<!-- Import Festività -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Importa Festività Nazionali</h2>
    <form method="POST" class="flex items-end space-x-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Anno</label>
            <input type="number" name="anno_import" value="<?= date('Y') ?>" min="2020" max="2030" required 
                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <button type="submit" name="importa_festivita" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium">
            <i class="fas fa-download mr-2"></i>Importa Festività
        </button>
    </form>
</div>

<!-- Calendario -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Calendario Chiusure</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php
        $mesi_mostrati = array_slice($calendario, 0, 6);
        $formatter_mese = new IntlDateFormatter('it_IT', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Rome', IntlDateFormatter::GREGORIAN, 'LLLL yyyy');
        foreach ($mesi_mostrati as $mese => $chiusure_mese):
            $data_mese = DateTime::createFromFormat('Y-m', $mese);
            $titolo_mese = $data_mese ? ucfirst($formatter_mese->format($data_mese)) : $mese;
        ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="text-lg font-semibold text-gray-800 mb-3 pb-2 border-b">
                    <?= htmlspecialchars($titolo_mese) ?>
                </div>
                <div class="space-y-2">
                    <?php foreach ($chiusure_mese as $chiusura): ?>
                        <div class="p-2 rounded text-sm <?= $colori_tipo[$chiusura['tipo']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <strong><?= date('d', strtotime($chiusura['data_chiusura'])) ?></strong>: 
                            <?= htmlspecialchars($chiusura['descrizione']) ?>
                            <?php if ($chiusura['sede_nome']): ?>
                                <div class="text-xs mt-1">(<?= htmlspecialchars($chiusura['sede_nome']) ?>)</div>
                            <?php endif; ?>
                            <?php if ($chiusura['ripete_annualmente']): ?>
                                <div class="text-xs mt-1"><i class="fas fa-sync-alt mr-1"></i>Annuale</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Tabella Completa -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrizione</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sede</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ripetizione</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($chiusure as $chiusura): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= date('d/m/Y', strtotime($chiusura['data_chiusura'])) ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($chiusura['descrizione']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $colori_tipo[$chiusura['tipo']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst($chiusura['tipo']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?= htmlspecialchars($chiusura['sede_nome'] ?: 'Tutte') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?= $chiusura['ripete_annualmente'] ? '<i class="fas fa-sync-alt text-green-600 mr-1"></i>Annuale' : 'Una tantum' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="modificaChiusura(<?= $chiusura['id'] ?>)" class="text-blue-600 hover:text-blue-900 mr-3" title="Modifica">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="eliminaChiusura(<?= $chiusura['id'] ?>)" class="text-red-600 hover:text-red-900" title="Elimina">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function modificaChiusura(id) {
    window.location.href = 'chiusura_form.php?id=' + id;
}

function eliminaChiusura(id) {
    if (confirm('Eliminare questo giorno di chiusura?')) {
        fetch('../api/giorni_chiusura_api.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Errore: ' + (data.error || 'Errore sconosciuto'));
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore durante l\'eliminazione');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>