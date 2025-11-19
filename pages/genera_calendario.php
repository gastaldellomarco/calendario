<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../algorithm/CalendarioGenerator.php';

// Assicura accesso a $db globale
global $db;
if (!isset($db) && isset($GLOBALS['db'])) {
    $db = $GLOBALS['db'];
}

$anno_scolastico_corrente = null;
$stmt = $db->prepare("SELECT * FROM anni_scolastici ORDER BY anno DESC");
$stmt->execute();
$anni_scolastici = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $anno_scolastico_id = $_POST['anno_scolastico_id'];
    $strategia = $_POST['strategia'];
    $max_tentativi = $_POST['max_tentativi'];
    $considera_preferenze = isset($_POST['considera_preferenze']);

    $generator = new CalendarioGenerator($db);
    $result = $generator->generaCalendario($anno_scolastico_id, [
        'strategia' => $strategia,
        'max_tentativi' => $max_tentativi,
        'considera_preferenze' => $considera_preferenze
    ]);

    if ($result['success']) {
        $messaggio_successo = "Calendario generato con successo!";
        $statistiche = $result['statistiche'];
    } else {
        $messaggio_errore = $result['error'];
    }
}
?>

<?php 
$page_title = "Genera Calendario";
include '../includes/header.php'; 
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Titolo Pagina -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
            <i class="fas fa-magic mr-3 text-blue-600"></i>Genera Calendario Automatico
        </h1>
        <p class="text-gray-600 mt-2">Configura i parametri per generare automaticamente il calendario lezioni</p>
    </div>

    <!-- Messaggio di Successo -->
    <?php if (isset($messaggio_successo)): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-start">
                <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                <div>
                    <h3 class="font-semibold text-green-900"><?php echo $messaggio_successo; ?></h3>
                    
                    <?php if (isset($statistiche)): ?>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-3 rounded border border-green-200">
                            <p class="text-sm text-gray-600">Lezioni Assegnate</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $statistiche['lezioni_assegnate']; ?></p>
                        </div>
                        <div class="bg-white p-3 rounded border border-green-200">
                            <p class="text-sm text-gray-600">Conflitti Rilevati</p>
                            <p class="text-2xl font-bold text-amber-600"><?php echo $statistiche['conflitti']; ?></p>
                        </div>
                        <div class="bg-white p-3 rounded border border-green-200">
                            <p class="text-sm text-gray-600">Tempo Esecuzione</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo round($statistiche['tempo_esecuzione'], 2); ?>s</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Messaggio di Errore -->
    <?php if (isset($messaggio_errore)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle text-red-600 mt-1 mr-3"></i>
                <div>
                    <h3 class="font-semibold text-red-900">Errore nella Generazione</h3>
                    <p class="text-sm text-red-700 mt-1"><?php echo $messaggio_errore; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Card Configurazione -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 border-b">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-cog mr-2"></i>Configurazione Generazione
            </h2>
        </div>
        
        <div class="p-6">
            <form method="POST" id="formGenera" class="space-y-6">
                
                <!-- Anno Scolastico -->
                <div>
                    <label for="anno_scolastico_id" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Anno Scolastico
                    </label>
                    <select name="anno_scolastico_id" id="anno_scolastico_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">Seleziona anno scolastico</option>
                        <?php foreach ($anni_scolastici as $anno): ?>
                            <option value="<?php echo $anno['id']; ?>" 
                                <?php echo ($anno['attivo'] ? 'selected' : ''); ?>>
                                <?php echo $anno['anno']; ?>
                                <?php echo ($anno['attivo'] ? ' (ATTIVO)' : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Strategia Generazione -->
                <div>
                    <label for="strategia" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-project-diagram text-blue-600 mr-2"></i>Strategia di Generazione
                    </label>
                    <select name="strategia" id="strategia" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="bilanciato">📊 Bilanciato - Distribuisce uniformemente le lezioni</option>
                        <option value="concentrato">📌 Concentrato - Minimi spostamenti tra giorni</option>
                        <option value="distribuito">🔀 Distribuito - Evita giorni pesanti</option>
                    </select>
                </div>

                <!-- Massimo Tentativi -->
                <div>
                    <label for="max_tentativi" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-repeat text-blue-600 mr-2"></i>Massimo Tentativi per Slot
                    </label>
                    <input type="number" name="max_tentativi" id="max_tentativi" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           value="3" min="1" max="10" required>
                    <p class="text-sm text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Numero di tentativi per trovare uno slot disponibile
                    </p>
                </div>

                <!-- Checkbox Preferenze -->
                <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <input type="checkbox" name="considera_preferenze" id="considera_preferenze" 
                           class="h-4 w-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500" checked>
                    <label for="considera_preferenze" class="ml-3 text-sm font-medium text-gray-700">
                        <i class="fas fa-heart text-red-500 mr-2"></i>Considera le preferenze dei docenti
                    </label>
                </div>

                <!-- Avvertenza -->
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-sm text-amber-800 flex items-start">
                        <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5 mr-3 flex-shrink-0"></i>
                        <span>
                            <strong>Attenzione:</strong> La generazione potrebbe richiedere alcuni minuti. 
                            Verrà creato un backup automatico prima di iniziare.
                        </span>
                    </p>
                </div>

                <!-- Pulsante Submit -->
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition duration-200 flex items-center justify-center" id="btnGenera">
                        <i class="fas fa-play mr-2"></i>Genera Calendario
                    </button>
                    <a href="calendario.php" class="flex items-center justify-center px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Indietro
                    </a>
                </div>

                <!-- Progress Container (Hidden by default) -->
                <div id="progressContainer" class="hidden space-y-4 p-4 bg-gray-50 rounded-lg">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-sm font-medium text-gray-700">Generazione in corso...</p>
                            <span id="progressPercent" class="text-sm font-bold text-blue-600">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div id="progressBar" class="bg-gradient-to-r from-blue-600 to-blue-700 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    <div id="progressText" class="text-sm text-gray-600 text-center font-medium"></div>
                    <div id="logContainer" class="bg-gray-900 text-green-400 p-4 rounded-lg max-h-64 overflow-y-auto font-mono text-xs leading-relaxed"></div>
                </div>
            </form>
        </div>
    </div>

    <!-- Log di Generazione (se disponibile) -->
    <?php if (isset($result) && isset($result['log'])): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-600 to-gray-700 border-b">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-list mr-2"></i>Log di Generazione
            </h2>
        </div>
        
        <div class="p-6">
            <div class="bg-gray-900 text-green-400 p-4 rounded-lg max-h-96 overflow-y-auto font-mono text-sm leading-relaxed">
                <?php foreach ($result['log'] as $log_entry): ?>
                    <div class="mb-1"><?php echo htmlspecialchars($log_entry); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

    <script>
    document.getElementById('formGenera').addEventListener('submit', function(e) {
        const btnGenera = document.getElementById('btnGenera');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressPercent = document.getElementById('progressPercent');

        btnGenera.disabled = true;
        btnGenera.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generazione in corso...';
        progressContainer.classList.remove('hidden');

        // Simula progresso
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            
            const roundedProgress = Math.round(progress);
            progressBar.style.width = roundedProgress + '%';
            progressPercent.textContent = roundedProgress + '%';
            progressText.textContent = '⏳ Elaborazione in corso...';
            
            if (progress >= 100) {
                clearInterval(interval);
                progressBar.style.width = '100%';
                progressPercent.textContent = '100%';
                progressText.textContent = '✅ Completato!';
                setTimeout(() => {
                    document.getElementById('formGenera').submit();
                }, 1500);
            }
        }, 800);
    });
    </script>
