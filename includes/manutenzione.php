<?php
require_once '../../config/config.php';
requireAuth('preside');

$pageTitle = "Manutenzione Database";
include '../../includes/header.php';

$result = '';

// Esegui azioni manutenzione
if ($_POST['action'] ?? '') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    requireCsrfToken($csrf_token);
    
    $azione = $_POST['action'];
    
    try {
        switch ($azione) {
            case 'ottimizza_tabelle':
                $tables = Database::queryAll("SHOW TABLES");
                $optimized = [];
                foreach ($tables as $table) {
                    $tableName = array_values($table)[0];
                    Database::query("OPTIMIZE TABLE `$tableName`");
                    $optimized[] = $tableName;
                }
                $result = "Tabelle ottimizzate: " . implode(', ', $optimized);
                logActivity('maintenance', 'database', 0, "Ottimizzazione tabelle completata");
                break;
                
            case 'ripara_tabelle':
                $tables = Database::queryAll("SHOW TABLES");
                $repaired = [];
                foreach ($tables as $table) {
                    $tableName = array_values($table)[0];
                    $status = Database::queryOne("CHECK TABLE `$tableName`");
                    if (in_array($status['Msg_text'], ['OK', 'Table is already up to date'])) {
                        $repaired[] = $tableName;
                    } else {
                        Database::query("REPAIR TABLE `$tableName`");
                        $repaired[] = $tableName . " (riparata)";
                    }
                }
                $result = "Tabelle controllate/riparate: " . implode(', ', $repaired);
                logActivity('maintenance', 'database', 0, "Riparazione tabelle completata");
                break;
                
            case 'analizza_tabelle':
                $tables = Database::queryAll("SHOW TABLES");
                $analyzed = [];
                foreach ($tables as $table) {
                    $tableName = array_values($table)[0];
                    Database::query("ANALYZE TABLE `$tableName`");
                    $analyzed[] = $tableName;
                }
                $result = "Tabelle analizzate: " . implode(', ', $analyzed);
                logActivity('maintenance', 'database', 0, "Analisi tabelle completata");
                break;
                
            case 'controlla_integrita':
                $tables = Database::queryAll("SHOW TABLES");
                $results = [];
                foreach ($tables as $table) {
                    $tableName = array_values($table)[0];
                    $check = Database::queryOne("CHECK TABLE `$tableName`");
                    $results[] = $tableName . ": " . $check['Msg_text'];
                }
                $result = "Risultati controllo integrità:\n" . implode("\n", $results);
                logActivity('maintenance', 'database', 0, "Controllo integrità completato");
                break;
                
            case 'rigenera_statistiche':
                $tables = Database::queryAll("SHOW TABLES");
                foreach ($tables as $table) {
                    $tableName = array_values($table)[0];
                    Database::query("ANALYZE TABLE `$tableName`");
                }
                $result = "Statistiche rigenerate per tutte le tabelle";
                logActivity('maintenance', 'database', 0, "Statistiche rigenerate");
                break;
                
            case 'pulisci_dati_orfani':
                $cleaned = [];
                
                // Lezioni senza docente
                $deleted = Database::query("DELETE FROM calendario_lezioni WHERE docente_id NOT IN (SELECT id FROM docenti)");
                if ($deleted > 0) {
                    $cleaned[] = "Lezioni senza docente: $deleted";
                }
                
                // Lezioni senza classe
                $deleted = Database::query("DELETE FROM calendario_lezioni WHERE classe_id NOT IN (SELECT id FROM classi)");
                if ($deleted > 0) {
                    $cleaned[] = "Lezioni senza classe: $deleted";
                }
                
                // Assegnazioni senza docente/classe/materia
                $deleted = Database::query("
                    DELETE FROM classi_materie_docenti 
                    WHERE docente_id NOT IN (SELECT id FROM docenti) 
                    OR classe_id NOT IN (SELECT id FROM classi) 
                    OR materia_id NOT IN (SELECT id FROM materie)
                ");
                if ($deleted > 0) {
                    $cleaned[] = "Assegnazioni orfane: $deleted";
                }
                
                $result = $cleaned ? "Dati puliti:\n" . implode("\n", $cleaned) : "Nessun dato orfano trovato";
                logActivity('maintenance', 'database', 0, "Pulizia dati orfani completata");
                break;
                
            case 'reset_contatori':
                // Reset auto_increment per tabelle (se necessario)
                $tables = ['log_attivita', 'notifiche', 'conflitti_orario'];
                $reset = [];
                foreach ($tables as $table) {
                    $maxId = Database::queryOne("SELECT MAX(id) as max_id FROM $table");
                    if ($maxId['max_id'] > 1000000) { // Solo se necessario
                        Database::query("ALTER TABLE $table AUTO_INCREMENT = 1");
                        $reset[] = $table;
                    }
                }
                $result = $reset ? "Contatori reset per: " . implode(', ', $reset) : "Nessun reset necessario";
                logActivity('maintenance', 'database', 0, "Reset contatori completato");
                break;
        }
    } catch (Exception $e) {
        $result = "Errore: " . $e->getMessage();
        logActivity('error', 'database', 0, "Errore manutenzione: " . $e->getMessage());
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Manutenzione Database</h1>
        <p class="text-gray-600">Strumenti di manutenzione e ottimizzazione database</p>
    </div>

    <!-- Risultato -->
    <?php if ($result): ?>
    <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
        <pre class="whitespace-pre-wrap"><?php echo htmlspecialchars($result); ?></pre>
    </div>
    <?php endif; ?>

    <!-- Azioni Manutenzione -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Ottimizzazione -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ottimizzazione</h3>
            <p class="text-gray-600 mb-4">Deframmenta le tabelle e recupera spazio non utilizzato</p>
            <form method="POST" onsubmit="return confirm('Vuoi ottimizzare tutte le tabelle?')">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="ottimizza_tabelle">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Ottimizza Tabelle
                </button>
            </form>
        </div>

        <!-- Riparazione -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Riparazione</h3>
            <p class="text-gray-600 mb-4">Controlla e ripara tabelle corrotte</p>
            <form method="POST" onsubmit="return confirm('Vuoi controllare e riparare le tabelle?')">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="ripara_tabelle">
                <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg">
                    Ripara Tabelle
                </button>
            </form>
        </div>

        <!-- Analisi -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Analisi</h3>
            <p class="text-gray-600 mb-4">Aggiorna le statistiche per l'ottimizzatore query</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="analizza_tabelle">
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    Analizza Tabelle
                </button>
            </form>
        </div>

        <!-- Controllo Integrità -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Controllo Integrità</h3>
            <p class="text-gray-600 mb-4">Verifica l'integrità di tutte le tabelle</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="controlla_integrita">
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                    Controlla Integrità
                </button>
            </form>
        </div>

        <!-- Pulizia Dati Orfani -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Pulizia Dati Orfani</h3>
            <p class="text-gray-600 mb-4">Elimina record senza riferimenti validi</p>
            <form method="POST" onsubmit="return confirm('ATTENZIONE: Questa operazione elimina dati permanentemente. Continuare?')">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="pulisci_dati_orfani">
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                    Pulisci Dati Orfani
                </button>
            </form>
        </div>

        <!-- Reset Contatori -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Reset Contatori</h3>
            <p class="text-gray-600 mb-4">Resetta auto_increment per tabelle con ID elevati</p>
            <form method="POST" onsubmit="return confirm('Vuoi resettare i contatori auto_increment?')">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="reset_contatori">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
                    Reset Contatori
                </button>
            </form>
        </div>
    </div>

    <!-- Informazioni Database -->
    <div class="mt-8 bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Informazioni Database</h2>
        </div>
        <div class="p-6">
            <?php
            $dbSize = Database::queryOne("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb,
                    COUNT(*) as table_count
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [DB_NAME]);
            
            $tableStatus = Database::queryAll("SHOW TABLE STATUS");
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900"><?php echo $dbSize['size_mb']; ?> MB</p>
                    <p class="text-sm text-gray-600">Dimensione Database</p>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900"><?php echo $dbSize['table_count']; ?></p>
                    <p class="text-sm text-gray-600">Tabelle</p>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900"><?php echo count($tableStatus); ?></p>
                    <p class="text-sm text-gray-600">Tabelle Totali</p>
                </div>
            </div>

            <h3 class="text-md font-medium text-gray-900 mb-4">Stato Tabelle</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tabella</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rows</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Index Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Engine</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tableStatus as $table): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $table['Name']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($table['Rows']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo round($table['Data_length'] / 1024 / 1024, 2); ?> MB
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo round($table['Index_length'] / 1024 / 1024, 2); ?> MB
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $table['Engine']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>