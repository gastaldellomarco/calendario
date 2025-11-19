<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Notifiche";
$current_page = 'notifiche';

// Filtri
$filtro_stato = $_GET['stato'] ?? 'non_lette';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_priorita = $_GET['priorita'] ?? '';
$filtro_data_da = $_GET['data_da'] ?? '';
$filtro_data_a = $_GET['data_a'] ?? '';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$per_pagina = 20;

// Query base
$where_conditions = ["n.utente_id = :user_id"];
$params = [':user_id' => $_SESSION['user_id']];

// Applica filtri
if ($filtro_stato === 'non_lette') {
    $where_conditions[] = "n.letta = 0";
} elseif ($filtro_stato === 'lette') {
    $where_conditions[] = "n.letta = 1";
}

if (!empty($filtro_tipo)) {
    $where_conditions[] = "n.tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if (!empty($filtro_priorita)) {
    $where_conditions[] = "n.priorita = :priorita";
    $params[':priorita'] = $filtro_priorita;
}

if (!empty($filtro_data_da)) {
    $where_conditions[] = "DATE(n.created_at) >= :data_da";
    $params[':data_da'] = $filtro_data_da;
}

if (!empty($filtro_data_a)) {
    $where_conditions[] = "DATE(n.created_at) <= :data_a";
    $params[':data_a'] = $filtro_data_a;
}

$where_sql = implode(" AND ", $where_conditions);

// Conta totale
$sql_count = "SELECT COUNT(*) as total FROM notifiche n WHERE $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$totale_notifiche = $stmt->fetchColumn();

// Ottieni notifiche
$offset = ($pagina - 1) * $per_pagina;
$sql = "SELECT n.* FROM notifiche n 
        WHERE $where_sql 
        ORDER BY n.created_at DESC 
        LIMIT $offset, $per_pagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifiche = $stmt->fetchAll();

// Statistiche
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(n.letta = 0) as non_lette,
    SUM(n.letta = 1) as lette
    FROM notifiche n 
    WHERE n.utente_id = ?";
$stmt = $pdo->prepare($sql_stats);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Notifiche
            </h1>
            <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    <span class="font-medium text-blue-600"><?php echo $stats['non_lette']; ?></span>
                    <span class="ml-1">non lette</span>
                </div>
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    <span class="font-medium text-gray-600"><?php echo $stats['total']; ?></span>
                    <span class="ml-1">totale notifiche</span>
                </div>
            </div>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
            <button type="button" id="segna-tutte-lette" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-check-double mr-2"></i>Segna tutte come lette
            </button>
            <button type="button" id="elimina-lette" 
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                <i class="fas fa-trash mr-2"></i>Elimina notifiche lette
            </button>
        </div>
    </div>

    <!-- Filtri -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <form method="GET" class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="stato" class="block text-sm font-medium text-gray-700">Stato</label>
                    <select id="stato" name="stato" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="tutte" <?php echo $filtro_stato === 'tutte' ? 'selected' : ''; ?>>Tutte</option>
                        <option value="non_lette" <?php echo $filtro_stato === 'non_lette' ? 'selected' : ''; ?>>Non lette</option>
                        <option value="lette" <?php echo $filtro_stato === 'lette' ? 'selected' : ''; ?>>Lette</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select id="tipo" name="tipo" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">Tutti i tipi</option>
                        <option value="info" <?php echo $filtro_tipo === 'info' ? 'selected' : ''; ?>>Info</option>
                        <option value="avviso" <?php echo $filtro_tipo === 'avviso' ? 'selected' : ''; ?>>Avviso</option>
                        <option value="allerta" <?php echo $filtro_tipo === 'allerta' ? 'selected' : ''; ?>>Allerta</option>
                        <option value="conflitto" <?php echo $filtro_tipo === 'conflitto' ? 'selected' : ''; ?>>Conflitto</option>
                        <option value="sistema" <?php echo $filtro_tipo === 'sistema' ? 'selected' : ''; ?>>Sistema</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="priorita" class="block text-sm font-medium text-gray-700">Priorità</label>
                    <select id="priorita" name="priorita" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">Tutte le priorità</option>
                        <option value="urgente" <?php echo $filtro_priorita === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                        <option value="alta" <?php echo $filtro_priorita === 'alta' ? 'selected' : ''; ?>>Alta</option>
                        <option value="media" <?php echo $filtro_priorita === 'media' ? 'selected' : ''; ?>>Media</option>
                        <option value="bassa" <?php echo $filtro_priorita === 'bassa' ? 'selected' : ''; ?>>Bassa</option>
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
                    <a href="?stato=non_lette" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Reset
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Applica filtri
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista Notifiche -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <ul class="divide-y divide-gray-200">
            <?php if (empty($notifiche)): ?>
                <li class="px-6 py-12 text-center">
                    <i class="fas fa-bell-slash text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">Nessuna notifica trovata</p>
                </li>
            <?php else: ?>
                <?php foreach ($notifiche as $notifica): ?>
                    <li class="px-6 py-4 hover:bg-gray-50 <?php echo !$notifica['letta'] ? 'bg-blue-50' : ''; ?>" 
                        data-notifica-id="<?php echo $notifica['id']; ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center flex-1 min-w-0">
                                <!-- Icona priorità -->
                                <div class="flex-shrink-0 mr-4">
                                    <?php
                                    $icon_class = [
                                        'urgente' => 'fas fa-exclamation-circle text-red-500',
                                        'alta' => 'fas fa-exclamation-triangle text-orange-500',
                                        'media' => 'fas fa-info-circle text-blue-500',
                                        'bassa' => 'fas fa-bell text-gray-400'
                                    ][$notifica['priorita']] ?? 'fas fa-bell text-gray-400';
                                    ?>
                                    <i class="<?php echo $icon_class; ?> text-lg"></i>
                                </div>

                                <!-- Contenuto -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium <?php echo !$notifica['letta'] ? 'text-gray-900 font-bold' : 'text-gray-700'; ?> truncate">
                                            <?php echo htmlspecialchars($notifica['titolo']); ?>
                                        </p>
                                        <div class="ml-2 flex-shrink-0 flex">
                                            <p class="text-xs text-gray-500">
                                                <?php echo date('d/m/Y H:i', strtotime($notifica['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <?php echo htmlspecialchars($notifica['messaggio']); ?>
                                    </p>
                                    
                                    <?php if ($notifica['azione_url']): ?>
                                        <div class="mt-2">
                                            <a href="<?php echo BASE_URL . $notifica['azione_url']; ?>" 
                                               class="inline-flex items-center text-sm text-blue-600 hover:text-blue-500">
                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                Vai alla risorsa
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Azioni -->
                            <div class="ml-4 flex-shrink-0 flex space-x-2">
                                <?php if (!$notifica['letta']): ?>
                                    <button class="segna-letta text-green-600 hover:text-green-900" 
                                            title="Segna come letta">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="elimina-notifica text-red-600 hover:text-red-900" 
                                        title="Elimina notifica">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Paginazione -->
    <?php if ($totale_notifiche > $per_pagina): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-6">
            <div class="flex-1 flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-700">
                        Mostrando 
                        <span class="font-medium"><?php echo $offset + 1; ?></span>
                        a 
                        <span class="font-medium"><?php echo min($offset + $per_pagina, $totale_notifiche); ?></span>
                        di 
                        <span class="font-medium"><?php echo $totale_notifiche; ?></span>
                        risultati
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php if ($pagina > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $pagine_totali = ceil($totale_notifiche / $per_pagina);
                        $pagine_mostrate = 5;
                        $inizio = max(1, $pagina - floor($pagine_mostrate / 2));
                        $fine = min($pagine_totali, $inizio + $pagine_mostrate - 1);
                        $inizio = max(1, $fine - $pagine_mostrate + 1);

                        for ($i = $inizio; $i <= $fine; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                               class="<?php echo $i == $pagina ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina < $pagine_totali): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Segna notifica come letta
    document.querySelectorAll('.segna-letta').forEach(button => {
        button.addEventListener('click', function() {
            const notificaId = this.closest('li').dataset.notificaId;
            segnaNotificaLetta(notificaId);
        });
    });

    // Elimina notifica
    document.querySelectorAll('.elimina-notifica').forEach(button => {
        button.addEventListener('click', function() {
            const notificaId = this.closest('li').dataset.notificaId;
            eliminaNotifica(notificaId);
        });
    });

    // Segna tutte come lette
    document.getElementById('segna-tutte-lette').addEventListener('click', function() {
        if (confirm('Segnare tutte le notifiche come lette?')) {
            fetch('../api/notifiche_api.php?action=mark_all_as_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    });

    // Elimina notifiche lette
    document.getElementById('elimina-lette').addEventListener('click', function() {
        if (confirm('Eliminare tutte le notifiche lette? Questa azione non può essere annullata.')) {
            fetch('../api/notifiche_api.php?action=delete_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    });

    function segnaNotificaLetta(notificaId) {
        fetch('../api/notifiche_api.php?action=mark_as_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notifica_id: notificaId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    function eliminaNotifica(notificaId) {
        if (confirm('Eliminare questa notifica?')) {
            fetch('../api/notifiche_api.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notifica_id: notificaId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>