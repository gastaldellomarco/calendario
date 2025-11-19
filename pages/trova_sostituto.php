<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../algorithm/SostitutoFinder.php';

$current_page = 'trova_sostituto';
$page_title = 'Trova Sostituto';

$lezione_id = intval($_GET['lezione_id'] ?? 0);
$docente_id = intval($_GET['docente_id'] ?? 0);
$data = $_GET['data'] ?? date('Y-m-d');

// Carica dati lezione
if ($lezione_id) {
    $lezione = $db->query("
        SELECT cl.*, c.nome as classe_nome, m.nome as materia_nome, 
               d.cognome as docente_cognome, d.nome as docente_nome,
               s.nome as sede_nome, a.nome as aula_nome,
               os.ora_inizio, os.ora_fine
        FROM calendario_lezioni cl
        JOIN classi c ON cl.classe_id = c.id
        JOIN materie m ON cl.materia_id = m.id
        JOIN docenti d ON cl.docente_id = d.id
        JOIN sedi s ON cl.sede_id = s.id
        LEFT JOIN aule a ON cl.aula_id = a.id
        JOIN orari_slot os ON cl.slot_id = os.id
        WHERE cl.id = ?
    ", [$lezione_id])->fetch();
} else {
    $lezione = null;
}

// Trova sostituti
$sostituti = [];
if ($lezione) {
    $finder = new SostitutoFinder($db);
    $sostituti = $finder->trovaSostituti($lezione_id);
}

// Gestione assegnazione sostituto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'assegna_sostituto') {
    $sostituto_id = intval($_POST['sostituto_id']);
    
    // Crea record sostituzione
    $db->query("
        INSERT INTO sostituzioni (lezione_id, docente_originale_id, docente_sostituto_id, motivo, note, confermata)
        VALUES (?, ?, ?, 'sostituzione', 'Assegnazione automatica', 0)
    ", [$lezione_id, $lezione['docente_id'], $sostituto_id]);
    
    // Aggiorna calendario
    $db->query("
        UPDATE calendario_lezioni SET docente_id = ? WHERE id = ?
    ", [$sostituto_id, $lezione_id]);
    
    $_SESSION['success_message'] = "Sostituto assegnato con successo!";
    header("Location: sostituzioni.php");
    exit;
}
?>

<?php include '../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Trova Sostituto
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Assegna un docente sostituto per la lezione selezionata
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="sostituzioni.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i>Torna alle Sostituzioni
            </a>
        </div>
    </div>

    <?php if (!$lezione): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Nessuna lezione selezionata</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Seleziona una lezione dalla pagina delle sostituzioni per trovare un sostituto.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Dettagli Lezione -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">📚 Dettagli Lezione</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data e Ora</label>
                    <p class="mt-1 text-sm text-gray-900 font-medium">
                        <?php echo date('d/m/Y H:i', strtotime($lezione['data_lezione'] . ' ' . $lezione['ora_inizio'])); ?>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Classe e Materia</label>
                    <p class="mt-1 text-sm text-gray-900 font-medium">
                        <?php echo htmlspecialchars($lezione['classe_nome'] . ' - ' . $lezione['materia_nome']); ?>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Docente Assente</label>
                    <p class="mt-1 text-sm text-gray-900 font-medium">
                        <?php echo htmlspecialchars($lezione['docente_cognome'] . ' ' . $lezione['docente_nome']); ?>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sede e Aula</label>
                    <p class="mt-1 text-sm text-gray-900 font-medium">
                        <?php echo htmlspecialchars($lezione['sede_nome'] . ' - ' . ($lezione['aula_nome'] ?? 'Aula da definire')); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Sostituti Disponibili -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">
                    👥 Sostituti Disponibili 
                    <span class="text-sm font-normal text-gray-500">(<?php echo count($sostituti); ?> trovati)</span>
                </h2>
            </div>

            <?php if (empty($sostituti)): ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-user-slash text-3xl mb-2 text-gray-400"></i>
                    <p>Nessun sostituto disponibile per questa lezione</p>
                    <p class="text-sm mt-2">Prova a modificare i criteri di ricerca o considera opzioni alternative</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($sostituti as $index => $sostituto): ?>
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <!-- Avatar e Info Base -->
                                    <div class="flex-shrink-0">
                                        <img class="h-12 w-12 rounded-full" 
                                             src="https://ui-avatars.com/api/?name=<?php echo urlencode($sostituto['cognome'] . ' ' . $sostituto['nome']); ?>&background=3b82f6&color=fff" 
                                             alt="">
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-medium text-gray-900">
                                            <?php echo htmlspecialchars($sostituto['cognome'] . ' ' . $sostituto['nome']); ?>
                                            <?php if ($index == 0): ?>
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-crown mr-1"></i>Migliore
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            Sede: <?php echo htmlspecialchars($sostituto['sede_nome']); ?> 
                                            • Ore settimanali: <?php echo $sostituto['ore_contratto']; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Score e Azioni -->
                                <div class="flex items-center space-x-4">
                                    <!-- Score -->
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">
                                            <?php echo $sostituto['score']; ?>%
                                        </div>
                                        <div class="text-xs text-gray-500">Idoneità</div>
                                    </div>

                                    <!-- Dettagli Score -->
                                    <div class="text-sm text-gray-600">
                                        <div class="flex items-center <?php echo $sostituto['puo_insegnare'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <i class="fas <?php echo $sostituto['puo_insegnare'] ? 'fa-check' : 'fa-times'; ?> mr-1"></i>
                                            Materia
                                        </div>
                                        <div class="flex items-center <?php echo $sostituto['disponibile'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <i class="fas <?php echo $sostituto['disponibile'] ? 'fa-check' : 'fa-times'; ?> mr-1"></i>
                                            Disponibile
                                        </div>
                                    </div>

                                    <!-- Azione Assegna -->
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="action" value="assegna_sostituto">
                                        <input type="hidden" name="sostituto_id" value="<?php echo $sostituto['id']; ?>">
                                        <button type="submit" 
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                onclick="return confirm('Assegnare <?php echo $sostituto['cognome']; ?> come sostituto?')">
                                            <i class="fas fa-user-check mr-2"></i>Assegna
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Dettagli Avanzati -->
                            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                                <div>
                                    <span class="font-medium">Esperienza Classe:</span>
                                    <span class="<?php echo $sostituto['esperienza_classe'] ? 'text-green-600' : 'text-gray-500'; ?>">
                                        <?php echo $sostituto['esperienza_classe'] ? 'SI' : 'NO'; ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium">Ore Oggi:</span>
                                    <span class="<?php echo $sostituto['ore_oggi'] < 4 ? 'text-green-600' : 'text-orange-600'; ?>">
                                        <?php echo $sostituto['ore_oggi']; ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium">Ore Settimana:</span>
                                    <span class="<?php echo $sostituto['ore_settimana'] < $sostituto['ore_contratto'] ? 'text-green-600' : 'text-orange-600'; ?>">
                                        <?php echo $sostituto['ore_settimana']; ?>/<?php echo $sostituto['ore_contratto']; ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium">Preferenza:</span>
                                    <span class="<?php echo $sostituto['preferenza_materia'] == 1 ? 'text-green-600' : ($sostituto['preferenza_materia'] == 2 ? 'text-yellow-600' : 'text-gray-500'); ?>">
                                        <?php echo $sostituto['preferenza_materia'] == 1 ? 'Alta' : ($sostituto['preferenza_materia'] == 2 ? 'Media' : 'Bassa'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Opzioni Alternative -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">🔄 Opzioni Alternative</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Sposta Lezione -->
                <div class="border border-gray-200 rounded-lg p-4 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-md bg-blue-100 text-blue-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sposta Lezione</h3>
                    <p class="mt-1 text-sm text-gray-500">Riprogramma la lezione in un altro slot orario</p>
                    <button class="mt-3 inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-calendar-alt mr-1"></i>Sposta
                    </button>
                </div>

                <!-- Annulla Lezione -->
                <div class="border border-gray-200 rounded-lg p-4 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-md bg-yellow-100 text-yellow-600">
                        <i class="fas fa-ban text-xl"></i>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Annulla Lezione</h3>
                    <p class="mt-1 text-sm text-gray-500">Cancella la lezione con recupero successivo</p>
                    <button class="mt-3 inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-times mr-1"></i>Annulla
                    </button>
                </div>

                <!-- Lezione Online -->
                <div class="border border-gray-200 rounded-lg p-4 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-md bg-green-100 text-green-600">
                        <i class="fas fa-laptop text-xl"></i>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Lezione Online</h3>
                    <p class="mt-1 text-sm text-gray-500">Converti in lezione a distanza se possibile</p>
                    <button class="mt-3 inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-video mr-1"></i>Online
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>