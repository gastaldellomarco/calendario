<?php
// pages/classi.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$current_page = "classi";
$page_title = "Gestione Classi";

// Connessione al database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupero filtri
$filtro_sede = sanitizeInput($_GET['sede'] ?? '');
$filtro_anno_scolastico = sanitizeInput($_GET['anno_scolastico'] ?? '');
$filtro_percorso = sanitizeInput($_GET['percorso'] ?? '');
$filtro_stato = sanitizeInput($_GET['stato'] ?? '');
$filtro_ricerca = sanitizeInput($_GET['ricerca'] ?? '');
$vista = $_GET['vista'] ?? 'tabella';

// Recupero opzioni per filtri
try {
    // Sedi
    $stmt_sedi = $pdo->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome");
    $sedi = $stmt_sedi->fetchAll(PDO::FETCH_ASSOC);
    
    // Anni scolastici
    $stmt_anni = $pdo->query("SELECT id, anno FROM anni_scolastici ORDER BY data_inizio DESC");
    $anni_scolastici = $stmt_anni->fetchAll(PDO::FETCH_ASSOC);
    
    // Percorsi formativi
    $stmt_percorsi = $pdo->query("SELECT id, nome FROM percorsi_formativi WHERE attivo = 1 ORDER BY nome");
    $percorsi = $stmt_percorsi->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Errore nel recupero opzioni filtri: " . $e->getMessage());
    $sedi = $anni_scolastici = $percorsi = [];
}

// Query classi con filtri
try {
    $sql = "SELECT c.*, 
                   a.anno as anno_scolastico_nome,
                   p.nome as percorso_nome,
                   s.nome as sede_nome,
                   au.nome as aula_nome
            FROM classi c
            LEFT JOIN anni_scolastici a ON c.anno_scolastico_id = a.id
            LEFT JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
            LEFT JOIN sedi s ON c.sede_id = s.id
            LEFT JOIN aule au ON c.aula_preferenziale_id = au.id
            WHERE 1=1";
    
    $params = [];
    
    if ($filtro_sede) {
        $sql .= " AND c.sede_id = ?";
        $params[] = $filtro_sede;
    }
    
    if ($filtro_anno_scolastico) {
        $sql .= " AND c.anno_scolastico_id = ?";
        $params[] = $filtro_anno_scolastico;
    }
    
    if ($filtro_percorso) {
        $sql .= " AND c.percorso_formativo_id = ?";
        $params[] = $filtro_percorso;
    }
    
    if ($filtro_stato) {
        $sql .= " AND c.stato = ?";
        $params[] = $filtro_stato;
    }
    
    if ($filtro_ricerca) {
        $sql .= " AND c.nome LIKE ?";
        $params[] = "%$filtro_ricerca%";
    }
    
    $sql .= " ORDER BY c.nome, c.anno_corso";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Errore nel recupero classi: " . $e->getMessage());
    $classi = [];
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Gestione Classi</h1>
                    <p class="text-gray-600 mt-1">Gestisci le classi dell'istituto</p>
                </div>
                <div>
                    <a href="<?php echo BASE_URL; ?>/pages/classe_form.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        <i class="fas fa-plus mr-2"></i>Nuova Classe
                    </a>
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
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Ricerca -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ricerca</label>
                        <input type="text" name="ricerca" value="<?php echo htmlspecialchars($filtro_ricerca); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" 
                               placeholder="Cerca classe...">
                    </div>
                    
                    <!-- Sede -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sede</label>
                        <select name="sede" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Tutte le sedi</option>
                            <?php foreach ($sedi as $sede): ?>
                                <option value="<?php echo $sede['id']; ?>" <?php echo $filtro_sede == $sede['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sede['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Anno Scolastico -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Anno Scolastico</label>
                        <select name="anno_scolastico" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Tutti gli anni</option>
                            <?php foreach ($anni_scolastici as $anno): ?>
                                <option value="<?php echo $anno['id']; ?>" <?php echo $filtro_anno_scolastico == $anno['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($anno['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
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
                    
                    <!-- Stato -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                        <select name="stato" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Tutti gli stati</option>
                            <option value="attiva" <?php echo $filtro_stato == 'attiva' ? 'selected' : ''; ?>>Attiva</option>
                            <option value="inattiva" <?php echo $filtro_stato == 'inattiva' ? 'selected' : ''; ?>>Inattiva</option>
                            <option value="completata" <?php echo $filtro_stato == 'completata' ? 'selected' : ''; ?>>Completata</option>
                        </select>
                    </div>
                    
                    <!-- Pulsanti -->
                    <div class="md:col-span-2 lg:col-span-5 flex justify-between items-end">
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-filter mr-1"></i>Applica Filtri
                            </button>
                            <a href="classi.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-times mr-1"></i>Reset
                            </a>
                        </div>
                        
                        <!-- Toggle Vista -->
                        <div class="flex space-x-1 bg-gray-100 rounded-lg p-1">
                            <button type="button" onclick="cambiaVista('tabella')" 
                                    class="px-3 py-1 rounded-md text-sm font-medium <?php echo $vista == 'tabella' ? 'bg-white shadow' : 'text-gray-600'; ?>">
                                <i class="fas fa-table"></i>
                            </button>
                            <button type="button" onclick="cambiaVista('card')" 
                                    class="px-3 py-1 rounded-md text-sm font-medium <?php echo $vista == 'card' ? 'bg-white shadow' : 'text-gray-600'; ?>">
                                <i class="fas fa-th-large"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Risultati -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-medium text-gray-900">
                    Classi (<?php echo count($classi); ?>)
                </h2>
                <div class="text-sm text-gray-500">
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>

            <?php if ($vista == 'tabella'): ?>
                <!-- Vista Tabella -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Anno Corso</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percorso</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sede</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Studenti</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore Sett.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($classi)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                        Nessuna classe trovata
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classi as $classe): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($classe['nome']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($classe['anno_scolastico_nome'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $classe['anno_corso']; ?>° anno
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($classe['percorso_nome'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($classe['sede_nome'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $classe['numero_studenti']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $classe['ore_settimanali_previste']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $classe['stato'] == 'attiva' ? 'bg-green-100 text-green-800' : 
                                                   ($classe['stato'] == 'completata' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo ucfirst($classe['stato']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="<?php echo BASE_URL; ?>/pages/classe_form.php?id=<?php echo $classe['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900" title="Modifica">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/pages/assegna_materie_classe.php?classe_id=<?php echo $classe['id']; ?>" 
                                               class="text-green-600 hover:text-green-900" title="Assegna Materie">
                                                <i class="fas fa-book"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/pages/orari_slot.php?classe_id=<?php echo $classe['id']; ?>" 
                                               class="text-purple-600 hover:text-purple-900" title="Orario">
                                                <i class="fas fa-calendar-alt"></i>
                                            </a>
                                            <button onclick="eliminaClasse(<?php echo $classe['id']; ?>)" 
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
            <?php else: ?>
                <!-- Vista Card -->
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($classi)): ?>
                        <div class="col-span-full text-center py-8 text-gray-500">
                            <i class="fas fa-users text-4xl mb-4"></i>
                            <p>Nessuna classe trovata</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($classi as $classe): ?>
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($classe['nome']); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($classe['percorso_nome'] ?? 'N/A'); ?></p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $classe['stato'] == 'attiva' ? 'bg-green-100 text-green-800' : 
                                               ($classe['stato'] == 'completata' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($classe['stato']); ?>
                                    </span>
                                </div>
                                
                                <div class="space-y-2 text-sm text-gray-600">
                                    <div class="flex justify-between">
                                        <span>Anno Corso:</span>
                                        <span class="font-medium"><?php echo $classe['anno_corso']; ?>°</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Sede:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($classe['sede_nome'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Studenti:</span>
                                        <span class="font-medium"><?php echo $classe['numero_studenti']; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Ore Sett.:</span>
                                        <span class="font-medium"><?php echo $classe['ore_settimanali_previste']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between">
                                    <div class="flex space-x-2">
                                        <a href="classe_form.php?id=<?php echo $classe['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Modifica">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/pages/assegna_materie_classe.php?classe_id=<?php echo $classe['id']; ?>" 
                                           class="text-green-600 hover:text-green-900" title="Materie">
                                            <i class="fas fa-book"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/pages/orari_slot.php?classe_id=<?php echo $classe['id']; ?>" 
                                           class="text-purple-600 hover:text-purple-900" title="Orario">
                                            <i class="fas fa-calendar-alt"></i>
                                        </a>
                                    </div>
                                    <button onclick="eliminaClasse(<?php echo $classe['id']; ?>)" 
                                            class="text-red-600 hover:text-red-900" title="Elimina">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function cambiaVista(tipo) {
    const url = new URL(window.location.href);
    url.searchParams.set('vista', tipo);
    window.location.href = url.toString();
}

function eliminaClasse(classeId) {
    if (confirm('Sei sicuro di voler eliminare questa classe?')) {
        fetch('../api/classi_api.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: classeId })
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>