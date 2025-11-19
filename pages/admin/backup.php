<?php
require_once '../../config/config.php';
requireAuth('preside');

$pageTitle = "Gestione Backup";
include '../../includes/header.php';

require_once '../../includes/BackupManager.php';
$backupManager = new BackupManager();

// Azioni backup
if (($_GET['action'] ?? '') === 'create_backup') {
    try {
        $result = $backupManager->creaBackup('manuale');
        $success = "Backup creato con successo: " . $result['file_path'];
        logActivity('backup', 'sistema', 0, "Backup manuale creato: " . $result['file_name']);
    } catch (Exception $e) {
        $error = "Errore durante il backup: " . $e->getMessage();
        logActivity('error', 'sistema', 0, "Backup fallito: " . $e->getMessage());
    }
}

if (($_GET['action'] ?? '') === 'delete_backup' && isset($_GET['file']) && !empty($_GET['file'])) {
    $file = sanitizeInput($_GET['file']);
    if ($backupManager->eliminaBackup($file)) {
        $success = "Backup eliminato con successo";
        logActivity('delete', 'backup', 0, "Backup eliminato: $file");
    } else {
        $error = "Errore durante l'eliminazione del backup";
    }
}

// Lista backup
$backups = $backupManager->listaBackup();
$snapshots = Database::queryAll("SELECT * FROM snapshot_calendario ORDER BY created_at DESC LIMIT 10");
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Gestione Backup</h1>
        <p class="text-gray-600">Gestisci backup database e snapshot calendario</p>
    </div>

    <!-- Messaggi -->
    <?php if (isset($error)): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Backup Manuali -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Backup Database</h2>
            </div>
            <div class="p-6">
                <!-- Pulsante Crea Backup -->
                <div class="mb-6">
                    <a href="?action=create_backup" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-download mr-2"></i>
                        Crea Backup Manuale
                    </a>
                    <p class="text-sm text-gray-500 mt-2">Crea un backup completo del database</p>
                </div>

                <!-- Lista Backup -->
                <h3 class="text-md font-medium text-gray-900 mb-4">Backup Esistenti</h3>
                <?php if (empty($backups)): ?>
                    <p class="text-gray-500">Nessun backup trovato</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($backups as $backup): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <p class="font-medium text-sm"><?php echo htmlspecialchars($backup['name']); ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('d/m/Y H:i', $backup['timestamp']); ?> • 
                                    <?php echo number_format($backup['size'] / 1024 / 1024, 2); ?> MB
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <a href="../../api/admin_api.php?action=download_backup&file=<?php echo urlencode($backup['name']); ?>" 
                                   class="text-blue-600 hover:text-blue-900" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button onclick="deleteBackup('<?php echo $backup['name']; ?>')" 
                                        class="text-red-600 hover:text-red-900" title="Elimina">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Snapshot Calendario -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Snapshot Calendario</h2>
            </div>
            <div class="p-6">
                <!-- Pulsante Crea Snapshot -->
                <div class="mb-6">
                    <button onclick="creaSnapshot()" 
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-camera mr-2"></i>
                        Crea Snapshot
                    </button>
                    <p class="text-sm text-gray-500 mt-2">Salva lo stato corrente del calendario</p>
                </div>

                <!-- Lista Snapshot -->
                <h3 class="text-md font-medium text-gray-900 mb-4">Snapshot Recenti</h3>
                <?php if (empty($snapshots)): ?>
                    <p class="text-gray-500">Nessuno snapshot trovato</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($snapshots as $snapshot): ?>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="font-medium text-sm"><?php echo htmlspecialchars($snapshot['nome']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo date('d/m/Y H:i', strtotime($snapshot['created_at'])); ?> • 
                                        <?php echo $snapshot['tipo']; ?>
                                    </p>
                                </div>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                    <?php echo number_format($snapshot['dimensione_mb'] ?? 0, 2); ?> MB
                                </span>
                            </div>
                            <?php if ($snapshot['descrizione']): ?>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($snapshot['descrizione']); ?></p>
                            <?php endif; ?>
                            <div class="flex space-x-2">
                                <button onclick="ripristinaSnapshot(<?php echo $snapshot['id']; ?>)" 
                                        class="text-sm text-yellow-600 hover:text-yellow-900">
                                    <i class="fas fa-undo mr-1"></i>Ripristina
                                </button>
                                <button onclick="eliminaSnapshot(<?php echo $snapshot['id']; ?>)" 
                                        class="text-sm text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash mr-1"></i>Elimina
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Configurazione Backup Automatici -->
    <div class="mt-8 bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Configurazione Backup Automatici</h2>
        </div>
        <div class="p-6">
            <form id="autoBackupForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="backup_automatico_enabled" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <span class="ml-2 text-sm font-medium text-gray-900">Abilita backup automatici</span>
                        </label>
                        <p class="text-sm text-gray-500 mt-1">Esegui backup automatici del database</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Frequenza</label>
                        <select name="backup_frequency" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="daily">Giornaliero</option>
                            <option value="weekly">Settimanale</option>
                            <option value="monthly">Mensile</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Orario Esecuzione</label>
                        <input type="time" name="backup_time" value="02:00" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Retention (giorni)</label>
                        <input type="number" name="backup_retention_days" value="30" min="1" max="365" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                        Salva Configurazione
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteBackup(fileName) {
    if (confirm('Sei sicuro di voler eliminare questo backup?')) {
        window.location.href = `?action=delete_backup&file=${fileName}`;
    }
}

function creaSnapshot() {
    const nome = prompt('Nome dello snapshot:');
    const descrizione = prompt('Descrizione (opzionale):');
    
    if (nome) {
        fetch('../../api/admin_api.php?action=crea_snapshot', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nome: nome,
                descrizione: descrizione || '',
                tipo: 'manuale'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Snapshot creato con successo!');
                location.reload();
            } else {
                alert('Errore: ' + data.message);
            }
        });
    }
}

function ripristinaSnapshot(id) {
    if (confirm('ATTENZIONE: Ripristinare questo snapshot sovrascriverà il calendario corrente. Continuare?')) {
        fetch('../../api/admin_api.php?action=ripristina_snapshot', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Snapshot ripristinato con successo!');
                location.reload();
            } else {
                alert('Errore: ' + data.message);
            }
        });
    }
}

function eliminaSnapshot(id) {
    if (confirm('Sei sicuro di voler eliminare questo snapshot?')) {
        fetch('../../api/admin_api.php?action=elimina_snapshot', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Snapshot eliminato!');
                location.reload();
            } else {
                alert('Errore: ' + data.message);
            }
        });
    }
}

// Configurazione backup automatici
document.getElementById('autoBackupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../../api/admin_api.php?action=config_backup_auto', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>