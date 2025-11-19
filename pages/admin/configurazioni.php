<?php
require_once '../../config/config.php';
requireAuth('preside');

$pageTitle = "Configurazioni Sistema";
include '../../includes/header.php';

// Salva configurazioni
if ($_POST['action'] ?? '' === 'save_config') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    requireCsrfToken($csrf_token);
    
    foreach ($_POST['config'] as $chiave => $valore) {
        $config = Database::queryOne("SELECT * FROM configurazioni WHERE chiave = ?", [$chiave]);
        
        if ($config) {
            // Validazione tipo
            $valid = true;
            switch ($config['tipo']) {
                case 'integer':
                    $valore = intval($valore);
                    break;
                case 'boolean':
                    $valore = $valore ? 1 : 0;
                    break;
                case 'json':
                    if (!empty($valore)) {
                        json_decode($valore);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $valid = false;
                            $error = "JSON non valido per: $chiave";
                        }
                    }
                    break;
            }
            
            if ($valid) {
                Database::query("UPDATE configurazioni SET valore = ? WHERE chiave = ?", [$valore, $chiave]);
            }
        }
    }
    
    if (!isset($error)) {
        $success = "Configurazioni salvate con successo!";
        logActivity('update', 'configurazioni', 0, "Configurazioni sistema aggiornate");
    }
}

// Ripristina default
if ($_POST['action'] ?? '' === 'reset_default') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    requireCsrfToken($csrf_token);
    
    Database::query("UPDATE configurazioni SET valore = NULL WHERE modificabile = 1");
    $success = "Configurazioni ripristinate ai valori di default!";
    logActivity('update', 'configurazioni', 0, "Configurazioni sistema ripristinate al default");
}

// Carica configurazioni
$configurazioni = Database::queryAll("SELECT * FROM configurazioni ORDER BY categoria, chiave");
$configByCategory = [];
foreach ($configurazioni as $config) {
    $configByCategory[$config['categoria']][] = $config;
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Configurazioni Sistema</h1>
        <p class="text-gray-600">Gestisci le impostazioni del sistema</p>
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

    <form method="POST" id="configForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="save_config">
        
        <!-- Tabs per categorie -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <?php foreach (array_keys($configByCategory) as $index => $categoria): ?>
                    <button type="button" 
                            onclick="showCategory('<?php echo $categoria; ?>')" 
                            class="<?php echo $index === 0 ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        <?php echo ucfirst($categoria); ?>
                    </button>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <!-- Contenuto tabs -->
        <?php foreach ($configByCategory as $categoria => $configs): ?>
        <div id="category-<?php echo $categoria; ?>" class="category-content <?php echo $categoria === array_keys($configByCategory)[0] ? '' : 'hidden'; ?>">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Configurazioni <?php echo $categoria; ?></h2>
                </div>
                <div class="p-6 space-y-6">
                    <?php foreach ($configs as $config): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <div class="md:col-span-1">
                            <label class="block text-sm font-medium text-gray-700">
                                <?php echo $config['chiave']; ?>
                            </label>
                            <?php if ($config['descrizione']): ?>
                            <p class="text-sm text-gray-500 mt-1"><?php echo $config['descrizione']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="md:col-span-2">
                            <?php if ($config['tipo'] === 'boolean'): ?>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="config[<?php echo $config['chiave']; ?>]" 
                                           value="1" 
                                           <?php echo ($config['valore'] ?? '0') === '1' ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                           <?php echo $config['modificabile'] ? '' : 'disabled'; ?>>
                                    <span class="ml-2 text-sm text-gray-900">Abilitato</span>
                                </div>
                            
                            <?php elseif ($config['tipo'] === 'integer'): ?>
                                <input type="number" 
                                       name="config[<?php echo $config['chiave']; ?>]" 
                                       value="<?php echo htmlspecialchars($config['valore'] ?? ''); ?>"
                                       class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                       <?php echo $config['modificabile'] ? '' : 'readonly'; ?>>
                            
                            <?php elseif ($config['tipo'] === 'json'): ?>
                                <textarea name="config[<?php echo $config['chiave']; ?>]" 
                                          rows="3"
                                          class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                          <?php echo $config['modificabile'] ? '' : 'readonly'; ?>><?php echo htmlspecialchars($config['valore'] ?? ''); ?></textarea>
                            
                            <?php else: ?>
                                <input type="text" 
                                       name="config[<?php echo $config['chiave']; ?>]" 
                                       value="<?php echo htmlspecialchars($config['valore'] ?? ''); ?>"
                                       class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                       <?php echo $config['modificabile'] ? '' : 'readonly'; ?>>
                            <?php endif; ?>
                            
                            <?php if (!$config['modificabile']): ?>
                            <p class="text-xs text-gray-500 mt-1">Non modificabile</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Pulsanti -->
        <div class="mt-6 flex justify-end space-x-3">
            <button type="button" onclick="resetToDefault()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Ripristina Default
            </button>
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                Salva Configurazioni
            </button>
        </div>
    </form>
</div>

<script>
function showCategory(categoria) {
    // Nascondi tutti i contenuti
    document.querySelectorAll('.category-content').forEach(el => {
        el.classList.add('hidden');
    });
    
    // Mostra categoria selezionata
    document.getElementById('category-' + categoria).classList.remove('hidden');
    
    // Aggiorna tab attiva
    document.querySelectorAll('nav button').forEach(btn => {
        btn.classList.remove('border-blue-500', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    event.target.classList.remove('border-transparent', 'text-gray-500');
    event.target.classList.add('border-blue-500', 'text-blue-600');
}

function resetToDefault() {
    if (confirm('Sei sicuro di voler ripristinare tutte le configurazioni ai valori di default?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = 'csrf_token';
        csrfToken.value = '<?php echo generateCsrfToken(); ?>';
        form.appendChild(csrfToken);
        
        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'reset_default';
        form.appendChild(action);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>