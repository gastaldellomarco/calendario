<?php
// pages/assegna_materie_classe.php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$page_title = "Assegna Materie alla Classe";
$current_page = "classi";

// Verifica parametro classe_id
if (!isset($_GET['classe_id'])) {
    header('Location: classi.php?error=classe_non_specificata');
    exit;
}

$classe_id = $_GET['classe_id'];

// Connessione al database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupero dati classe
try {
    $stmt = $pdo->prepare("
        SELECT c.*, a.anno as anno_scolastico_nome, p.nome as percorso_nome, 
               s.nome as sede_nome, au.nome as aula_nome
        FROM classi c
        LEFT JOIN anni_scolastici a ON c.anno_scolastico_id = a.id
        LEFT JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
        LEFT JOIN sedi s ON c.sede_id = s.id
        LEFT JOIN aule au ON c.aula_preferenziale_id = au.id
        WHERE c.id = ?
    ");
    $stmt->execute([$classe_id]);
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

// Recupero materie disponibili per il percorso e anno
try {
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM materie m
        WHERE m.percorso_formativo_id = ? AND m.anno_corso = ? AND m.attiva = 1
        ORDER BY m.tipo, m.nome
    ");
    $stmt->execute([$classe['percorso_formativo_id'], $classe['anno_corso']]);
    $materie_disponibili = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero materie disponibili: " . $e->getMessage());
    $materie_disponibili = [];
}

// Ottieni settimane di lezione per l'anno scolastico attivo (fallback 33)
try {
    $stmt_weeks = $pdo->prepare("SELECT settimane_lezione FROM anni_scolastici WHERE attivo = 1 LIMIT 1");
    $stmt_weeks->execute();
    $settimane_lezione = (int)($stmt_weeks->fetchColumn() ?: 33);
} catch (Exception $e) {
    $settimane_lezione = 33;
}

// Recupero materie già assegnate
try {
    $stmt = $pdo->prepare("
        SELECT cmd.*, m.nome as materia_nome, m.tipo as materia_tipo,
               CONCAT(d.cognome, ' ', d.nome) as docente_nome,
               d.ore_settimanali_contratto, d.max_ore_settimana
        FROM classi_materie_docenti cmd
        LEFT JOIN materie m ON cmd.materia_id = m.id
        LEFT JOIN docenti d ON cmd.docente_id = d.id
        WHERE cmd.classe_id = ? AND cmd.attivo = 1
        ORDER BY m.nome
    ");
    $stmt->execute([$classe_id]);
    $materie_assegnate = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero materie assegnate: " . $e->getMessage());
    $materie_assegnate = [];
}

// Recupero docenti
try {
    $stmt = $pdo->query("
        SELECT id, cognome, nome, ore_settimanali_contratto, max_ore_settimana
        FROM docenti 
        WHERE stato = 'attivo'
        ORDER BY cognome, nome
    ");
    $docenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero docenti: " . $e->getMessage());
    $docenti = [];
}

// Calcolo ore totali assegnate
$ore_totali_assegnate = 0;
foreach ($materie_assegnate as $assegnazione) {
    $ore_totali_assegnate += $assegnazione['ore_settimanali'];
}

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assegnazioni = $_POST['assegnazioni'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Disattiva tutte le assegnazioni esistenti
        $stmt_disattiva = $pdo->prepare("
            UPDATE classi_materie_docenti 
            SET attivo = 0, updated_at = NOW() 
            WHERE classe_id = ?
        ");
        $stmt_disattiva->execute([$classe_id]);
        
        // Inserisci nuove assegnazioni
        foreach ($assegnazioni as $materia_id => $dati) {
                    if (!empty($dati['docente_id']) && !empty($dati['ore_settimanali'])) {
                $stmt_inserisci = $pdo->prepare("
                    INSERT INTO classi_materie_docenti 
                    (classe_id, materia_id, docente_id, ore_settimanali, ore_annuali_previste, 
                     priorita, note, attivo, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    docente_id = VALUES(docente_id), ore_settimanali = VALUES(ore_settimanali),
                    ore_annuali_previste = VALUES(ore_annuali_previste), priorita = VALUES(priorita),
                    note = VALUES(note), attivo = 1, updated_at = NOW()
                ");
                
                // Usa ore_annuali_previste manuali se specificate, altrimenti usa quelle calcolate
                $ore_sett = intval($dati['ore_settimanali']);
                $ore_annuali_previste = isset($dati['ore_annuali_previste']) && intval($dati['ore_annuali_previste']) ? intval($dati['ore_annuali_previste']) : intval(round($settimane_lezione * $ore_sett));
                
                $stmt_inserisci->execute([
                    $classe_id, $materia_id, $dati['docente_id'], $dati['ore_settimanali'],
                    $ore_annuali_previste, $dati['priorita'] ?? 2, $dati['note'] ?? ''
                ]);
            }
        }
        
        // Log attività
        $sql_log = "INSERT INTO log_attivita (tipo, azione, descrizione, tabella, record_id, utente, ip_address) 
                    VALUES ('classe', 'assegna_materie', 'Assegnate materie alla classe: {$classe['nome']}', 'classi_materie_docenti', ?, ?, ?)";
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute([$classe_id, $_SESSION['username'], $_SERVER['REMOTE_ADDR']]);
        
        $pdo->commit();
        
        header('Location: assegna_materie_classe.php?classe_id=' . $classe_id . '&success=assegnazioni_salvate');
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errore = "Errore nel salvataggio: " . $e->getMessage();
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
                    <h1 class="text-3xl font-bold text-gray-900">Assegna Materie</h1>
                    <p class="text-gray-600 mt-1">Gestisci le materie e i docenti per la classe</p>
                </div>
                <div>
                    <a href="classi.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        <i class="fas fa-arrow-left mr-2"></i>Torna alle Classi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Info Classe -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($classe['nome']); ?></h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Percorso:</span>
                        <span class="font-medium ml-2"><?php echo htmlspecialchars($classe['percorso_nome']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Anno Corso:</span>
                        <span class="font-medium ml-2"><?php echo $classe['anno_corso']; ?>°</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Sede:</span>
                        <span class="font-medium ml-2"><?php echo htmlspecialchars($classe['sede_nome']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Ore Sett. Previste:</span>
                        <span class="font-medium ml-2"><?php echo $classe['ore_settimanali_previste']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($errore)): ?>
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Errore</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p><?php echo htmlspecialchars($errore); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Successo</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>Assegnazioni salvate con successo!</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Riepilogo Ore -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-blue-800">Riepilogo Ore</h3>
                    <p class="text-blue-600 text-sm">Ore assegnate vs ore previste</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-blue-800">
                        <?php echo $ore_totali_assegnate; ?> / <?php echo $classe['ore_settimanali_previste']; ?>
                    </div>
                    <div class="text-sm text-blue-600">
                        <?php 
                        $differenza = $classe['ore_settimanali_previste'] - $ore_totali_assegnate;
                        if ($differenza > 0) {
                            echo "Mancano " . $differenza . " ore";
                        } elseif ($differenza < 0) {
                            echo "Superate di " . abs($differenza) . " ore";
                        } else {
                            echo "Ore complete";
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div class="mt-2 w-full bg-blue-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full" 
                     style="width: <?php echo min(100, ($ore_totali_assegnate / $classe['ore_settimanali_previste']) * 100); ?>%"></div>
            </div>
        </div>

        <!-- Form Assegnazioni -->
        <form method="POST" class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Materie Disponibili</h2>
                <p class="text-gray-600 text-sm mt-1">Seleziona le materie da assegnare e assegna i docenti</p>
            </div>

            <div class="p-6">
                <?php if (empty($materie_disponibili)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-book text-4xl mb-4"></i>
                        <p>Nessuna materia disponibile per questo percorso e anno</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($materie_disponibili as $materia): 
                            // Trova assegnazione esistente
                            $assegnazione = null;
                            foreach ($materie_assegnate as $ass) {
                                if ($ass['materia_id'] == $materia['id']) {
                                    $assegnazione = $ass;
                                    break;
                                }
                            }
                            
                            $colori_tipi = [
                                'culturale' => 'border-blue-200 bg-blue-50',
                                'professionale' => 'border-green-200 bg-green-50',
                                'laboratoriale' => 'border-purple-200 bg-purple-50',
                                'stage' => 'border-yellow-200 bg-yellow-50',
                                'sostegno' => 'border-red-200 bg-red-50'
                            ];
                            $colore = $colori_tipi[$materia['tipo']] ?? 'border-gray-200 bg-gray-50';
                        ?>
                        <div class="border rounded-lg <?php echo $colore; ?> p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($materia['nome']); ?></h3>
                                    <div class="flex items-center space-x-2 mt-1 text-sm text-gray-600">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                            <?php echo $colori_tipi[$materia['tipo']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo ucfirst($materia['tipo']); ?>
                                        </span>
                                        <?php if ($materia['richiede_laboratorio']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                <i class="fas fa-flask mr-1"></i>Laboratorio
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <label class="flex items-center">
                                    <input type="checkbox" name="assegnazioni[<?php echo $materia['id']; ?>][attiva]" 
                                           value="1" <?php echo $assegnazione ? 'checked' : ''; ?>
                                           class="materia-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           data-materia="<?php echo $materia['id']; ?>">
                                    <span class="ml-2 text-sm text-gray-700">Assegna</span>
                                </label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 materia-campi" 
                                 id="campi-<?php echo $materia['id']; ?>" 
                                 style="<?php echo $assegnazione ? '' : 'display: none;'; ?>">
                                
                                <!-- Docente -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Docente</label>
                                    <select name="assegnazioni[<?php echo $materia['id']; ?>][docente_id]"
                                            class="docente-select w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                            data-materia="<?php echo $materia['id']; ?>">
                                        <option value="">Seleziona docente</option>
                                        <?php foreach ($docenti as $docente): 
                                            $ore_assegnate_docente = 0;
                                            foreach ($materie_assegnate as $ass) {
                                                if ($ass['docente_id'] == $docente['id'] && $ass['materia_id'] != $materia['id']) {
                                                    $ore_assegnate_docente += $ass['ore_settimanali'];
                                                }
                                            }
                                        ?>
                                            <option value="<?php echo $docente['id']; ?>" 
                                                    data-ore-contratto="<?php echo $docente['ore_settimanali_contratto']; ?>"
                                                    data-ore-assegnate="<?php echo $ore_assegnate_docente; ?>"
                                                    data-max-ore="<?php echo $docente['max_ore_settimana']; ?>"
                                                    <?php echo ($assegnazione['docente_id'] ?? '') == $docente['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']); ?>
                                                (<?php echo $docente['ore_settimanali_contratto']; ?>h)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Ore Settimanali -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ore Settimanali</label>
                                    <input type="number" name="assegnazioni[<?php echo $materia['id']; ?>][ore_settimanali]"
                                           value="<?php echo $assegnazione['ore_settimanali'] ?? $materia['ore_settimanali']; ?>"
                                           min="1" max="20" step="1"
                                           class="ore-input w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                           data-materia="<?php echo $materia['id']; ?>">
                                </div>
                                
                                <!-- Ore Annuali Previste -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ore Annuali</label>
                                    <input type="number" name="assegnazioni[<?php echo $materia['id']; ?>][ore_annuali_previste]"
                                           value="<?php echo $assegnazione['ore_annuali_previste'] ?? $materia['ore_annuali'] ?? ($settimane_lezione * $materia['ore_settimanali']); ?>"
                                           min="1" class="ore-annuali-input w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                           data-materia="<?php echo $materia['id']; ?>">
                                </div>
                                
                                <!-- Priorità -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Priorità</label>
                                    <select name="assegnazioni[<?php echo $materia['id']; ?>][priorita]"
                                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                        <option value="1" <?php echo ($assegnazione['priorita'] ?? 2) == 1 ? 'selected' : ''; ?>>Alta</option>
                                        <option value="2" <?php echo ($assegnazione['priorita'] ?? 2) == 2 ? 'selected' : ''; ?>>Media</option>
                                        <option value="3" <?php echo ($assegnazione['priorita'] ?? 2) == 3 ? 'selected' : ''; ?>>Bassa</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Note -->
                            <div class="mt-3 materia-campi" id="note-<?php echo $materia['id']; ?>" 
                                 style="<?php echo $assegnazione ? '' : 'display: none;'; ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                                <input type="text" name="assegnazioni[<?php echo $materia['id']; ?>][note]"
                                       value="<?php echo htmlspecialchars($assegnazione['note'] ?? ''); ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                                       placeholder="Note aggiuntive...">
                            </div>
                            
                            <!-- Warning Docente -->
                            <div id="warning-<?php echo $materia['id']; ?>" class="mt-2 hidden">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-2 text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <span id="warning-text-<?php echo $materia['id']; ?>"></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    Totale ore assegnate: <span id="totale-ore" class="font-bold"><?php echo $ore_totali_assegnate; ?></span> / 
                    <?php echo $classe['ore_settimanali_previste']; ?>
                </div>
                <div class="flex space-x-3">
                    <a href="classi.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Annulla
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-save mr-2"></i>Salva Assegnazioni
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
// Toggle campi materia quando checkbox cambia
document.querySelectorAll('.materia-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const materiaId = this.getAttribute('data-materia');
        const campi = document.querySelectorAll(`[id^="campi-${materiaId}"], [id^="note-${materiaId}"]`);
        
        campi.forEach(campo => {
            campo.style.display = this.checked ? 'block' : 'none';
        });
        
        // Se deselezionato, resetta i valori
        if (!this.checked) {
            const inputs = document.querySelectorAll(`[name="assegnazioni[${materiaId}][docente_id]"],
                                                     [name="assegnazioni[${materiaId}][ore_settimanali]"],
                                                     [name="assegnazioni[${materiaId}][priorita]"],
                                                     [name="assegnazioni[${materiaId}][note]"]`);
            inputs.forEach(input => {
                if (input.type === 'select-one') {
                    input.selectedIndex = 0;
                } else {
                    input.value = '';
                }
            });
        }
        
        calcolaTotaleOre();
    });
});

// Calcola totale ore e verifica vincoli
function calcolaTotaleOre() {
    let totale = 0;
    const oreInputs = document.querySelectorAll('.ore-input');
    
    oreInputs.forEach(input => {
        const materiaId = input.getAttribute('data-materia');
        const checkbox = document.querySelector(`.materia-checkbox[data-materia="${materiaId}"]`);
        
        if (checkbox.checked && input.value) {
            totale += parseInt(input.value);
        }
    });
    
    document.getElementById('totale-ore').textContent = totale;
    
    // Verifica superamento ore classe
    const orePreviste = <?php echo $classe['ore_settimanali_previste']; ?>;
    if (totale > orePreviste) {
        document.getElementById('totale-ore').classList.add('text-red-600');
    } else {
        document.getElementById('totale-ore').classList.remove('text-red-600');
    }
}

// Verifica vincoli docente quando cambiano ore o docente
document.querySelectorAll('.ore-input, .docente-select').forEach(element => {
    element.addEventListener('change', function() {
        const materiaId = this.getAttribute('data-materia');
        verificaVincoliDocente(materiaId);
        calcolaTotaleOre();
    });
});

function verificaVincoliDocente(materiaId) {
    const docenteSelect = document.querySelector(`.docente-select[data-materia="${materiaId}"]`);
    const oreInput = document.querySelector(`.ore-input[data-materia="${materiaId}"]`);
    const warningDiv = document.getElementById(`warning-${materiaId}`);
    const warningText = document.getElementById(`warning-text-${materiaId}`);
    
    if (!docenteSelect.value || !oreInput.value) {
        warningDiv.classList.add('hidden');
        return;
    }
    
    const selectedOption = docenteSelect.options[docenteSelect.selectedIndex];
    const oreContratto = parseInt(selectedOption.getAttribute('data-ore-contratto'));
    const oreAssegnate = parseInt(selectedOption.getAttribute('data-ore-assegnate'));
    const maxOre = parseInt(selectedOption.getAttribute('data-max-ore'));
    const nuoveOre = parseInt(oreInput.value);
    
    const totaleDocente = oreAssegnate + nuoveOre;
    
    let warning = '';
    
    if (totaleDocente > maxOre) {
        warning = `Il docente supererebbe il massimo di ${maxOre} ore settimanali (totale: ${totaleDocente}h)`;
    } else if (totaleDocente > oreContratto) {
        warning = `Il docente supererebbe le ore di contratto (${oreContratto}h - totale: ${totaleDocente}h)`;
    }
    
    if (warning) {
        warningText.textContent = warning;
        warningDiv.classList.remove('hidden');
    } else {
        warningDiv.classList.add('hidden');
    }
}

// Inizializza calcolo totale
document.addEventListener('DOMContentLoaded', function() {
    calcolaTotaleOre();
    
    // Verifica vincoli per tutte le materie assegnate
    document.querySelectorAll('.materia-checkbox:checked').forEach(checkbox => {
        const materiaId = checkbox.getAttribute('data-materia');
        verificaVincoliDocente(materiaId);
    });

    // Quando l'input delle ore settimanali cambia, aggiorna ore annuali previste per la materia
    document.querySelectorAll('.ore-input').forEach(input => {
        input.addEventListener('input', function() {
            const materiaId = this.getAttribute('data-materia');
            const oreAnnualiInput = document.querySelector(`.ore-annuali-input[data-materia="${materiaId}"]`);
            const weekly = parseInt(this.value) || 0;
            if (oreAnnualiInput) {
                const computed = Math.round(weekly * <?php echo $settimane_lezione; ?>);
                oreAnnualiInput.value = computed;
            }
            calcolaTotaleOre();
            verificaVincoliDocente(materiaId);
        });
    });

    // Se l'utente modifica ore annuali manualmente, lascia i valori come sono.
    document.querySelectorAll('.ore-annuali-input').forEach(input => {
        input.addEventListener('input', function() {
            const materiaId = this.getAttribute('data-materia');
            // Se necessario, possiamo aggiornare anche l'input settimanale a partire dalle ore annuali.
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>