<?php
// pages/materie.php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$page_title = "Gestione Materie";
$current_page = "materie";

// Connessione al database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupero filtri
$filtro_percorso = $_GET['percorso'] ?? '';
$filtro_anno_corso = $_GET['anno_corso'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_attiva = $_GET['attiva'] ?? '';

// Recupero opzioni per filtri
try {
    // Percorsi formativi
    $stmt_percorsi = $pdo->query("SELECT id, nome FROM percorsi_formativi WHERE attivo = 1 ORDER BY nome");
    $percorsi = $stmt_percorsi->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Errore nel recupero opzioni filtri: " . $e->getMessage());
    $percorsi = [];
}

// Ottieni settimane di lezione per l'anno scolastico attivo (fallback 33)
try {
    $stmt_weeks = $pdo->prepare("SELECT settimane_lezione FROM anni_scolastici WHERE attivo = 1 LIMIT 1");
    $stmt_weeks->execute();
    $settimane_lezione = (int)($stmt_weeks->fetchColumn() ?: 33);
} catch (Exception $e) {
    $settimane_lezione = 33;
}

// Query materie con filtri
try {
    $sql = "SELECT m.*, p.nome as percorso_nome
            FROM materie m
            LEFT JOIN percorsi_formativi p ON m.percorso_formativo_id = p.id
            WHERE 1=1";
    
    $params = [];
    
    if ($filtro_percorso) {
        $sql .= " AND m.percorso_formativo_id = ?";
        $params[] = $filtro_percorso;
    }
    
    if ($filtro_anno_corso) {
        $sql .= " AND m.anno_corso = ?";
        $params[] = $filtro_anno_corso;
    }
    
    if ($filtro_tipo) {
        $sql .= " AND m.tipo = ?";
        $params[] = $filtro_tipo;
    }
    
    if ($filtro_attiva !== '') {
        $sql .= " AND m.attiva = ?";
        $params[] = $filtro_attiva;
    }
    
    $sql .= " ORDER BY p.nome, m.anno_corso, m.nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materie = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Errore nel recupero materie: " . $e->getMessage());
    $materie = [];
}
?>

<?php include '../includes/header.php'; ?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Gestione Materie</h1>
                    <p class="text-gray-600 mt-1">Gestisci le materie dei percorsi formativi</p>
                </div>
                <div>
                    <button onclick="apriModalMateria()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        <i class="fas fa-plus mr-2"></i>Nuova Materia
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filtri -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Filtri</h2>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Percorso -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Percorso</label>
                        <select name="percorso" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Tutti i percorsi</option>
                            <?php foreach ($percorsi as $percorso): ?>
                                <option value="<?php echo $percorso['id']; ?>" <?php echo $filtro_percorso == $percorso['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($percorso['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Anno Corso -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Anno Corso</label>
                        <select name="anno_corso" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Tutti gli anni</option>
                            <option value="1" <?php echo $filtro_anno_corso == '1' ? 'selected' : ''; ?>>1° Anno</option>
                            <option value="2" <?php echo $filtro_anno_corso == '2' ? 'selected' : ''; ?>>2° Anno</option>
                            <option value="3" <?php echo $filtro_anno_corso == '3' ? 'selected' : ''; ?>>3° Anno</option>
                            <option value="4" <?php echo $filtro_anno_corso == '4' ? 'selected' : ''; ?>>4° Anno</option>
                        </select>
                    </div>
                    
                    <!-- Tipo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                        <select name="tipo" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Tutti i tipi</option>
                            <option value="culturale" <?php echo $filtro_tipo == 'culturale' ? 'selected' : ''; ?>>Culturale</option>
                            <option value="professionale" <?php echo $filtro_tipo == 'professionale' ? 'selected' : ''; ?>>Professionale</option>
                            <option value="laboratoriale" <?php echo $filtro_tipo == 'laboratoriale' ? 'selected' : ''; ?>>Laboratoriale</option>
                            <option value="stage" <?php echo $filtro_tipo == 'stage' ? 'selected' : ''; ?>>Stage</option>
                            <option value="sostegno" <?php echo $filtro_tipo == 'sostegno' ? 'selected' : ''; ?>>Sostegno</option>
                        </select>
                    </div>
                    
                    <!-- Stato -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                        <select name="attiva" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Tutti</option>
                            <option value="1" <?php echo $filtro_attiva === '1' ? 'selected' : ''; ?>>Attive</option>
                            <option value="0" <?php echo $filtro_attiva === '0' ? 'selected' : ''; ?>>Inattive</option>
                        </select>
                    </div>
                    
                    <!-- Pulsanti -->
                    <div class="md:col-span-2 lg:col-span-4 flex justify-between">
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-filter mr-1"></i>Applica Filtri
                            </button>
                            <a href="materie.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-times mr-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista Materie -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">
                    Materie (<?php echo count($materie); ?>)
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Materia</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percorso</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Anno</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore Annuali</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratorio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docenti</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($materie)): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                    Nessuna materia trovata
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materie as $materia): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($materia['nome']); ?></div>
                                    <?php if ($materia['descrizione']): ?>
                                        <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($materia['descrizione']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($materia['codice']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($materia['percorso_nome'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $materia['anno_corso']; ?>° anno
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $colori_tipi = [
                                        'culturale' => 'bg-blue-100 text-blue-800',
                                        'professionale' => 'bg-green-100 text-green-800',
                                        'laboratoriale' => 'bg-purple-100 text-purple-800',
                                        'stage' => 'bg-yellow-100 text-yellow-800',
                                        'sostegno' => 'bg-red-100 text-red-800'
                                    ];
                                    $colore = $colori_tipi[$materia['tipo']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $colore; ?>">
                                        <?php echo ucfirst($materia['tipo']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $materia['ore_annuali']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $materia['richiede_laboratorio'] ? 'Sì' : 'No'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $materia['attiva'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $materia['attiva'] ? 'Attiva' : 'Inattiva'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="modificaMateria(<?php echo $materia['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900" title="Modifica">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="docente_materie.php?materia_id=<?php echo $materia['id']; ?>" 
                                           class="text-green-600 hover:text-green-900" title="Assegna Docenti">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <button onclick="eliminaMateria(<?php echo $materia['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900" title="Elimina">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Materia -->
<div id="modalMateria" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitoloMateria">Nuova Materia</h3>
                <button onclick="chiudiModalMateria()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="formMateria" class="space-y-4 mt-4">
                <input type="hidden" id="materia_id" name="id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="nome_materia" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                        <input type="text" id="nome_materia" name="nome" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    
                    <div>
                        <label for="codice_materia" class="block text-sm font-medium text-gray-700 mb-1">Codice *</label>
                        <input type="text" id="codice_materia" name="codice" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="percorso_formativo_id" class="block text-sm font-medium text-gray-700 mb-1">Percorso *</label>
                        <select id="percorso_formativo_id" name="percorso_formativo_id" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Seleziona percorso</option>
                            <?php foreach ($percorsi as $percorso): ?>
                                <option value="<?php echo $percorso['id']; ?>"><?php echo htmlspecialchars($percorso['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="anno_corso_materia" class="block text-sm font-medium text-gray-700 mb-1">Anno Corso *</label>
                        <select id="anno_corso_materia" name="anno_corso" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Seleziona anno</option>
                            <option value="1">1° Anno</option>
                            <option value="2">2° Anno</option>
                            <option value="3">3° Anno</option>
                            <option value="4">4° Anno</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label for="tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                        <select id="tipo" name="tipo" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Seleziona tipo</option>
                            <option value="culturale">Culturale</option>
                            <option value="professionale">Professionale</option>
                            <option value="laboratoriale">Laboratoriale</option>
                            <option value="stage">Stage</option>
                            <option value="sostegno">Sostegno</option>
                        </select>
                    </div>
                    <div>
                        <label for="distribuzione" class="block text-sm font-medium text-gray-700 mb-1">Distribuzione</label>
                        <select id="distribuzione" name="distribuzione" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="settimanale">Tuttele settimane</option>
                            <option value="sparsa">Sparsa (non tutte le settimane)</option>
                            <option value="casuale">Sparsa casuale</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="ore_settimanali" class="block text-sm font-medium text-gray-700 mb-1">Ore Sett.</label>
                        <input type="number" id="ore_settimanali" name="ore_settimanali" min="1" max="20" value="2"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    
                    <div>
                        <label for="peso" class="block text-sm font-medium text-gray-700 mb-1">Peso</label>
                        <input type="number" id="peso" name="peso" min="1" max="10" value="1"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="ore_annuali_display" class="block text-sm font-medium text-gray-700 mb-1">Ore Annuali</label>
                        <div class="flex space-x-2 items-center">
                            <input type="text" id="ore_annuali_display" readonly value="66"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-gray-100">
                            <div class="flex items-center space-x-2">
                                <label class="text-sm text-gray-700">Calcolo automatico</label>
                                <input type="checkbox" id="ore_annuali_auto" checked class="rounded border-gray-300">
                            </div>
                        </div>
                        <input type="number" id="ore_annuali_input" name="ore_annuali" min="1" placeholder="Inserisci ore annuali"
                               class="mt-2 w-full border border-gray-300 rounded-md px-3 py-2 text-sm hidden">
                    </div>
                </div>
                
                <div>
                    <label for="descrizione_materia" class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                    <textarea id="descrizione_materia" name="descrizione" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"></textarea>
                </div>
                
                <div class="flex space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="richiede_laboratorio" name="richiede_laboratorio" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Richiede laboratorio</span>
                    </label>
                    
                    <label class="flex items-center">
                        <input type="checkbox" id="attiva_materia" name="attiva" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                        <span class="ml-2 text-sm text-gray-700">Materia attiva</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="chiudiModalMateria()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Annulla
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <div>
                    </button>
                </div>
            </form>
        </div>
    </div>
                
                    <div class="mt-2 md:col-span-4">
<script>
const settimaneLezione = <?php echo json_encode((int)$settimane_lezione); ?>;
const tipoToPeso = {
    'culturale': 1,
    'professionale': 2,
    'laboratoriale': 3,
    'stage': 2,
    'sostegno': 1
};
let pesoEdited = false;
let oreSettEdited = false;
let modalMateria = document.getElementById('modalMateria');
const oreAnnualiAutoCheckbox = document.getElementById('ore_annuali_auto');
const oreAnnualiInput = document.getElementById('ore_annuali_input');

function apriModalMateria() {
    document.getElementById('modalTitoloMateria').textContent = 'Nuova Materia';
    document.getElementById('formMateria').reset();
    document.getElementById('materia_id').value = '';
    document.getElementById('attiva_materia').checked = true;
    // Imposta valori di default in base alle impostazioni
    const computed = Math.round(settimaneLezione * 2);
    document.getElementById('ore_annuali_display').value = computed;
    oreAnnualiInput.value = computed;
    oreAnnualiInput.classList.add('hidden');
    oreAnnualiAutoCheckbox.checked = true;
    document.getElementById('peso').value = tipoToPeso['culturale'] || 1;
    modalMateria.classList.remove('hidden');
}

function chiudiModalMateria() {
    modalMateria.classList.add('hidden');
}

function modificaMateria(id) {
    fetch(`../api/materie_api.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(materia => {
            document.getElementById('modalTitoloMateria').textContent = 'Modifica Materia';
            document.getElementById('materia_id').value = materia.id;
            document.getElementById('nome_materia').value = materia.nome;
            document.getElementById('codice_materia').value = materia.codice;
            document.getElementById('percorso_formativo_id').value = materia.percorso_formativo_id;
            document.getElementById('anno_corso_materia').value = materia.anno_corso;
            document.getElementById('tipo').value = materia.tipo;
            document.getElementById('ore_settimanali').value = materia.ore_settimanali;
            document.getElementById('peso').value = materia.peso;
            const computedVal = Math.round(settimaneLezione * materia.ore_settimanali);
            document.getElementById('ore_annuali_display').value = materia.ore_annuali || computedVal;
            // Detect if ore_annuali appears to be a manual override
            if (typeof materia.ore_annuali !== 'undefined' && parseInt(materia.ore_annuali) !== computedVal) {
                oreAnnualiAutoCheckbox.checked = false;
                oreAnnualiInput.value = materia.ore_annuali;
                oreAnnualiInput.classList.remove('hidden');
            } else {
                oreAnnualiAutoCheckbox.checked = true;
                oreAnnualiInput.value = computedVal;
                oreAnnualiInput.classList.add('hidden');
            }
            document.getElementById('descrizione_materia').value = materia.descrizione || '';
            document.getElementById('richiede_laboratorio').checked = materia.richiede_laboratorio;
            document.getElementById('attiva_materia').checked = materia.attiva;
            
            modalMateria.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore nel caricamento materia');
        });
}

function eliminaMateria(id) {
    if (confirm('Sei sicuro di voler eliminare questa materia?')) {
        fetch('../api/materie_api.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Errore: ' + (data.message || 'Errore sconosciuto'));
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore durante l\'eliminazione');
        });
    }
}

// Gestione submit form
document.getElementById('formMateria').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const dati = Object.fromEntries(formData);
    
    // Converti checkbox
    dati.richiede_laboratorio = document.getElementById('richiede_laboratorio').checked ? 1 : 0;
    dati.attiva = document.getElementById('attiva_materia').checked ? 1 : 0;
    
    // Calcola ore annuali dal numero reale di settimane attive o usa valore manuale
    if (oreAnnualiAutoCheckbox.checked) {
        dati.ore_annuali = Math.round(settimaneLezione * dati.ore_settimanali);
    } else {
        dati.ore_annuali = parseInt(document.getElementById('ore_annuali_input').value) || Math.round(settimaneLezione * dati.ore_settimanali);
    }
    // Inserisci peso in base al tipo se non modificato manualmente
    dati.peso = pesoEdited ? (parseInt(dati.peso) || 1) : (tipoToPeso[dati.tipo] || 1);
    
    fetch('../api/materie_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            action: dati.id ? 'update' : 'create',
            ...dati 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            chiudiModalMateria();
            location.reload();
        } else {
            alert('Errore: ' + (data.message || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore durante il salvataggio');
    });
});

// Chiudi modal cliccando fuori
window.onclick = function(event) {
    if (event.target == modalMateria) {
        chiudiModalMateria();
    }
}

// Aggiorna il peso in base al tipo selezionato se l'utente non lo ha modificato manualmente
document.getElementById('tipo').addEventListener('change', function() {
    if (!pesoEdited) {
        document.getElementById('peso').value = tipoToPeso[this.value] || 1;
    }
});

document.getElementById('peso').addEventListener('input', function() {
    pesoEdited = true;
});

// Track if weekly input was manually edited
document.getElementById('ore_settimanali').addEventListener('input', function() {
    oreSettEdited = true;
    const weekly = parseInt(this.value) || 0;
    // If auto annual mode, update display
    if (oreAnnualiAutoCheckbox.checked) {
        document.getElementById('ore_annuali_display').value = Math.round(settimaneLezione * weekly);
        oreAnnualiInput.value = Math.round(settimaneLezione * weekly);
    } else {
        // Do not overwrite manual annual value
        document.getElementById('ore_annuali_display').value = oreAnnualiInput.value || Math.round(settimaneLezione * weekly);
    }
});

// Update annual hours display on weekly hours change
document.getElementById('ore_settimanali').addEventListener('input', function() {
    const weekly = parseInt(this.value) || 0;
    const computed = Math.round(settimaneLezione * weekly);
    if (oreAnnualiAutoCheckbox.checked) {
        document.getElementById('ore_annuali_display').value = computed;
        oreAnnualiInput.value = computed;
    } else {
        // keep manual input as-is; optionally update display to show both
        document.getElementById('ore_annuali_display').value = oreAnnualiInput.value || computed;
    }
});

// Toggle manual/auto for annual hours
oreAnnualiAutoCheckbox.addEventListener('change', function() {
    const weekly = parseInt(document.getElementById('ore_settimanali').value) || 0;
    const computed = Math.round(settimaneLezione * weekly);
    if (this.checked) {
        oreAnnualiInput.classList.add('hidden');
        document.getElementById('ore_annuali_display').value = computed;
        oreAnnualiInput.value = computed;
    } else {
        oreAnnualiInput.classList.remove('hidden');
        // if input is empty, prefill with computed
        if (!oreAnnualiInput.value) oreAnnualiInput.value = computed;
        document.getElementById('ore_annuali_display').value = oreAnnualiInput.value;
    }
});

// When the manual input changes, update the display
oreAnnualiInput.addEventListener('input', function() {
    document.getElementById('ore_annuali_display').value = this.value || Math.round(settimaneLezione * (parseInt(document.getElementById('ore_settimanali').value) || 0));
    // If the user hasn't manually edited weekly hours, derive weekly hours from annual
    if (!oreSettEdited) {
        const weeklyAuto = Math.round((parseInt(this.value) || 0) / Math.max(1, settimaneLezione));
        document.getElementById('ore_settimanali').value = Math.max(1, weeklyAuto);
    }
});
</script>

<?php include '../includes/footer.php'; ?>