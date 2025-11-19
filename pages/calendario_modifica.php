<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Solo admin e preside possono modificare
checkRole(['amministratore', 'preside', 'vice_preside']);

$current_page = 'calendario_modifica';
$page_title = 'Modifica Calendario - Drag & Drop';

// Configurazione
$settimana = $_GET['settimana'] ?? date('o-W');
$sede_id = $_GET['sede_id'] ?? '';

if (!preg_match('/^\d{4}-\d{1,2}$/', $settimana)) {
    $settimana = date('o-W');
}
[$anno_settimana, $numero_settimana] = array_map('intval', explode('-', $settimana));
if ($numero_settimana < 1 || $numero_settimana > 53) {
    $numero_settimana = (int)date('W');
}

$data_inizio = new DateTime();
$data_inizio->setISODate($anno_settimana, $numero_settimana);

// Carica dati
try {
    $sedi = $db->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome")->fetchAll();
    $slot_orari = $db->query("SELECT id, numero_slot, ora_inizio, ora_fine FROM orari_slot WHERE attivo = 1 ORDER BY numero_slot")->fetchAll();
    $classi = $db->query("SELECT id, nome FROM classi WHERE stato = 'attiva' ORDER BY nome")->fetchAll();
    
    if ($sede_id) {
        $stmt = $db->prepare("SELECT id, nome FROM aule WHERE sede_id = ? AND attiva = 1 ORDER BY nome");
        $stmt->execute([$sede_id]);
        $aule = $stmt->fetchAll();
    } else {
        $aule = [];
    }
} catch (Exception $e) {
    error_log("Errore caricamento dati modifica: " . $e->getMessage());
    $sedi = $slot_orari = $classi = $aule = [];
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Modifica Calendario</h1>
                <p class="text-gray-600 mt-1">Drag & Drop per spostare lezioni tra gli slot</p>
            </div>
            
            <div class="flex items-center space-x-4 mt-4 lg:mt-0">
                <!-- Navigazione Settimana -->
                <div class="flex items-center space-x-2">
                    <?php
                    $prev_week = (clone $data_inizio)->modify('-1 week')->format('Y-W');
                    $next_week = (clone $data_inizio)->modify('+1 week')->format('Y-W');
                    ?>
                    <a href="?settimana=<?= $prev_week ?>&sede_id=<?= $sede_id ?>" 
                       class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <span class="text-lg font-semibold px-4">
                        Settimana <?= $data_inizio->format('W/Y') ?>
                    </span>
                    
                    <a href="?settimana=<?= $next_week ?>&sede_id=<?= $sede_id ?>" 
                       class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg transition-colors">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <!-- Filtro Sede -->
                <select id="sedeFilter" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tutte le sedi</option>
                    <?php foreach ($sedi as $sede): ?>
                        <option value="<?= $sede['id'] ?>" <?= $sede_id == $sede['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sede['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Toolbar Azioni -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <button id="saveChanges" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-save mr-2"></i>Salva Modifiche
                </button>
                <button id="undoBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center" disabled>
                    <i class="fas fa-undo mr-2"></i>Annulla
                </button>
                <button id="redoBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center" disabled>
                    <i class="fas fa-redo mr-2"></i>Ripeti
                </button>
            </div>
            
            <div class="flex items-center space-x-3 text-sm">
                <span class="text-gray-600">Modifiche non salvate: <span id="unsavedCount">0</span></span>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                    <span class="text-gray-600">Disponibile</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                    <span class="text-gray-600">Conflitto</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Griglia Drag & Drop -->
    <div id="calendarioGrid" class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="grid grid-cols-8 border-b bg-gray-50">
            <div class="p-4 font-semibold text-gray-700">Ora</div>
            <?php for ($i = 0; $i < 7; $i++): 
                $giorno = (clone $data_inizio)->modify("+$i days");
                $is_oggi = $giorno->format('Y-m-d') === date('Y-m-d');
            ?>
                <div class="p-4 text-center font-semibold <?= $is_oggi ? 'text-blue-600 bg-blue-50' : 'text-gray-700' ?>">
                    <div><?= $giorno->format('D') ?></div>
                    <div class="text-lg"><?= $giorno->format('d') ?></div>
                </div>
            <?php endfor; ?>
        </div>

        <?php foreach ($slot_orari as $slot): ?>
            <div class="grid grid-cols-8 border-b hover:bg-gray-50 transition-colors" data-slot-id="<?= $slot['id'] ?>">
                <div class="p-4 border-r bg-gray-50 text-sm text-gray-600 font-medium">
                    <?= substr($slot['ora_inizio'], 0, 5) ?> - <?= substr($slot['ora_fine'], 0, 5) ?>
                </div>
                
                <?php for ($i = 0; $i < 7; $i++): 
                    $giorno = (clone $data_inizio)->modify("+$i days");
                    $data_giorno = $giorno->format('Y-m-d');
                ?>
                    <div class="p-2 min-h-24 border-r droppable-slot"
                         data-date="<?= $data_giorno ?>"
                         data-slot="<?= $slot['id'] ?>"
                         data-sede="<?= $sede_id ?>">
                         
                        <div class="lezione-container h-full" id="slot-<?= $data_giorno ?>-<?= $slot['id'] ?>">
                            <!-- Lezioni verranno caricate via AJAX -->
                        </div>
                        
                        <button class="add-lezione-btn w-full h-full min-h-20 flex items-center justify-center text-gray-300 hover:text-blue-500 transition-colors border-2 border-dashed border-gray-300 hover:border-blue-400 rounded-lg"
                                data-date="<?= $data_giorno ?>"
                                data-slot="<?= $slot['id'] ?>">
                            <i class="fas fa-plus text-xl"></i>
                        </button>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Log Modifiche -->
    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Log Modifiche Recenti</h3>
        <div id="modificheLog" class="space-y-2 max-h-48 overflow-y-auto">
            <!-- Log verrà popolato via JavaScript -->
        </div>
    </div>
</div>

<!-- Modale Nuova Lezione -->
<div id="lezioneModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-90vh overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-semibold" id="modalTitle">Nuova Lezione</h3>
            <button type="button" id="closeModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="lezioneForm">
                <input type="hidden" id="lezione_id" name="lezione_id">
                <input type="hidden" id="modal_date" name="data_lezione">
                <input type="hidden" id="modal_slot" name="slot_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Classe *</label>
                            <select id="classe_id" name="classe_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleziona classe</option>
                                <?php foreach ($classi as $classe): ?>
                                    <option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Materia *</label>
                            <select id="materia_id" name="materia_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleziona materia</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Docente *</label>
                            <select id="docente_id" name="docente_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleziona docente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Aula</label>
                            <select id="aula_id" name="aula_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Nessuna aula</option>
                                <?php foreach ($aule as $aula): ?>
                                    <option value="<?= $aula['id'] ?>"><?= htmlspecialchars($aula['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                            <select id="stato" name="stato" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="pianificata">Pianificata</option>
                                <option value="confermata">Confermata</option>
                                <option value="svolta">Svolta</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modalità</label>
                            <select id="modalita" name="modalita" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="presenza">In presenza</option>
                                <option value="online">Online</option>
                                <option value="mista">Mista</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Argomento</label>
                    <input type="text" id="argomento" name="argomento" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Argomento della lezione...">
                </div>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                    <textarea id="note" name="note" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Note aggiuntive..."></textarea>
                </div>
                
                <!-- Conflitti Warning -->
                <div id="conflittiAlert" class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg hidden">
                    <h4 class="font-semibold text-yellow-800 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Conflitti rilevati
                    </h4>
                    <div id="conflittiList" class="text-sm text-yellow-700"></div>
                </div>
            </form>
        </div>
        <div class="flex justify-end space-x-3 p-6 border-t bg-gray-50">
            <button type="button" id="checkConflitti" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-search mr-2"></i>Verifica Conflitti
            </button>
            <button type="button" id="saveLezione" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Salva Lezione
            </button>
            <button type="button" id="cancelLezione" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                Annulla
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendario.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/calendario_modifica.js"></script>

<?php include '../includes/footer.php'; ?>