<?php
require_once '../../config/config.php';
requireAuth('preside');

$pageTitle = "Dashboard Amministrazione";
include '../../includes/header.php';

// Inizializza SystemChecker
require_once '../../includes/SystemChecker.php';
$systemChecker = new SystemChecker();
$systemInfo = $systemChecker->getSystemInfo();

// Statistiche sistema
$totalUsers = Database::count("SELECT COUNT(*) FROM utenti WHERE attivo = 1");
$totalTeachers = Database::count("SELECT COUNT(*) FROM docenti WHERE stato = 'attivo'");
$totalClasses = Database::count("SELECT COUNT(*) FROM classi WHERE stato = 'attivo'");
$totalLessons = Database::count("SELECT COUNT(*) FROM calendario_lezioni WHERE stato = 'pianificata'");

// Ultimo backup
$lastBackup = Database::queryOne("SELECT created_at FROM snapshot_calendario ORDER BY created_at DESC LIMIT 1");
$lastBackupDate = $lastBackup ? date('d/m/Y H:i', strtotime($lastBackup['created_at'])) : 'Mai';

// Attività recente
$recentActivity = Database::queryAll("
    SELECT la.*, u.username 
    FROM log_attivita la 
    LEFT JOIN utenti u ON la.utente = u.username 
    ORDER BY la.created_at DESC 
    LIMIT 10
");

// Errori recenti
$recentErrors = Database::queryAll("
    SELECT * FROM log_attivita 
    WHERE tipo = 'error' 
    ORDER BY created_at DESC 
    LIMIT 5
");
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard Amministrazione</h1>
        <p class="text-gray-600">Panoramica sistema e strumenti di gestione</p>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Utenti Attivi -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Utenti Attivi</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalUsers; ?></p>
                </div>
            </div>
        </div>

        <!-- Docenti Attivi -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Docenti Attivi</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalTeachers; ?></p>
                </div>
            </div>
        </div>

        <!-- Classi Attive -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-school text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Classi Attive</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalClasses; ?></p>
                </div>
            </div>
        </div>

        <!-- Ultimo Backup -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-database text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Ultimo Backup</p>
                    <p class="text-lg font-bold text-gray-900"><?php echo $lastBackupDate; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Azioni Rapide -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Azioni Rapide</h2>
            </div>
            <div class="p-6 grid grid-cols-2 gap-4">
                <button onclick="creaBackup()" class="flex items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                    <i class="fas fa-download text-blue-600 mr-2"></i>
                    <span class="font-medium">Backup DB</span>
                </button>
                
                <button onclick="pulisciCache()" class="flex items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                    <i class="fas fa-broom text-green-600 mr-2"></i>
                    <span class="font-medium">Pulisci Cache</span>
                </button>
                
                <button onclick="ottimizzaDB()" class="flex items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                    <i class="fas fa-tools text-purple-600 mr-2"></i>
                    <span class="font-medium">Ottimizza DB</span>
                </button>
                
                <button onclick="controllaAggiornamenti()" class="flex items-center justify-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors">
                    <i class="fas fa-sync text-yellow-600 mr-2"></i>
                    <span class="font-medium">Controlla Aggiornamenti</span>
                </button>
            </div>
        </div>

        <!-- Salute Sistema -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Salute Sistema</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Database</span>
                    <span class="px-2 py-1 bg-<?php echo ($systemInfo['database']['status'] ?? 'ok') === 'ok' ? 'green' : (($systemInfo['database']['status'] ?? 'ok') === 'warning' ? 'yellow' : 'red'); ?>-100 text-<?php echo ($systemInfo['database']['status'] ?? 'ok') === 'ok' ? 'green' : (($systemInfo['database']['status'] ?? 'ok') === 'warning' ? 'yellow' : 'red'); ?>-800 text-xs rounded-full">
                        <?php 
                        $dbStatus = $systemInfo['database']['status'] ?? 'ok';
                        echo $dbStatus === 'ok' ? 'OK' : ($dbStatus === 'warning' ? 'Warning' : 'Errore');
                        ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">PHP <?php echo htmlspecialchars($systemInfo['php_version']['version'] ?? PHP_VERSION); ?></span>
                    <span class="px-2 py-1 bg-<?php echo ($systemInfo['php_version']['status'] ?? 'ok') === 'ok' ? 'green' : 'red'; ?>-100 text-<?php echo ($systemInfo['php_version']['status'] ?? 'ok') === 'ok' ? 'green' : 'red'; ?>-800 text-xs rounded-full">
                        <?php echo ($systemInfo['php_version']['status'] ?? 'ok') === 'ok' ? 'OK' : 'Errore'; ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Spazio Disco</span>
                    <span class="px-2 py-1 bg-<?php echo ($systemInfo['disk_space']['used_percent'] ?? 0) > 90 ? 'red' : 'green'; ?>-100 text-<?php echo ($systemInfo['disk_space']['used_percent'] ?? 0) > 90 ? 'red' : 'green'; ?>-800 text-xs rounded-full">
                        <?php echo number_format($systemInfo['disk_space']['used_percent'] ?? 0, 1); ?>%
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Cron Jobs</span>
                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Da verificare</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Attività Recente -->
    <div class="mt-8 bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Attività Recente</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Ora</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azione</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrizione</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($recentActivity as $activity): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($activity['username'] ?? 'Sistema'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                <?php echo htmlspecialchars($activity['azione']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php echo htmlspecialchars($activity['descrizione']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function creaBackup() {
    if (confirm('Vuoi creare un backup manuale del database?')) {
        fetch('../../api/admin_api.php?action=backup_database')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup creato con successo!');
                    location.reload();
                } else {
                    alert('Errore durante il backup: ' + data.message);
                }
            });
    }
}

function pulisciCache() {
    fetch('../../api/admin_api.php?action=clear_cache')
        .then(response => response.json())
        .then(data => {
            alert(data.message);
        });
}

function ottimizzaDB() {
    if (confirm('Vuoi ottimizzare le tabelle del database?')) {
        fetch('../../api/admin_api.php?action=run_maintenance')
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            });
    }
}

function controllaAggiornamenti() {
    fetch('../../api/admin_api.php?action=check_updates')
        .then(response => response.json())
        .then(data => {
            if (data.update_available) {
                alert('Aggiornamento disponibile: ' + data.latest_version);
            } else {
                alert('Il sistema è aggiornato all\'ultima versione.');
            }
        });
}
</script>

<?php include '../../includes/footer.php'; ?>