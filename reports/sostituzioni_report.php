<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/SostituzioniManager.php';

$sostituzioniManager = new SostituzioniManager($db);
$current_page = 'sostituzioni_report';
$page_title = 'Report Sostituzioni';

// Filtri periodo
$data_inizio = $_GET['data_inizio'] ?? date('Y-m-01');
$data_fine = $_GET['data_fine'] ?? date('Y-m-t');
$tipo_export = $_GET['export'] ?? '';

// Statistiche
$statistiche = $sostituzioniManager->calcolaStatisticheSostituzioni($data_inizio, $data_fine);
$storico = $sostituzioniManager->getStoricoSostituzioni($data_inizio, $data_fine);

// Docenti più assenti
// Docenti più assenti (prepared statement)
$stmt = $pdo->prepare("\
    SELECT do.id, CONCAT(do.cognome, ' ', do.nome) as docente,\
           COUNT(*) as totale_assenze,\
           SUM(CASE WHEN s.motivo = 'malattia' THEN 1 ELSE 0 END) as malattie,\
           SUM(CASE WHEN s.motivo = 'permesso' THEN 1 ELSE 0 END) as permessi\
    FROM sostituzioni s\
    JOIN docenti do ON s.docente_originale_id = do.id\
    JOIN calendario_lezioni cl ON s.lezione_id = cl.id\
    WHERE cl.data_lezione BETWEEN ? AND ?\
    GROUP BY do.id, do.cognome, do.nome\
    ORDER BY totale_assenze DESC\
    LIMIT 10\
");
$stmt->execute([$data_inizio, $data_fine]);
$docenti_assenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Docenti sostituti più attivi
// Docenti sostituti più attivi (prepared statement)
$stmt = $pdo->prepare("\
    SELECT ds.id, CONCAT(ds.cognome, ' ', ds.nome) as docente,\
           COUNT(*) as totale_sostituzioni,\
           AVG(CASE WHEN s.confermata = 1 THEN 1 ELSE 0 END) as tasso_conferma\
    FROM sostituzioni s\
    JOIN docenti ds ON s.docente_sostituto_id = ds.id\
    JOIN calendario_lezioni cl ON s.lezione_id = cl.id\
    WHERE cl.data_lezione BETWEEN ? AND ?\
    GROUP BY ds.id, ds.cognome, ds.nome\
    ORDER BY totale_sostituzioni DESC\
    LIMIT 10\
");
$stmt->execute([$data_inizio, $data_fine]);
$docenti_sostituti = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Report Sostituzioni
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Analisi e statistiche delle sostituzioni dal <?php echo date('d/m/Y', strtotime($data_inizio)); ?> al <?php echo date('d/m/Y', strtotime($data_fine)); ?>
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-print mr-2"></i>Stampa
            </button>
        </div>
    </div>

    <!-- Filtri -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Data Inizio</label>
                <input type="date" name="data_inizio" value="<?php echo $data_inizio; ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Data Fine</label>
                <input type="date" name="data_fine" value="<?php echo $data_fine; ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-chart-bar mr-2"></i>Genera Report
                </button>
            </div>
        </form>
    </div>

    <!-- Statistiche Generali -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <!-- Totale Sostituzioni -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exchange-alt text-2xl text-blue-500"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Totale Sostituzioni</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $statistiche['totali']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confermate -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-2xl text-green-500"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Confermate</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $statistiche['confermate']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- In Attesa -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-2xl text-yellow-500"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">In Attesa</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $statistiche['in_attesa']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasso Successo -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-percentage text-2xl text-purple-500"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Tasso Successo</dt>
                            <dd class="text-lg font-medium text-gray-900">
                                <?php echo $statistiche['totali'] > 0 ? 
                                    round(($statistiche['confermate'] / $statistiche['totali']) * 100, 1) : 0; ?>%
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Docenti più assenti -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">📉 Docenti più Assenti</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Totale</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Malattie</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permessi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($docenti_assenti)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Nessun dato disponibile per il periodo selezionato
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($docenti_assenti as $docente): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($docente['docente']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $docente['totale_assenze']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $docente['malattie']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $docente['permessi']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Docenti sostituti più attivi -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">📈 Sostituti più Attivi</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sostituzioni</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tasso Conferma</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($docenti_sostituti)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Nessun dato disponibile per il periodo selezionato
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($docenti_sostituti as $docente): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($docente['docente']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $docente['totale_sostituzioni']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $docente['tasso_conferma'] > 0.8 ? 'bg-green-100 text-green-800' : 
                                                   ($docente['tasso_conferma'] > 0.5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                            <?php echo round($docente['tasso_conferma'] * 100, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Grafico Distribuzione Motivi -->
    <div class="mt-6 bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">📊 Distribuzione per Motivo</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($statistiche['per_motivo'] as $motivo => $count): ?>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $count; ?></div>
                    <div class="text-sm text-gray-600 capitalize"><?php echo $motivo; ?></div>
                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" 
                             style="width: <?php echo $statistiche['totali'] > 0 ? ($count / $statistiche['totali'] * 100) : 0; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Storico Recente -->
    <div class="mt-6 bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">🕐 Storico Recente (ultime 10)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classe/Materia</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docente Assente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sostituto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Motivo</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($storico)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                Nessuna sostituzione nel periodo selezionato
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($storico, 0, 10) as $sost): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('d/m/Y', strtotime($sost['data_lezione'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($sost['classe_nome'] . ' - ' . $sost['materia_nome']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($sost['docente_originale']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($sost['docente_sostituto'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 capitalize">
                                    <?php echo $sost['motivo']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>