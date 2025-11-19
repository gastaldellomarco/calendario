<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/NotificheManager.php';

// Solo admin e preside possono accedere
checkRole(['amministratore', 'preside']);

$page_title = "Gestione Conflitti";
$current_page = 'conflitti';

// Filtri
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_gravita = $_GET['gravita'] ?? '';
$filtro_risolto = $_GET['risolto'] ?? '0';
$filtro_data_da = $_GET['data_da'] ?? '';
$filtro_data_a = $_GET['data_a'] ?? '';

// Statistiche
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(risolto = 0) as aperti,
    SUM(risolto = 1) as risolti,
    SUM(gravita = 'critico') as critici,
    SUM(gravita = 'error') as errori,
    SUM(gravita = 'warning') as warning
    FROM conflitti_orario";
$stmt = $pdo->prepare($sql_stats);
$stmt->execute();
$stats = $stmt->fetch();

// Query conflitti
$where_conditions = [];
$params = [];

if (!empty($filtro_tipo)) {
    $where_conditions[] = "tipo = ?";
    $params[] = $filtro_tipo;
}

if (!empty($filtro_gravita)) {
    $where_conditions[] = "gravita = ?";
    $params[] = $filtro_gravita;
}

if ($filtro_risolto !== '') {
    $where_conditions[] = "risolto = ?";
    $params[] = $filtro_risolto;
}

if (!empty($filtro_data_da)) {
    $where_conditions[] = "data_conflitto >= ?";
    $params[] = $filtro_data_da;
}

if (!empty($filtro_data_a)) {
    $where_conditions[] = "data_conflitto <= ?";
    $params[] = $filtro_data_a;
}

$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$sql = "SELECT c.*, 
               d.cognome as docente_cognome, d.nome as docente_nome,
               cl.nome as classe_nome,
               a.nome as aula_nome,
               u.nome_visualizzato as risolto_da_nome
        FROM conflitti_orario c
        LEFT JOIN docenti d ON c.docente_id = d.id
        LEFT JOIN classi cl ON c.classe_id = cl.id
        LEFT JOIN aule a ON c.aula_id = a.id
        LEFT JOIN utenti u ON c.risolto_da = u.id
        $where_sql
        ORDER BY 
            CASE gravita 
                WHEN 'critico' THEN 1
                WHEN 'error' THEN 2
                WHEN 'warning' THEN 3
            END,
            data_conflitto DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conflitti = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Gestione Conflitti
            </h1>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button type="button" id="ricarica-conflitti" 
                    class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-sync-alt mr-2"></i>Ricarica Conflitti
            </button>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <!-- Totale Conflitti -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-gray-500 rounded-md p-3">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Totale Conflitti</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aperti -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                        <i class="fas fa-clock text-white text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Aperti</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['aperti']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critici -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-600 rounded-md p-3">
                        <i class="fas fa-fire text-white text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Critici</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['critici']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risolti -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Risolti</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['risolti']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtri -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <form method="GET" class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo Conflitto</label>
                    <select id="tipo" name="tipo" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">Tutti i tipi</option>
                        <option value="doppia_assegnazione_docente" <?php echo $filtro_tipo === 'doppia_assegnazione_docente' ? 'selected' : ''; ?>>Doppia assegnazione docente</option>
                        <option value="doppia_aula" <?php echo $filtro_tipo === 'doppia_aula' ? 'selected' : ''; ?>>Doppia assegnazione aula</option>
                        <option value="vincolo_docente" <?php echo $filtro_tipo === 'vincolo_docente' ? 'selected' : ''; ?>>Vincolo docente</option>
                        <option value="vincolo_classe" <?php echo $filtro_tipo === 'vincolo_classe' ? 'selected' : ''; ?>>Vincolo classe</option>
                        <option value="superamento_ore" <?php echo $filtro_tipo === 'superamento_ore' ? 'selected' : ''; ?>>Superamento ore</option>
                        <option value="aula_non_adeguata" <?php echo $filtro_tipo === 'aula_non_adeguata' ? 'selected' : ''; ?>>Aula non adeguata</option>
                        <option value="sede_multipla" <?php echo $filtro_tipo === 'sede_multipla' ? 'selected' : ''; ?>>Sede multipla</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="gravita" class="block text-sm font-medium text-gray-700">Gravità</label>
                    <select id="gravita" name="gravita" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">Tutte le gravità</option>
                        <option value="critico" <?php echo $filtro_gravita === 'critico' ? 'selected' : ''; ?>>Critico</option>
                        <option value="error" <?php echo $filtro_gravita === 'error' ? 'selected' : ''; ?>>Errore</option>
                        <option value="warning" <?php echo $filtro_gravita === 'warning' ? 'selected' : ''; ?>>Warning</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="risolto" class="block text-sm font-medium text-gray-700">Stato</label>
                    <select id="risolto" name="risolto" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">Tutti</option>
                        <option value="0" <?php echo $filtro_risolto === '0' ? 'selected' : ''; ?>>Aperti</option>
                        <option value="1" <?php echo $filtro_risolto === '1' ? 'selected' : ''; ?>>Risolti</option>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label for="data_da" class="block text-sm font-medium text-gray-700">Data da</label>
                    <input type="date" id="data_da" name="data_da" value="<?php echo htmlspecialchars($filtro_data_da); ?>" 
                           class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>

                <div class="sm:col-span-3">
                    <label for="data_a" class="block text-sm font-medium text-gray-700">Data a</label>
                    <input type="date" id="data_a" name="data_a" value="<?php echo htmlspecialchars($filtro_data_a); ?>" 
                           class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>

                <div class="sm:col-span-6 flex justify-end space-x-3">
                    <a href="?" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Reset
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Applica filtri
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista Conflitti -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <?php if (empty($conflitti)): ?>
            <div class="px-6 py-12 text-center">
                <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                <p class="text-gray-500">Nessun conflitto trovato</p>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-gray-200">
                <?php foreach ($conflitti as $conflitto): ?>
                    <li class="px-6 py-4 hover:bg-gray-50 <?php echo $conflitto['risolto'] ? 'bg-green-50' : ''; ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center flex-1 min-w-0">
                                <!-- Icona gravità -->
                                <div class="flex-shrink-0 mr-4">
                                    <?php
                                    $icon_class = [
                                        'critico' => 'fas fa-fire text-red-600',
                                        'error' => 'fas fa-exclamation-circle text-orange-500',
                                        'warning' => 'fas fa-exclamation-triangle text-yellow-500'
                                    ][$conflitto['gravita']] ?? 'fas fa-exclamation-triangle text-gray-400';
                                    ?>
                                    <i class="<?php echo $icon_class; ?> text-lg"></i>
                                </div>

                                <!-- Contenuto -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars($conflitto['titolo']); ?>
                                            </p>
                                            <div class="flex items-center mt-1 space-x-4 text-xs text-gray-500">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo [
                                                        'critico' => 'bg-red-100 text-red-800',
                                                        'error' => 'bg-orange-100 text-orange-800',
                                                        'warning' => 'bg-yellow-100 text-yellow-800'
                                                    ][$conflitto['gravita']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo ucfirst($conflitto['gravita']); ?>
                                                </span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo str_replace('_', ' ', $conflitto['tipo']); ?>
                                                </span>
                                                <?php if ($conflitto['risolto']): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Risolto
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="ml-2 flex-shrink-0 flex flex-col items-end">
                                            <p class="text-xs text-gray-500">
                                                <?php echo date('d/m/Y', strtotime($conflitto['data_conflitto'])); ?>
                                            </p>
                                            <?php if ($conflitto['risolto_da_nome']): ?>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    Risolto da: <?php echo htmlspecialchars($conflitto['risolto_da_nome']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <p class="text-sm text-gray-500 mt-2">
                                        <?php echo htmlspecialchars($conflitto['descrizione']); ?>
                                    </p>
                                    
                                    <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                                        <?php if ($conflitto['docente_cognome']): ?>
                                            <span><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($conflitto['docente_cognome'] . ' ' . $conflitto['docente_nome']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($conflitto['classe_nome']): ?>
                                            <span><i class="fas fa-users mr-1"></i><?php echo htmlspecialchars($conflitto['classe_nome']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($conflitto['aula_nome']): ?>
                                            <span><i class="fas fa-door-open mr-1"></i><?php echo htmlspecialchars($conflitto['aula_nome']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Azioni -->
                            <div class="ml-4 flex-shrink-0 flex space-x-2">
                                <?php if (!$conflitto['risolto']): ?>
                                    <a href="risolvi_conflitto.php?id=<?php echo $conflitto['id']; ?>" 
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class="fas fa-wrench mr-1"></i>Risolvi
                                    </a>
                                    <button class="ignora-conflitto inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50" 
                                            data-conflitto-id="<?php echo $conflitto['id']; ?>">
                                        <i class="fas fa-eye-slash mr-1"></i>Ignora
                                    </button>
                                <?php else: ?>
                                    <button class="riapri-conflitto inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50" 
                                            data-conflitto-id="<?php echo $conflitto['id']; ?>">
                                        <i class="fas fa-undo mr-1"></i>Riapri
                                    </button>
                                <?php endif; ?>
                                <button class="elimina-conflitto inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50" 
                                        data-conflitto-id="<?php echo $conflitto['id']; ?>">
                                    <i class="fas fa-trash mr-1"></i>Elimina
                                </button>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ricarica conflitti
    document.getElementById('ricarica-conflitti').addEventListener('click', function() {
        location.reload();
    });

    // Ignora conflitto
    document.querySelectorAll('.ignora-conflitto').forEach(button => {
        button.addEventListener('click', function() {
            const conflittoId = this.dataset.conflittoId;
            if (confirm('Ignorare questo conflitto? Sarà marcato come risolto senza azioni.')) {
                fetch('../api/conflitti_api.php?action=ignora', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conflitto_id: conflittoId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        });
    });

    // Riapri conflitto
    document.querySelectorAll('.riapri-conflitto').forEach(button => {
        button.addEventListener('click', function() {
            const conflittoId = this.dataset.conflittoId;
            if (confirm('Riaprire questo conflitto?')) {
                fetch('../api/conflitti_api.php?action=riapri', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conflitto_id: conflittoId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        });
    });

    // Elimina conflitto
    document.querySelectorAll('.elimina-conflitto').forEach(button => {
        button.addEventListener('click', function() {
            const conflittoId = this.dataset.conflittoId;
            if (confirm('Eliminare definitivamente questo conflitto?')) {
                fetch('../api/conflitti_api.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conflitto_id: conflittoId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>