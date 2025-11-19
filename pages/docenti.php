<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// ✅ CORREZIONE: Imposta current_page e page_title
$current_page = 'docenti';
$page_title = 'Gestione Docenti';

// Verifica permessi
if (!in_array($_SESSION['ruolo'], ['preside', 'vice_preside', 'segreteria', 'amministratore'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Parametri paginazione e ricerca
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search'] ?? '') : '';
$sede_filter = isset($_GET['sede']) ? (int)$_GET['sede'] : '';
$stato_filter = isset($_GET['stato']) ? sanitizeInput($_GET['stato'] ?? '') : '';

// ✅ CORREZIONE: Usa PDO invece di MySQLi
try {
    $pdo = getPDOConnection();
    
    // Query base
    $sql = "SELECT d.*, s.nome as sede_nome, 
                   (SELECT COUNT(*) FROM docenti_materie dm WHERE dm.docente_id = d.id AND dm.abilitato = 1) as num_materie,
                   (SELECT COUNT(*) FROM vincoli_docenti vd WHERE vd.docente_id = d.id AND vd.attivo = 1) as num_vincoli
            FROM docenti d 
            LEFT JOIN sedi s ON d.sede_principale_id = s.id 
            WHERE 1=1";
    $params = [];
    
    // Applica filtri
    if (!empty($search)) {
        $sql .= " AND (d.cognome LIKE ? OR d.nome LIKE ? OR d.email LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    if (!empty($sede_filter)) {
        $sql .= " AND d.sede_principale_id = ?";
        $params[] = $sede_filter;
    }
    
    if (!empty($stato_filter)) {
        $sql .= " AND d.stato = ?";
        $params[] = $stato_filter;
    }
    
    // Count per paginazione
    $count_sql = "SELECT COUNT(*) as total FROM ($sql) as counted";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $limit);
    
    // Query principale con paginazione
    $sql .= " ORDER BY d.cognome, d.nome LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $docenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sedi per filtro
    $sedi_stmt = $pdo->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome");
    $sedi = $sedi_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Errore docenti: " . $e->getMessage());
    $docenti = [];
    $sedi = [];
    $total_rows = 0;
    $total_pages = 0;
}

// ✅ CORRETTO: Include header DOPO aver impostato $current_page e $page_title
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Gestione Docenti</h1>
            <div class="space-x-2">
                <button onclick="exportDocenti()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </button>
                <button onclick="openImportModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-upload mr-2"></i>Import CSV
                </button>
                <button onclick="openDocenteForm()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Nuovo Docente
                </button>
            </div>
        </div>

        <!-- Filtri e Ricerca -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form id="searchForm" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Cognome, nome, email..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sede</label>
                    <select name="sede" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Tutte le sedi</option>
                        <?php foreach ($sedi as $sede): ?>
                            <option value="<?= $sede['id'] ?>" <?= $sede_filter == $sede['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sede['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                    <select name="stato" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Tutti</option>
                        <option value="attivo" <?= $stato_filter == 'attivo' ? 'selected' : '' ?>>Attivo</option>
                        <option value="inattivo" <?= $stato_filter == 'inattivo' ? 'selected' : '' ?>>Inattivo</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md">
                        <i class="fas fa-search mr-2"></i>Cerca
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabella Docenti -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contatti</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sede</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Materie</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vincoli</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore/Sett</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($docenti as $docente): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($docente['cognome']) ?> <?= htmlspecialchars($docente['nome']) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        CF: <?= htmlspecialchars($docente['codice_fiscale'] ?? 'N/D') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($docente['email'] ?? '') ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($docente['telefono'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($docente['sede_nome'] ?? 'N/D') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= $docente['num_materie'] ?> materie
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        <?= $docente['num_vincoli'] ?> vincoli
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $docente['ore_settimanali_contratto'] ?>h
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?= $docente['stato'] == 'attivo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= ucfirst($docente['stato']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="openDocenteForm(<?= $docente['id'] ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900" title="Modifica">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="openVincoli(<?= $docente['id'] ?>)" 
                                            class="text-yellow-600 hover:text-yellow-900" title="Vincoli Orari">
                                        <i class="fas fa-calendar-alt"></i>
                                    </button>
                                    <button onclick="openMaterie(<?= $docente['id'] ?>)" 
                                            class="text-purple-600 hover:text-purple-900" title="Materie">
                                        <i class="fas fa-book"></i>
                                    </button>
                                    <button onclick="deleteDocente(<?= $docente['id'] ?>, '<?= htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']) ?>')" 
                                            class="text-red-600 hover:text-red-900" title="Elimina">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Mostrando <span class="font-medium"><?= $offset + 1 ?></span> a 
                            <span class="font-medium"><?= min($offset + $limit, $total_rows) ?></span> di 
                            <span class="font-medium"><?= $total_rows ?></span> risultati
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Precedente
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium 
                                          <?= $i == $page ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Successiva
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modale Import CSV -->
    <div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Importa Docenti da CSV</h3>
            </div>
            <div class="px-6 py-4">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">File CSV</label>
                        <input type="file" name="csv_file" accept=".csv" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        <p class="mt-1 text-sm text-gray-500">Formato: cognome,nome,email,codice_fiscale,telefono,sede_id</p>
                    </div>
                </form>
            </div>
            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-3">
                <button type="button" onclick="closeImportModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                    Annulla
                </button>
                <button type="button" onclick="importCSV()" 
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    Importa
                </button>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
    // Funzioni per gestione docenti
    function openDocenteForm(id = null) {
        if (id) {
            window.location.href = 'docente_form.php?id=' + id;
        } else {
            window.location.href = 'docente_form.php';
        }
    }

    function openVincoli(id) {
        window.location.href = 'vincoli_docente.php?docente_id=' + id;
    }

    function openMaterie(id) {
        // Reindirizza alla pagina dove assegnare materie al docente
        window.location.href = 'docente_materie.php?docente_id=' + id;
    }

    function deleteDocente(id, nome) {
        if (confirm('Eliminare il docente "' + nome + '"? Questa azione non può essere annullata.')) {
            fetch('../api/docenti_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    action: 'delete',
                    id: id 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Docente eliminato con successo');
                    location.reload();
                } else {
                    alert('Errore: ' + (data.message || 'Errore sconosciuto'));
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                alert('Errore di connessione');
            });
        }
    }

    function exportDocenti() {
        window.location.href = '../api/export_docenti.php';
    }

    function openImportModal() {
        document.getElementById('importModal').classList.remove('hidden');
    }

    function closeImportModal() {
        document.getElementById('importModal').classList.add('hidden');
    }

    function importCSV() {
        const fileInput = document.querySelector('#importForm input[type="file"]');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Selezionare un file CSV');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        fetch('../api/docenti_api.php?action=import', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Import completato con successo');
                closeImportModal();
                location.reload();
            } else {
                alert('Errore: ' + (data.message || 'Errore sconosciuto'));
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Errore di connessione');
        });
    }

    // Chiudi modal cliccando fuori
    document.getElementById('importModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeImportModal();
        }
    });
    </script>