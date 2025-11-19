<?php
// pages/classe_form.php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$page_title = isset($_GET['id']) ? "Modifica Classe" : "Nuova Classe";
$current_page = "classi";

// Connessione al database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupero dati se modifica
$classe = null;
$is_edit = isset($_GET['id']);

if ($is_edit) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, a.anno as anno_scolastico_nome, p.nome as percorso_nome, s.nome as sede_nome
            FROM classi c
            LEFT JOIN anni_scolastici a ON c.anno_scolastico_id = a.id
            LEFT JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
            LEFT JOIN sedi s ON c.sede_id = s.id
            WHERE c.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $classe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$classe) {
            header('Location: classi.php?error=classe_non_trovata');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Errore nel recupero classe: " . $e->getMessage());
        header('Location: classi.php?error=errore_db');
        exit;
    }
}

// Recupero opzioni per form
try {
    // Anni scolastici
    $stmt_anni = $pdo->query("SELECT id, anno FROM anni_scolastici ORDER BY data_inizio DESC");
    $anni_scolastici = $stmt_anni->fetchAll(PDO::FETCH_ASSOC);
    
    // Sedi
    $stmt_sedi = $pdo->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome");
    $sedi = $stmt_sedi->fetchAll(PDO::FETCH_ASSOC);
    
    // Aule (per sede specifica se in modifica)
    $aule = [];
    if ($is_edit && $classe['sede_id']) {
        $stmt_aule = $pdo->prepare("SELECT id, nome FROM aule WHERE sede_id = ? AND attiva = 1 ORDER BY nome");
        $stmt_aule->execute([$classe['sede_id']]);
        $aule = $stmt_aule->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Errore nel recupero opzioni form: " . $e->getMessage());
    $anni_scolastici = $sedi = $aule = [];
}

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dati = [
        'nome' => trim($_POST['nome']),
        'anno_scolastico_id' => $_POST['anno_scolastico_id'],
        'percorso_formativo_id' => $_POST['percorso_formativo_id'],
        'anno_corso' => $_POST['anno_corso'],
        'sede_id' => $_POST['sede_id'],
        'numero_studenti' => $_POST['numero_studenti'],
        'ore_settimanali_previste' => $_POST['ore_settimanali_previste'],
        'aula_preferenziale_id' => $_POST['aula_preferenziale_id'] ?: null,
        'note' => trim($_POST['note']),
        'stato' => $_POST['stato']
    ];
    
    // Validazione
    $errori = [];
    
    if (empty($dati['nome'])) {
        $errori[] = "Il nome della classe è obbligatorio";
    }
    
    if (empty($dati['anno_scolastico_id'])) {
        $errori[] = "L'anno scolastico è obbligatorio";
    }
    
    if (empty($dati['percorso_formativo_id'])) {
        $errori[] = "Il percorso formativo è obbligatorio";
    }
    
    if (empty($dati['sede_id'])) {
        $errori[] = "La sede è obbligatoria";
    }
    
    // Verifica nome univoco per anno scolastico
    if (empty($errori)) {
        try {
            $sql_check = "SELECT id FROM classi WHERE nome = ? AND anno_scolastico_id = ?";
            $params_check = [$dati['nome'], $dati['anno_scolastico_id']];
            
            if ($is_edit) {
                $sql_check .= " AND id != ?";
                $params_check[] = $_GET['id'];
            }
            
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute($params_check);
            
            if ($stmt_check->fetch()) {
                $errori[] = "Esiste già una classe con questo nome per l'anno scolastico selezionato";
            }
        } catch (PDOException $e) {
            $errori[] = "Errore di verifica univocità: " . $e->getMessage();
        }
    }
    
    if (empty($errori)) {
        try {
            if ($is_edit) {
                // Update
                $sql = "UPDATE classi SET 
                        nome = ?, anno_scolastico_id = ?, percorso_formativo_id = ?, 
                        anno_corso = ?, sede_id = ?, numero_studenti = ?, 
                        ore_settimanali_previste = ?, aula_preferenziale_id = ?, 
                        note = ?, stato = ?, updated_at = NOW() 
                        WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $params = [
                    $dati['nome'], $dati['anno_scolastico_id'], $dati['percorso_formativo_id'],
                    $dati['anno_corso'], $dati['sede_id'], $dati['numero_studenti'],
                    $dati['ore_settimanali_previste'], $dati['aula_preferenziale_id'],
                    $dati['note'], $dati['stato'], $_GET['id']
                ];
            } else {
                // Insert
                $sql = "INSERT INTO classi 
                        (nome, anno_scolastico_id, percorso_formativo_id, anno_corso, 
                         sede_id, numero_studenti, ore_settimanali_previste, 
                         aula_preferenziale_id, note, stato, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $stmt = $pdo->prepare($sql);
                $params = [
                    $dati['nome'], $dati['anno_scolastico_id'], $dati['percorso_formativo_id'],
                    $dati['anno_corso'], $dati['sede_id'], $dati['numero_studenti'],
                    $dati['ore_settimanali_previste'], $dati['aula_preferenziale_id'],
                    $dati['note'], $dati['stato']
                ];
            }
            
            $stmt->execute($params);
            
            // Log attività
            $azione = $is_edit ? 'modifica' : 'creazione';
            $descrizione = $is_edit ? 
                "Modificata classe: " . $dati['nome'] : 
                "Creata classe: " . $dati['nome'];
            
            $sql_log = "INSERT INTO log_attivita (tipo, azione, descrizione, tabella, record_id, utente, ip_address) 
                        VALUES ('classe', ?, ?, 'classi', ?, ?, ?)";
            $stmt_log = $pdo->prepare($sql_log);
            $stmt_log->execute([
                $azione, $descrizione, 
                $is_edit ? $_GET['id'] : $pdo->lastInsertId(),
                $_SESSION['username'],
                $_SERVER['REMOTE_ADDR']
            ]);
            
            header('Location: classi.php?success=' . ($is_edit ? 'classe_modificata' : 'classe_creata'));
            exit;
            
        } catch (PDOException $e) {
            $errori[] = "Errore nel salvataggio: " . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo $page_title; ?></h1>
                    <p class="text-gray-600 mt-1">
                        <?php echo $is_edit ? 'Modifica i dati della classe' : 'Crea una nuova classe'; ?>
                    </p>
                </div>
                <div>
                    <a href="classi.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        <i class="fas fa-arrow-left mr-2"></i>Torna alle Classi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Form -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Dati Classe</h2>
            </div>
            
            <?php if (!empty($errori)): ?>
                <div class="m-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Si sono verificati i seguenti errori:</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <?php foreach ($errori as $errore): ?>
                                        <li><?php echo htmlspecialchars($errore); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nome Classe -->
                    <div>
                        <label for="nome" class="block text-sm font-medium text-gray-700 mb-1">Nome Classe *</label>
                        <input type="text" id="nome" name="nome" required
                               value="<?php echo htmlspecialchars($classe['nome'] ?? ''); ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Es: 1A INF">
                    </div>
                    
                    <!-- Anno Scolastico -->
                    <div>
                        <label for="anno_scolastico_id" class="block text-sm font-medium text-gray-700 mb-1">Anno Scolastico *</label>
                        <select id="anno_scolastico_id" name="anno_scolastico_id" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona anno scolastico</option>
                            <?php foreach ($anni_scolastici as $anno): ?>
                                <option value="<?php echo $anno['id']; ?>" 
                                    <?php echo ($classe['anno_scolastico_id'] ?? '') == $anno['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($anno['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Sede -->
                    <div>
                        <label for="sede_id" class="block text-sm font-medium text-gray-700 mb-1">Sede *</label>
                        <select id="sede_id" name="sede_id" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona sede</option>
                            <?php foreach ($sedi as $sede): ?>
                                <option value="<?php echo $sede['id']; ?>" 
                                    <?php echo ($classe['sede_id'] ?? '') == $sede['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sede['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
    <!-- Percorso Formativo -->
                    <div>
                        <label for="percorso_formativo_id" class="block text-sm font-medium text-gray-700 mb-1">Percorso Formativo *</label>
                        <select id="percorso_formativo_id" name="percorso_formativo_id" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona percorso</option>
                            <?php 
                            // ✅ CORRETTO: Popola percorsi direttamente se sede selezionata
                            if ($is_edit && $classe['sede_id']): 
                                $sql_percorsi = "SELECT id, nome FROM percorsi_formativi WHERE attivo = 1 AND sede_id = ? ORDER BY nome";
                                $percorsi = Database::queryAll($sql_percorsi, [$classe['sede_id']]);
                                foreach ($percorsi as $perc): 
                            ?>
                                <option value="<?php echo $perc['id']; ?>" 
                                    <?php echo ($classe['percorso_formativo_id'] ?? '') == $perc['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($perc['nome']); ?>
                                </option>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </select>
                    </div>
                    
                    <!-- Anno Corso -->
                    <div>
                        <label for="anno_corso" class="block text-sm font-medium text-gray-700 mb-1">Anno Corso *</label>
                        <select id="anno_corso" name="anno_corso" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona anno</option>
                            <option value="1" <?php echo ($classe['anno_corso'] ?? '') == '1' ? 'selected' : ''; ?>>1° Anno</option>
                            <option value="2" <?php echo ($classe['anno_corso'] ?? '') == '2' ? 'selected' : ''; ?>>2° Anno</option>
                            <option value="3" <?php echo ($classe['anno_corso'] ?? '') == '3' ? 'selected' : ''; ?>>3° Anno</option>
                            <option value="4" <?php echo ($classe['anno_corso'] ?? '') == '4' ? 'selected' : ''; ?>>4° Anno</option>
                            <option value="5" <?php echo ($classe['anno_corso'] ?? '') == '5' ? 'selected' : ''; ?>>5° Anno</option>
                        </select>
                    </div>
                    
                    <!-- Numero Studenti -->
                    <div>
                        <label for="numero_studenti" class="block text-sm font-medium text-gray-700 mb-1">Numero Studenti</label>
                        <input type="number" id="numero_studenti" name="numero_studenti" min="1" max="40"
                               value="<?php echo htmlspecialchars($classe['numero_studenti'] ?? '20'); ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Ore Settimanali Previste -->
                    <div>
                        <label for="ore_settimanali_previste" class="block text-sm font-medium text-gray-700 mb-1">Ore Settimanali Previste</label>
                        <input type="number" id="ore_settimanali_previste" name="ore_settimanali_previste" min="1" max="40" step="1"
                               value="<?php echo htmlspecialchars($classe['ore_settimanali_previste'] ?? '33'); ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Aula Preferenziale -->
                    <div>
                        <label for="aula_preferenziale_id" class="block text-sm font-medium text-gray-700 mb-1">Aula Preferenziale</label>
                        <select id="aula_preferenziale_id" name="aula_preferenziale_id"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Nessuna aula preferenziale</option>
                            <?php foreach ($aule as $aula): ?>
                                <option value="<?php echo $aula['id']; ?>" 
                                    <?php echo ($classe['aula_preferenziale_id'] ?? '') == $aula['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($aula['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Stato -->
                    <div>
                        <label for="stato" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                        <select id="stato" name="stato" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="attiva" <?php echo ($classe['stato'] ?? 'attiva') == 'attiva' ? 'selected' : ''; ?>>Attiva</option>
                            <option value="inattiva" <?php echo ($classe['stato'] ?? '') == 'inattiva' ? 'selected' : ''; ?>>Inattiva</option>
                            <option value="completata" <?php echo ($classe['stato'] ?? '') == 'completata' ? 'selected' : ''; ?>>Completata</option>
                            <option value="sospesa" <?php echo ($classe['stato'] ?? '') == 'sospesa' ? 'selected' : ''; ?>>Sospesa</option>
                        </select>
                    </div>
                </div>
                
                <!-- Note -->
                <div>
                    <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                    <textarea id="note" name="note" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Note aggiuntive sulla classe..."><?php echo htmlspecialchars($classe['note'] ?? ''); ?></textarea>
                </div>
                
                <!-- Pulsanti -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="classi.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        Annulla
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        <i class="fas fa-save mr-2"></i><?php echo $is_edit ? 'Aggiorna Classe' : 'Crea Classe'; ?>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
// Carica percorsi formativi in base alla sede
document.getElementById('sede_id').addEventListener('change', function() {
    const sedeId = this.value;
    const percorsoSelect = document.getElementById('percorso_formativo_id');
    
    if (sedeId) {
        fetch(`../api/classi_api.php?action=get_percorsi&sede_id=${sedeId}`)
            .then(response => response.json())
            .then(data => {
                percorsoSelect.innerHTML = '<option value="">Seleziona percorso</option>';
                data.forEach(percorso => {
                    const option = document.createElement('option');
                    option.value = percorso.id;
                    option.textContent = percorso.nome;
                    percorsoSelect.appendChild(option);
                });
                
                // Se in modifica, seleziona il percorso corrente
                <?php if ($is_edit && isset($classe['percorso_formativo_id'])): ?>
                    percorsoSelect.value = '<?php echo $classe['percorso_formativo_id']; ?>';
                <?php endif; ?>
            })
            .catch(error => {
                console.error('Errore nel caricamento percorsi:', error);
            });
    } else {
        percorsoSelect.innerHTML = '<option value="">Seleziona percorso</option>';
    }
});

// Carica aule in base alla sede
document.getElementById('sede_id').addEventListener('change', function() {
    const sedeId = this.value;
    const aulaSelect = document.getElementById('aula_preferenziale_id');
    
    if (sedeId) {
        fetch(`../api/classi_api.php?action=get_aule&sede_id=${sedeId}`)
            .then(response => response.json())
            .then(data => {
                aulaSelect.innerHTML = '<option value="">Nessuna aula preferenziale</option>';
                data.forEach(aula => {
                    const option = document.createElement('option');
                    option.value = aula.id;
                    option.textContent = aula.nome;
                    aulaSelect.appendChild(option);
                });
                
                // Se in modifica, seleziona l'aula corrente
                <?php if ($is_edit && isset($classe['aula_preferenziale_id'])): ?>
                    aulaSelect.value = '<?php echo $classe['aula_preferenziale_id']; ?>';
                <?php endif; ?>
            })
            .catch(error => {
                console.error('Errore nel caricamento aule:', error);
            });
    } else {
        aulaSelect.innerHTML = '<option value="">Nessuna aula preferenziale</option>';
    }
});

// Trigger cambio sede al caricamento se in modifica
<?php if ($is_edit && isset($classe['sede_id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('sede_id').dispatchEvent(new Event('change'));
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>