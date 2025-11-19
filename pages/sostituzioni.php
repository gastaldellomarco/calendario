<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/SostituzioniManager.php';

$sostituzioniManager = new SostituzioniManager($db);
$current_page = 'sostituzioni';
$page_title = 'Gestione Sostituzioni';

// Gestione form registrazione assenza
if (($_POST['action'] ?? '') == 'registra_assenza') {
    $docente_id = intval($_POST['docente_id']);
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $motivo = $_POST['motivo'];
    $note = $_POST['note'];
    
    $result = $sostituzioniManager->creaAssenza($docente_id, $data_inizio, $data_fine, $motivo, $note);
    
    if ($result['success']) {
        $_SESSION['success_message'] = "Assenza registrata con successo. " . count($result['lezioni']) . " lezioni da sostituire.";
    } else {
        $_SESSION['error_message'] = $result['message'];
    }
}

// Gestione azioni sostituzioni
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'conferma':
            $sostituzioniManager->confermaSostituzione(intval($_GET['id']));
            break;
        case 'annulla':
            $sostituzioniManager->annullaSostituzione(intval($_GET['id']));
            break;
    }
}

// Filtri
$filtro_data = $_GET['filtro_data'] ?? date('Y-m-d');
$filtro_stato = $_GET['filtro_stato'] ?? 'tutti';

// Dati per le view
$sostituzioni_attive = $sostituzioniManager->getSostituzioniAttive($filtro_data, $filtro_stato);
$sostituzioni_storico = $sostituzioniManager->getStoricoSostituzioni(date('Y-m-01'), date('Y-m-t'));
$statistiche = $sostituzioniManager->calcolaStatisticheSostituzioni(date('Y-m-01'), date('Y-m-t'));

// Docenti per select
$docenti = $db->query("SELECT id, cognome, nome FROM docenti WHERE stato = 'attivo' ORDER BY cognome, nome")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Gestione Sostituzioni
            </h1>
        </div>
    </div>

    <!-- Messaggi -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Colonna sinistra: Registra Assenza -->
        <div class="lg:col-span-1">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">📝 Registra Assenza</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="registra_assenza">
                    
                    <div class="space-y-4">
                        <!-- Docente -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Docente Assente</label>
                            <select name="docente_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleziona docente...</option>
                                <?php foreach ($docenti as $docente): ?>
                                    <option value="<?php echo $docente['id']; ?>">
                                        <?php echo htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Periodo -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Data Inizio</label>
                                <input type="date" name="data_inizio" required 
                                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Data Fine</label>
                                <input type="date" name="data_fine" required 
                                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <!-- Motivo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Motivo</label>
                            <select name="motivo" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="malattia">Malattia</option>
                                <option value="permesso">Permesso</option>
                                <option value="formazione">Formazione</option>
                                <option value="altro">Altro</option>
                            </select>
                        </div>

                        <!-- Note -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Note</label>
                            <textarea name="note" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <!-- Azioni -->
                        <div class="flex space-x-3 pt-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>Registra Assenza
                            </button>
                            <a href="trova_sostituto.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-search mr-2"></i>Cerca Sostituti
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Statistiche Rapide -->
            <div class="bg-white shadow rounded-lg p-6 mt-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">📊 Statistiche Mese</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Totale Sostituzioni:</span>
                        <span class="font-medium"><?php echo $statistiche['totali']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">In Attesa:</span>
                        <span class="font-medium text-yellow-600"><?php echo $statistiche['in_attesa']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Confermate:</span>
                        <span class="font-medium text-green-600"><?php echo $statistiche['confermate']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonna destra: Sostituzioni Attive -->
        <div class="lg:col-span-2">
            <!-- Filtri -->
            <div class="bg-white shadow rounded-lg p-4 mb-6">
                <div class="flex flex-wrap gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data</label>
                        <input type="date" id="filtro_data" value="<?php echo $filtro_data; ?>" 
                               class="mt-1 block border border-gray-300 rounded-md shadow-sm py-1 px-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stato</label>
                        <select id="filtro_stato" class="mt-1 block border border-gray-300 rounded-md shadow-sm py-1 px-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="tutti" <?php echo $filtro_stato == 'tutti' ? 'selected' : ''; ?>>Tutti</option>
                            <option value="in_attesa" <?php echo $filtro_stato == 'in_attesa' ? 'selected' : ''; ?>>In Attesa</option>
                            <option value="confermata" <?php echo $filtro_stato == 'confermata' ? 'selected' : ''; ?>>Confermate</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="applicaFiltri()" class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                            <i class="fas fa-filter mr-1"></i>Filtra
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabella Sostituzioni Attive -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">🔄 Sostituzioni Attive</h2>
                </div>
                
                <?php if (empty($sostituzioni_attive)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-check-circle text-3xl mb-2 text-green-400"></i>
                        <p>Nessuna sostituzione attiva</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Ora</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lezione</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sostituto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($sostituzioni_attive as $sost): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('d/m H:i', strtotime($sost['data_lezione'] . ' ' . $sost['ora_inizio'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($sost['classe_nome'] . ' - ' . $sost['materia_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($sost['docente_originale']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($sost['docente_sostituto'] ?? 'Da assegnare'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $sost['stato'] == 'confermata' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo $sost['stato'] == 'confermata' ? 'Confermata' : 'In Attesa'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if (!$sost['docente_sostituto_id']): ?>
                                                <a href="trova_sostituto.php?lezione_id=<?php echo $sost['lezione_id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-user-plus"></i> Assegna
                                                </a>
                                            <?php elseif ($sost['stato'] == 'in_attesa'): ?>
                                                <a href="?action=conferma&id=<?php echo $sost['id']; ?>" 
                                                   class="text-green-600 hover:text-green-900 mr-3">
                                                    <i class="fas fa-check"></i> Conferma
                                                </a>
                                            <?php endif; ?>
                                            <a href="?action=annulla&id=<?php echo $sost['id']; ?>" 
                                               class="text-red-600 hover:text-red-900"
                                               onclick="return confirm('Annullare questa sostituzione?')">
                                                <i class="fas fa-times"></i> Annulla
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function applicaFiltri() {
    const data = document.getElementById('filtro_data').value;
    const stato = document.getElementById('filtro_stato').value;
    window.location.href = `?filtro_data=${data}&filtro_stato=${stato}`;
}
</script>

<?php include '../includes/footer.php'; ?>