<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Filtri globali
$anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
$sede_id = $_GET['sede_id'] ?? '';
$data_inizio = $_GET['data_inizio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fine = $_GET['data_fine'] ?? date('Y-m-d');

// Ottieni anni scolastici e sedi per filtri
$stmt = $db->query("SELECT id, anno FROM anni_scolastici ORDER BY data_inizio DESC");
$anni_scolastici = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome");
$sedi = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">📊 Report e Statistiche</h1>
        <p class="text-gray-600">Dashboard completa per analisi data-driven della scuola</p>
    </div>

    <!-- Filtri Globali -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Filtri Report</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Anno Scolastico</label>
                <select name="anno_scolastico_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutti</option>
                    <?php foreach ($anni_scolastici as $anno): ?>
                        <option value="<?= $anno['id'] ?>" <?= $anno['id'] == $anno_scolastico_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($anno['anno']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sede</label>
                <select name="sede_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutte</option>
                    <?php foreach ($sedi as $sede): ?>
                        <option value="<?= $sede['id'] ?>" <?= $sede['id'] == $sede_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sede['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Da Data</label>
                <input type="date" name="data_inizio" value="<?= $data_inizio ?>" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">A Data</label>
                <input type="date" name="data_fine" value="<?= $data_fine ?>" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div class="md:col-span-4 flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Applica Filtri
                </button>
                <a href="reports.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                    Reset
                </a>
                <button type="button" onclick="exportAllReports()" 
                        class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    Export Tutto (ZIP)
                </button>
            </div>
        </form>
    </div>

    <!-- Cards Report -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Report Docenti -->
        <a href="reports/report_docenti.php?<?= http_build_query($_GET) ?>" 
           class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-chalkboard-teacher text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Report Docenti</h3>
            </div>
            <p class="text-gray-600 text-sm mb-2">Analisi carico lavoro, competenze e performance</p>
            <div class="text-blue-600 text-sm font-medium">Visualizza →</div>
        </a>

        <!-- Report Classi -->
        <a href="reports/report_classi.php?<?= http_build_query($_GET) ?>" 
           class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Report Classi</h3>
            </div>
            <p class="text-gray-600 text-sm mb-2">Copertura ore, distribuzione e composizione</p>
            <div class="text-green-600 text-sm font-medium">Visualizza →</div>
        </a>

        <!-- Report Aule -->
        <a href="reports/report_aule.php?<?= http_build_query($_GET) ?>" 
           class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-building text-purple-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Report Aule</h3>
            </div>
            <p class="text-gray-600 text-sm mb-2">Utilizzo spazi, ottimizzazione e conflitti</p>
            <div class="text-purple-600 text-sm font-medium">Visualizza →</div>
        </a>

        <!-- Report Sostituzioni -->
        <a href="reports/report_sostituzioni.php?<?= http_build_query($_GET) ?>" 
           class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-exchange-alt text-orange-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Report Sostituzioni</h3>
            </div>
            <p class="text-gray-600 text-sm mb-2">Analisi sostituzioni e copertura assenze</p>
            <div class="text-orange-600 text-sm font-medium">Visualizza →</div>
        </a>

        <!-- Report Calendario -->
        <a href="reports/report_calendario.php?<?= http_build_query($_GET) ?>" 
           class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-calendar-alt text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Report Calendario</h3>
            </div>
            <p class="text-gray-600 text-sm mb-2">Completamento, qualità e distribuzione</p>
            <div class="text-red-600 text-sm font-medium">Visualizza →</div>
        </a>

        <!-- Report Stage -->
        <a href="reports/report_stage.php?<?= http_build_query($_GET) ?>" 
           class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-briefcase text-teal-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Report Stage</h3>
            </div>
            <p class="text-gray-600 text-sm mb-2">Periodi stage, tutor e ore effettuate</p>
            <div class="text-teal-600 text-sm font-medium">Visualizza →</div>
        </a>
    </div>

    <!-- KPI Quick View -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">KPI Principali</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="kpi-container">
            <!-- KPI verranno caricati via AJAX -->
        </div>
    </div>
</div>

<script>
function exportAllReports() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../api/reports_api.php?action=export_all&${params.toString()}`, '_blank');
}

// Carica KPI via AJAX
fetch(`../api/reports_api.php?action=get_kpi&${new URLSearchParams(window.location.search)}`)
    .then(r => r.json())
    .then(data => {
        const kpiContainer = document.getElementById('kpi-container');
        if (data.success && data.kpi) {
            kpiContainer.innerHTML = data.kpi.map(kpi => `
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-${kpi.color}-600">${kpi.value}</div>
                    <div class="text-sm text-gray-600">${kpi.label}</div>
                    <div class="text-xs text-gray-500 mt-1">${kpi.trend}</div>
                </div>
            `).join('');
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>