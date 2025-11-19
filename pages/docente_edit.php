<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Verifica permessi
if (!in_array($_SESSION['ruolo'], ['preside', 'vice_preside', 'segreteria', 'amministratore'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

// Imposta variabili per header
$current_page = 'docenti';
$page_title = 'Gestione Docente';

$docente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docente = null;

if ($docente_id > 0) {
    $sql = "SELECT * FROM docenti WHERE id = ?";
    $docente = Database::queryOne($sql, [$docente_id]);
    
    if (!$docente) {
        $_SESSION['error'] = "Docente non trovato";
        header('Location: docenti.php');
        exit();
    }
}

// Get sedi
$sql_sedi = "SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome";
$sedi = Database::queryAll($sql_sedi, []);

// Get materie
$sql_materie = "SELECT id, nome FROM materie WHERE attiva = 1 ORDER BY nome";
$materie = Database::queryAll($sql_materie, []);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b">
                <h1 class="text-2xl font-bold text-gray-900">
                    <?= $docente_id ? 'Modifica Docente' : 'Nuovo Docente' ?>
                </h1>
                <p class="text-sm text-gray-600 mt-1">Gestisci i dati del docente, le materie insegnate e i vincoli orari</p>
            </div>
            
            <form id="docenteForm" class="p-6 space-y-6">
                <input type="hidden" name="id" value="<?= $docente_id ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Colonna Sinistra -->
                    <div class="space-y-4">
                        <div>
                            <label for="cognome" class="block text-sm font-medium text-gray-700 mb-1">Cognome *</label>
                            <input type="text" id="cognome" name="cognome" value="<?= htmlspecialchars($docente['cognome'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                        
                        <div>
                            <label for="nome" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($docente['nome'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                        
                        <div>
                            <label for="codice_fiscale" class="block text-sm font-medium text-gray-700 mb-1">Codice Fiscale</label>
                            <input type="text" id="codice_fiscale" name="codice_fiscale" 
                                   value="<?= htmlspecialchars($docente['codice_fiscale'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                   maxlength="16">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($docente['email'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <!-- Colonna Destra -->
                    <div class="space-y-4">
                        <div>
                            <label for="sede_principale_id" class="block text-sm font-medium text-gray-700 mb-1">Sede Principale *</label>
                            <select id="sede_principale_id" name="sede_principale_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                                <option value="">Seleziona sede</option>
                                <?php foreach ($sedi as $sede): ?>
                                    <option value="<?= $sede['id'] ?>" 
                                            <?= ($docente['sede_principale_id'] ?? '') == $sede['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sede['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="telefono" class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                            <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($docente['telefono'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div>
                            <label for="cellulare" class="block text-sm font-medium text-gray-700 mb-1">Cellulare</label>
                            <input type="tel" id="cellulare" name="cellulare" value="<?= htmlspecialchars($docente['cellulare'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div>
                            <label for="stato" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                            <select id="stato" name="stato" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="attivo" <?= ($docente['stato'] ?? 'attivo') == 'attivo' ? 'selected' : '' ?>>Attivo</option>
                                <option value="inattivo" <?= ($docente['stato'] ?? '') == 'inattivo' ? 'selected' : '' ?>>Inattivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Seconda Riga -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="ore_settimanali_contratto" class="block text-sm font-medium text-gray-700 mb-1">Ore Settimanali Contratto</label>
                        <input type="number" id="ore_settimanali_contratto" name="ore_settimanali_contratto" 
                               value="<?= $docente['ore_settimanali_contratto'] ?? 18 ?>" min="1" max="40"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <div>
                        <label for="max_ore_giorno" class="block text-sm font-medium text-gray-700 mb-1">Max Ore/Giorno</label>
                        <input type="number" id="max_ore_giorno" name="max_ore_giorno" 
                               value="<?= $docente['max_ore_giorno'] ?? 6 ?>" min="1" max="10"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <div>
                        <label for="max_ore_settimana" class="block text-sm font-medium text-gray-700 mb-1">Max Ore/Settimana</label>
                        <input type="number" id="max_ore_settimana" name="max_ore_settimana" 
                               value="<?= $docente['max_ore_settimana'] ?? 18 ?>" min="1" max="40"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                
                <!-- Terza Riga -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="specializzazione" class="block text-sm font-medium text-gray-700 mb-1">Specializzazione</label>
                        <input type="text" id="specializzazione" name="specializzazione" 
                               value="<?= htmlspecialchars($docente['specializzazione'] ?? '') ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    
                    <div class="flex items-center space-x-4 pt-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="permette_buchi_orario" name="permette_buchi_orario" value="1"
                                   <?= ($docente['permette_buchi_orario'] ?? 1) ? 'checked' : '' ?>
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="permette_buchi_orario" class="ml-2 block text-sm text-gray-700">
                                Permette buchi orari
                            </label>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                    <textarea id="note" name="note" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($docente['note'] ?? '') ?></textarea>
                </div>
            </form>
            
            <!-- Sezione Materie (visibile solo dopo salvataggio) -->
            <?php if ($docente_id): ?>
            <div class="border-t pt-6 px-6 py-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium text-gray-900">📚 Materie Insegnate</h2>
                    <button type="button" onclick="openMaterie()" 
                            class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-1"></i>Aggiungi Materia
                    </button>
                </div>
                <div id="materieList" class="space-y-2"></div>
            </div>
            
            <!-- Sezione Vincoli (visibile solo dopo salvataggio) -->
            <div class="border-t pt-6 px-6 py-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium text-gray-900">⏰ Vincoli Orari</h2>
                    <button type="button" onclick="openVincoli()" 
                            class="px-4 py-2 bg-orange-600 text-white font-medium rounded-md hover:bg-orange-700 transition">
                        <i class="fas fa-plus mr-1"></i>Aggiungi Vincolo
                    </button>
                </div>
                <div id="vincoliList" class="space-y-2"></div>
            </div>
            <?php endif; ?>
            
            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-3">
                <a href="docenti.php" 
                   class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                    ← Torna ai Docenti
                </a>
                <button type="button" onclick="saveDocentePage()" 
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    <?= $docente_id ? '💾 Aggiorna' : '✨ Crea' ?>
                </button>
            </div>
        </div>
    </main>
</div>

<!-- ===================== MODALE MATERIE ===================== -->
<div id="materieModal" style="display: none;" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-900">Gestione Materie</h2>
            <button onclick="closeModal('materieModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-6">
            <!-- Form Aggiungi -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-medium text-gray-900 mb-4">Aggiungi Materia</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Materia *</label>
                        <select id="materiaSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="">Seleziona materia</option>
                            <?php foreach ($materie as $mat): ?>
                                <option value="<?= $mat['id'] ?>">
                                    <?= htmlspecialchars($mat['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferenza</label>
                        <select id="preferenzaSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="1">Alta</option>
                            <option value="2" selected>Media</option>
                            <option value="3">Bassa</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="addMateria()" 
                                class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700">
                            Aggiungi
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Lista Materie -->
            <div>
                <h3 class="font-medium text-gray-900 mb-4">Materie Assegnate</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Materia</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Preferenza</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="materieTableBody" class="divide-y divide-gray-200">
                            <!-- Caricato via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== MODALE VINCOLI ===================== -->
<div id="vincoliModal" style="display: none;" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-900">Gestione Vincoli Orari</h2>
            <button onclick="closeModal('vincoliModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-6">
            <!-- Form Aggiungi -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h3 class="font-medium text-gray-900 mb-4">Aggiungi Vincolo</h3>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                        <select id="tipoVincolo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500">
                            <option value="">Seleziona tipo</option>
                            <option value="indisponibilita">Indisponibilità</option>
                            <option value="preferenza">Preferenza</option>
                            <option value="doppia_sede">Doppia Sede</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giorno *</label>
                        <select id="giornoVincolo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500">
                            <option value="">Seleziona giorno</option>
                            <option value="1">Lunedì</option>
                            <option value="2">Martedì</option>
                            <option value="3">Mercoledì</option>
                            <option value="4">Giovedì</option>
                            <option value="5">Venerdì</option>
                            <option value="6">Sabato</option>
                            <option value="7">Domenica</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Inizio</label>
                        <input type="time" id="oraInizioVincolo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fine</label>
                        <input type="time" id="oraFineVincolo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="addVincolo()" 
                                class="w-full px-4 py-2 bg-orange-600 text-white font-medium rounded-md hover:bg-orange-700">
                            Aggiungi
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sede</label>
                        <select id="sedeVincolo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500">
                            <option value="">Nessuna sede specifica</option>
                            <?php foreach ($sedi as $sede): ?>
                                <option value="<?= $sede['id'] ?>"><?= htmlspecialchars($sede['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                        <input type="text" id="motivoVincolo" placeholder="Es: Commissione, Riunione..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
            </div>
            
            <!-- Lista Vincoli -->
            <div>
                <h3 class="font-medium text-gray-900 mb-4">Vincoli Assegnati</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Giorno</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Orari</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Motivo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="vincoliTableBody" class="divide-y divide-gray-200">
                            <!-- Caricato via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/docenti_form.js"></script>

<script>
// Funzione per salvare da questa pagina (non modale)
function saveDocentePage() {
    const form = document.getElementById('docenteForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    const action = data.id ? 'update' : 'create';
    const url = `../api/docenti_api.php?action=${action}`;
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            currentDocenteId = result.id || data.id;
            alert('✅ Docente salvato con successo!');
            
            // Se è nuovo, ricarica per mostrare le sezioni materie/vincoli
            if (!data.id) {
                window.location.href = `docente_edit.php?id=${result.id}`;
            } else {
                // Se è aggiornamento, ricarica la pagina
                location.reload();
            }
        } else {
            alert('❌ Errore: ' + (result.message || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore nel salvataggio');
    });
}

// Al caricamento, carica materie e vincoli se docente_id esiste
document.addEventListener('DOMContentLoaded', function() {
    const docenteId = document.querySelector('input[name="id"]').value;
    if (docenteId > 0) {
        currentDocenteId = parseInt(docenteId);
        loadMaterie();
        loadVincoli();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
