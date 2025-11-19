<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Controllo permessi
if (!in_array($_SESSION['ruolo'], ['preside', 'vice_preside', 'segreteria', 'amministratore'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$materia_id = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : 0;
if (!$materia_id) {
    $_SESSION['error'] = "Materia non specificata";
    header('Location: ' . BASE_URL . '/pages/materie.php');
    exit();
}

// Dati materia
$sql = "
    SELECT m.*, p.nome as percorso_nome 
    FROM materie m 
    JOIN percorsi_formativi p ON m.percorso_formativo_id = p.id 
    WHERE m.id = ?
";
$materia = Database::queryOne($sql, [$materia_id]);

if (!$materia) {
    $_SESSION['error'] = "Materia non trovata";
    header('Location: ' . BASE_URL . '/pages/materie.php');
    exit();
}

// Docenti abilitati per questa materia
$sql_docenti = "
    SELECT dm.*, d.cognome, d.nome, d.email, s.nome as sede_nome
    FROM docenti_materie dm
    JOIN docenti d ON dm.docente_id = d.id
    JOIN sedi s ON d.sede_principale_id = s.id
    WHERE dm.materia_id = ? AND dm.abilitato = 1
    ORDER BY dm.preferenza, d.cognome, d.nome
";
$docenti = Database::queryAll($sql_docenti, [$materia_id]);

// Tutti i docenti disponibili
$sql_tutti = "
    SELECT d.*, s.nome as sede_nome 
    FROM docenti d 
    JOIN sedi s ON d.sede_principale_id = s.id 
    WHERE d.stato = 'attivo' 
    ORDER BY d.cognome, d.nome
";
$tutti_docenti = Database::queryAll($sql_tutti, []);

// Imposta current_page PRIMA di includere header
$current_page = 'materie';
$page_title = 'Gestione Docenti per Materia';

// ✅ CORRETTO: Include header DOPO tutti i controlli e i redirect
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Intestazione con Titolo e Navigazione -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-book text-blue-600 mr-3"></i>Docenti per Materia
                </h1>
                <p class="text-lg font-semibold text-blue-600 mt-2">
                    <?php echo htmlspecialchars($materia['nome']); ?>
                </p>
                <p class="text-sm text-gray-600 mt-1">
                    <i class="fas fa-graduation-cap mr-1"></i>
                    <?php echo htmlspecialchars($materia['percorso_nome']); ?> - 
                    <?php echo $materia['anno_corso']; ?>° Anno
                </p>
            </div>
            <a href="materie.php" class="inline-flex items-center justify-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg transition duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Torna alle Materie
            </a>
        </div>
    </div>

    <!-- Docenti Abilitati -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 border-b">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-users mr-2"></i>Docenti Abilitati (<?php echo count($docenti); ?>)
            </h2>
        </div>
        
        <div class="p-6">
            <?php if ($docenti): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">
                                    <i class="fas fa-user mr-2 text-blue-600"></i>Docente
                                </th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">
                                    <i class="fas fa-building mr-2 text-blue-600"></i>Sede
                                </th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">
                                    <i class="fas fa-star mr-2 text-blue-600"></i>Preferenza
                                </th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">
                                    <i class="fas fa-envelope mr-2 text-blue-600"></i>Email
                                </th>
                                <th class="text-center py-3 px-4 font-semibold text-gray-700">
                                    <i class="fas fa-cog mr-2 text-blue-600"></i>Azioni
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($docenti as $docente): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                    <td class="py-4 px-4">
                                        <p class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']); ?>
                                        </p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($docente['sede_nome']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php 
                                        $pref = $docente['preferenza'];
                                        $badge_color = $pref == 1 ? 'bg-red-100 text-red-800' : 
                                                      ($pref == 2 ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800');
                                        $pref_text = $pref == 1 ? 'Alta' : ($pref == 2 ? 'Media' : 'Bassa');
                                        ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold <?php echo $badge_color; ?>">
                                            <?php echo $pref_text; ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <a href="mailto:<?php echo htmlspecialchars($docente['email']); ?>" class="text-blue-600 hover:underline text-sm">
                                            <?php echo htmlspecialchars($docente['email']); ?>
                                        </a>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="modificaPreferenza(<?php echo $docente['id']; ?>, <?php echo $docente['preferenza']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium rounded transition">
                                                <i class="fas fa-edit mr-1"></i>Modifica
                                            </button>
                                            <button onclick="rimuoviAbilitazione(<?php echo $docente['id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 font-medium rounded transition">
                                                <i class="fas fa-trash mr-1"></i>Rimuovi
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-gray-400 text-4xl mb-3"></i>
                    <p class="text-gray-500 text-lg">Nessun docente abilitato per questa materia.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aggiungi Docente -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-green-700 border-b">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>Aggiungi Docente
            </h2>
        </div>
        
        <div class="p-6">
            <form id="aggiungiDocenteForm" class="space-y-6">
                <input type="hidden" name="materia_id" value="<?php echo $materia_id; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Seleziona Docente -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-check text-blue-600 mr-2"></i>Docente *
                        </label>
                        <select name="docente_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="">Seleziona docente</option>
                            <?php foreach ($tutti_docenti as $docente): ?>
                                <option value="<?php echo $docente['id']; ?>">
                                    <?php echo htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']); ?> 
                                    (<?php echo htmlspecialchars($docente['sede_nome']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Seleziona Preferenza -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-star text-amber-500 mr-2"></i>Preferenza
                        </label>
                        <select name="preferenza" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="1">🔴 Alta</option>
                            <option value="2" selected>🟡 Media</option>
                            <option value="3">🔵 Bassa</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition duration-200 flex items-center justify-center">
                        <i class="fas fa-plus mr-2"></i>Aggiungi Docente
                    </button>
                    <a href="materie.php" class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-times mr-2"></i>Annulla
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifica Preferenza -->
<div id="preferenzaModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-star text-amber-500 mr-2"></i>Modifica Preferenza
            </h2>
        </div>
        
        <form id="preferenzaForm" class="p-6 space-y-4">
            <input type="hidden" name="id" id="docenteMateriaId">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Preferenza</label>
                <select name="preferenza" id="preferenzaSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="1">🔴 Alta</option>
                    <option value="2">🟡 Media</option>
                    <option value="3">🔵 Bassa</option>
                </select>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="chiudiModal()" class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg transition">
                    Annulla
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                    Salva
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function modificaPreferenza(id, preferenza) {
    document.getElementById('docenteMateriaId').value = id;
    document.getElementById('preferenzaSelect').value = preferenza;
    document.getElementById('preferenzaModal').classList.remove('hidden');
}

function chiudiModal() {
    document.getElementById('preferenzaModal').classList.add('hidden');
}

function rimuoviAbilitazione(id) {
    if (confirm('Sei sicuro di voler rimuovere questa abilitazione?')) {
        fetch('../api/materie_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id, action: 'rimuovi_abilitazione' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Abilitazione rimossa con successo!');
                location.reload();
            } else {
                alert('Errore: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore di connessione');
        });
    }
}

document.getElementById('aggiungiDocenteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'aggiungi_docente';
    
    fetch('../api/materie_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Docente aggiunto con successo!');
            location.reload();
        } else {
            alert('Errore: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione');
    });
});

document.getElementById('preferenzaForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'modifica_preferenza';
    
    fetch('../api/materie_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            chiudiModal();
            alert('Preferenza aggiornata con successo!');
            location.reload();
        } else {
            alert('Errore: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione');
    });
});

// Chiudi modal cliccando fuori
document.getElementById('preferenzaModal').addEventListener('click', function(e) {
    if (e.target === this) {
        chiudiModal();
    }
});
</script>