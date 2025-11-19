<?php
require_once '../../config/config.php';
requireAuth('preside');

$pageTitle = "Log Attività";
include '../../includes/header.php';

// Filtri
$filtro_utente = $_GET['utente'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_tabella = $_GET['tabella'] ?? '';
$filtro_data_da = $_GET['data_da'] ?? '';
$filtro_data_a = $_GET['data_a'] ?? '';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = 50;
$offset = ($pagina - 1) * $limite;

// Costruisci query con filtri
$where = [];
$params = [];

if ($filtro_utente) {
    $where[] = "la.utente LIKE ?";
    $params[] = "%$filtro_utente%";
}

if ($filtro_tipo) {
    $where[] = "la.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_tabella) {
    $where[] = "la.tabella = ?";
    $params[] = $filtro_tabella;
}

if ($filtro_data_da) {
    $where[] = "DATE(la.created_at) >= ?";
    $params[] = $filtro_data_da;
}

if ($filtro_data_a) {
    $where[] = "DATE(la.created_at) <= ?";
    $params[] = $filtro_data_a;
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Conta totale
$total = Database::count("SELECT COUNT(*) FROM log_attivita la $where_clause", $params);

// Query log
$log = Database::queryAll("
    SELECT la.* 
    FROM log_attivita la 
    $where_clause 
    ORDER BY la.created_at DESC 
    LIMIT $limite OFFSET $offset
", $params);

// Tipi e tabelle per filtri
$tipi = Database::queryAll("SELECT DISTINCT tipo FROM log_attivita ORDER BY tipo");
$tabelle = Database::queryAll("SELECT DISTINCT tabella FROM log_attivita WHERE tabella IS NOT NULL ORDER BY tabella");

// Export CSV
if (($_GET['action'] ?? '') === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=log_attivita_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Data/Ora', 'Utente', 'Tipo', 'Azione', 'Descrizione', 'Tabella', 'Record ID', 'IP Address']);
    
    $export_log = Database::queryAll("SELECT * FROM log_attivita la $where_clause ORDER BY la.created_at DESC", $params);
    foreach ($export_log as $row) {
        fputcsv($output, [
            $row['created_at'],
            $row['utente'],
            $row['tipo'],
            $row['azione'],
            $row['descrizione'],
            $row['tabella'],
            $row['record_id'],
            $row['ip_address']
        ]);
    }
    fclose($output);
    exit;
}

// Pulisci log vecchi
if (($_POST['action'] ?? '') === 'clean_old_logs') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    requireCsrfToken($csrf_token);
    
    $days = intval($_POST['days'] ?? 30);
    $cutoff_date = date('Y-m-d', strtotime("-$days days"));
    
    $deleted = Database::query("DELETE FROM log_attivita WHERE DATE(created_at) < ?", [$cutoff_date]);
    $success = "Eliminati $deleted log più vecchi di $days giorni";
    logActivity('delete', 'log_attivita', 0, "Puliti log più vecchi di $days giorni");
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Log Attività</h1>
        <p class="text-gray-600">Monitora tutte le attività del sistema</p>
    </div>

    <!-- Filtri -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Utente</label>
                    <input type="text" name="utente" value="<?php echo htmlspecialchars($filtro_utente); ?>" 
                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select name="tipo" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tutti</option>
                        <?php foreach ($tipi as $tipo): ?>
                        <option value="<?php echo $tipo['tipo']; ?>" <?php echo $filtro_tipo === $tipo['tipo'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipo['tipo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tabella</label>
                    <select name="tabella" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tutte</option>
                        <?php foreach ($tabelle as $tabella): ?>
                        <option value="<?php echo $tabella['tabella']; ?>" <?php echo $filtro_tabella === $tabella['tabella'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tabella['tabella']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Da Data</label>
                    <input type="date" name="data_da" value="<?php echo htmlspecialchars($filtro_data_da); ?>" 
                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">A Data</label>
                    <input type="date" name="data_a" value="<?php echo htmlspecialchars($filtro_data_a); ?>" 
                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="md:col-span-2 lg:col-span-5 flex justify-end space-x-3">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                        Applica Filtri
                    </button>
                    <a href="?" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Reset
                    </a>
                    <a href="?action=export_csv&<?php echo http_build_query($_GET); ?>" 
                       class="px-4 py-2 text-sm font-medium text-green-700 bg-white border border-green-300 rounded-md hover:bg-green-50">
                        Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Messaggi -->
    <?php if (isset($success)): ?>
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <!-- Statistiche -->
    <div class="mb-4 text-sm text-gray-600">
        Visualizzati <?php echo count($log); ?> di <?php echo $total; ?> record
    </div>

    <!-- Tabella Log -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Ora</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azione</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrizione</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dettagli</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($log as $entry): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($entry['utente'] ?? 'Sistema'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 py-1 bg-<?php echo getLogTypeColor($entry['tipo']); ?>-100 text-<?php echo getLogTypeColor($entry['tipo']); ?>-800 rounded-full text-xs">
                            <?php echo htmlspecialchars($entry['tipo']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($entry['azione']); ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo htmlspecialchars($entry['descrizione']); ?>
                        <?php if ($entry['tabella'] && $entry['record_id']): ?>
                        <br><span class="text-xs text-gray-500">
                            <?php echo $entry['tabella']; ?> #<?php echo $entry['record_id']; ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <?php if ($entry['dati_prima'] || $entry['dati_dopo']): ?>
                        <button onclick="showLogDetails(<?php echo $entry['id']; ?>)" 
                                class="text-blue-600 hover:text-blue-900">
                            Dettagli
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginazione -->
    <?php if ($total > $limite): ?>
    <div class="mt-6 flex justify-between items-center">
        <div class="text-sm text-gray-700">
            Pagina <?php echo $pagina; ?> di <?php echo ceil($total / $limite); ?>
        </div>
        <div class="flex space-x-2">
            <?php if ($pagina > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" 
               class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Precedente
            </a>
            <?php endif; ?>
            
            <?php if ($pagina < ceil($total / $limite)): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" 
               class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Successiva
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pulisci Log -->
    <div class="mt-8 bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Manutenzione Log</h2>
        </div>
        <div class="p-6">
            <form method="POST" class="flex items-end space-x-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="clean_old_logs">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Elimina log più vecchi di (giorni)</label>
                    <input type="number" name="days" value="30" min="1" max="365" 
                           class="mt-1 block w-32 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <button type="submit" 
                        onclick="return confirm('Sei sicuro di voler eliminare i log più vecchi?')"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                    Pulisci Log
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Dettagli Log -->
<div id="logDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Dettagli Log</h3>
            <div id="logDetailsContent" class="space-y-4"></div>
            <div class="flex justify-end mt-6">
                <button onclick="closeLogDetails()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Chiudi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showLogDetails(logId) {
    fetch(`../../api/admin_api.php?action=get_log_details&id=${logId}`)
        .then(response => response.json())
        .then(data => {
            let content = '';
            
            if (data.dati_prima) {
                content += `
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Dati Prima</h4>
                        <pre class="bg-gray-100 p-3 rounded text-sm overflow-auto max-h-40">${JSON.stringify(JSON.parse(data.dati_prima), null, 2)}</pre>
                    </div>
                `;
            }
            
            if (data.dati_dopo) {
                content += `
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Dati Dopo</h4>
                        <pre class="bg-gray-100 p-3 rounded text-sm overflow-auto max-h-40">${JSON.stringify(JSON.parse(data.dati_dopo), null, 2)}</pre>
                    </div>
                `;
            }
            
            if (data.ip_address) {
                content += `<p><strong>IP Address:</strong> ${data.ip_address}</p>`;
            }
            
            document.getElementById('logDetailsContent').innerHTML = content;
            document.getElementById('logDetailsModal').classList.remove('hidden');
        });
}

function closeLogDetails() {
    document.getElementById('logDetailsModal').classList.add('hidden');
}
</script>

<?php
function getLogTypeColor($type) {
    $colors = [
        'error' => 'red',
        'warning' => 'yellow',
        'info' => 'blue',
        'success' => 'green',
        'insert' => 'green',
        'update' => 'blue',
        'delete' => 'red',
        'login' => 'purple',
        'backup' => 'indigo'
    ];
    
    return $colors[$type] ?? 'gray';
}
?>

<?php include '../../includes/footer.php'; ?>