<?php
// pages/percorsi.php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$page_title = "Percorsi Formativi";
$current_page = "percorsi";

// Connessione al database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupero percorsi
try {
    $sql = "SELECT p.*, s.nome as sede_nome
            FROM percorsi_formativi p
            LEFT JOIN sedi s ON p.sede_id = s.id
            ORDER BY p.nome";
    
    $stmt = $pdo->query($sql);
    $percorsi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Errore nel recupero percorsi: " . $e->getMessage());
    $percorsi = [];
}
?>

<?php include '../includes/header.php'; ?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Percorsi Formativi</h1>
                    <p class="text-gray-600 mt-1">Gestisci i percorsi formativi dell'istituto</p>
                </div>
                <div>
                    <button onclick="apriModalPercorso()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        <i class="fas fa-plus mr-2"></i>Nuovo Percorso
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Lista Percorsi -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">
                    Percorsi Formativi (<?php echo count($percorsi); ?>)
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sede</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durata</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore Annuali</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($percorsi)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    Nessun percorso formativo trovato
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($percorsi as $percorso): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($percorso['nome']); ?></div>
                                    <?php if ($percorso['descrizione']): ?>
                                        <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($percorso['descrizione']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($percorso['codice']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($percorso['sede_nome'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $percorso['durata_anni']; ?> anni
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $percorso['ore_annuali_base']; ?> ore
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $percorso['attivo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $percorso['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="modificaPercorso(<?php echo $percorso['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900" title="Modifica">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="eliminaPercorso(<?php echo $percorso['id']; ?>)" 
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

<!-- Modal Percorso -->
<div id="modalPercorso" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitolo">Nuovo Percorso</h3>
                <button onclick="chiudiModalPercorso()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="formPercorso" class="space-y-4 mt-4">
                <input type="hidden" id="percorso_id" name="id">
                
                <div>
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                    <input type="text" id="nome" name="nome" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                </div>
                
                <div>
                    <label for="codice" class="block text-sm font-medium text-gray-700 mb-1">Codice *</label>
                    <input type="text" id="codice" name="codice" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                </div>
                
                <div>
                    <label for="sede_id" class="block text-sm font-medium text-gray-700 mb-1">Sede *</label>
                    <select id="sede_id" name="sede_id" required
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Seleziona sede</option>
                        <?php
                        $stmt_sedi = $pdo->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome");
                        $sedi = $stmt_sedi->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($sedi as $sede): ?>
                            <option value="<?php echo $sede['id']; ?>"><?php echo htmlspecialchars($sede['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="durata_anni" class="block text-sm font-medium text-gray-700 mb-1">Durata (anni)</label>
                        <input type="number" id="durata_anni" name="durata_anni" min="1" max="5" value="3"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    
                    <div>
                        <label for="ore_annuali_base" class="block text-sm font-medium text-gray-700 mb-1">Ore Annuali</label>
                        <input type="number" id="ore_annuali_base" name="ore_annuali_base" min="1" value="990"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
                
                <div>
                    <label for="descrizione" class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                    <textarea id="descrizione" name="descrizione" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"></textarea>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" id="attivo" name="attivo" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                        <span class="ml-2 text-sm text-gray-700">Percorso attivo</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="chiudiModalPercorso()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Annulla
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Salva
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let modalPercorso = document.getElementById('modalPercorso');

function apriModalPercorso() {
    document.getElementById('modalTitolo').textContent = 'Nuovo Percorso';
    document.getElementById('formPercorso').reset();
    document.getElementById('percorso_id').value = '';
    modalPercorso.classList.remove('hidden');
}

function chiudiModalPercorso() {
    modalPercorso.classList.add('hidden');
}

function modificaPercorso(id) {
    fetch(`../api/classi_api.php?action=get_percorso&id=${id}`)
        .then(response => response.json())
        .then(percorso => {
            document.getElementById('modalTitolo').textContent = 'Modifica Percorso';
            document.getElementById('percorso_id').value = percorso.id;
            document.getElementById('nome').value = percorso.nome;
            document.getElementById('codice').value = percorso.codice;
            document.getElementById('sede_id').value = percorso.sede_id;
            document.getElementById('durata_anni').value = percorso.durata_anni;
            document.getElementById('ore_annuali_base').value = percorso.ore_annuali_base;
            document.getElementById('descrizione').value = percorso.descrizione || '';
            document.getElementById('attivo').checked = percorso.attivo;
            
            modalPercorso.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore nel caricamento percorso');
        });
}

function eliminaPercorso(id) {
    if (confirm('Sei sicuro di voler eliminare questo percorso?')) {
        fetch('../api/classi_api.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id, tipo: 'percorso' })
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
document.getElementById('formPercorso').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const dati = Object.fromEntries(formData);
    
    // Converti checkbox
    dati.attivo = document.getElementById('attivo').checked ? 1 : 0;
    
    fetch('../api/classi_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            action: dati.id ? 'update_percorso' : 'create_percorso',
            ...dati 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            chiudiModalPercorso();
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
    if (event.target == modalPercorso) {
        chiudiModalPercorso();
    }
}
</script>

<?php include '../includes/footer.php'; ?>