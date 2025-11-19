<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$stage_id = $_GET['id'] ?? null;
if (!$stage_id) {
    header('Location: stage.php');
    exit;
}

try {
    $stage = Database::queryOne("
    SELECT 
        sp.*,
        c.nome as classe_nome,
        p.nome as percorso_nome,
        c.anno_scolastico_id
    FROM stage_periodi sp
    JOIN classi c ON sp.classe_id = c.id
    JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
    WHERE sp.id = ?
", [$stage_id]);
} catch (Exception $e) {
    error_log('Stage detail DB error: ' . $e->getMessage());
    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo "<div style=\"padding:10px;border:1px solid #f00;background:#fee;\"><strong>DB error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    // Redirect back to list
    header('Location: stage.php');
    exit;
}

if (!$stage) {
    header('Location: stage.php');
    exit;
}

$tutor = null;
try {
    $tutor = Database::queryOne("
        SELECT st.*, CONCAT(d.cognome, ' ', d.nome) as tutor_scolastico_nome
        FROM stage_tutor st
        LEFT JOIN docenti d ON st.docente_id = d.id
        WHERE st.stage_periodo_id = ?
    ", [$stage_id]);
} catch (Exception $e) {
    error_log('Stage detail tutor DB error: ' . $e->getMessage());
    $tutor = null;
}

// Calcola progresso
$progresso = $stage['ore_totali_previste'] > 0 ? 
    round(($stage['ore_effettuate'] / $stage['ore_totali_previste']) * 100, 1) : 0;

// Recupera giorni stage
// Recupera giorni stage (fetch is wrapped below in try/catch to handle DB errors)
$giorni_stage = [];
try {
    $giorni_stage = Database::queryAll("
    SELECT id, data, ore_effettuate, note, presenza
    FROM stage_giorni 
    WHERE stage_periodo_id = ?
    ORDER BY data
    ", [$stage_id]);
} catch (Exception $e) {
    error_log('Stage detail giorni DB error: ' . $e->getMessage());
    $giorni_stage = [];
}

// Recupera documenti
$documenti = [];
try {
    $documenti = Database::queryAll("
    SELECT * FROM stage_documenti
    WHERE stage_periodo_id = ?
    ORDER BY tipo_documento, created_at DESC
    ", [$stage_id]);
} catch (Exception $e) {
    error_log('Stage detail documenti DB error: ' . $e->getMessage());
    $documenti = [];
}

// Log attività
$log_attivita = [];
try {
    $log_attivita = Database::queryAll("
        SELECT * FROM log_attivita 
        WHERE tabella = 'stage_periodi' AND record_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ", [$stage_id]);
} catch (Exception $e) {
    error_log('Stage detail log DB error: ' . $e->getMessage());
    $log_attivita = [];
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Stage - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Stage - <?php echo htmlspecialchars($stage['classe_nome']); ?></h1>
                <p class="text-gray-600 mt-2">
                    <?php echo date('d/m/Y', strtotime($stage['data_inizio'])); ?> - 
                    <?php echo date('d/m/Y', strtotime($stage['data_fine'])); ?>
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="stage_form.php?id=<?php echo $stage_id; ?>" 
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-edit mr-2"></i> Modifica
                </a>
                <a href="stage.php" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Torna alla lista
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Colonna Sinistra - Informazioni -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Card Informazioni Generali -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Informazioni Generali</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Classe</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($stage['classe_nome']); ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Percorso Formativo</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($stage['percorso_nome']); ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Periodo</label>
                            <p class="text-gray-900">
                                <?php echo date('d/m/Y', strtotime($stage['data_inizio'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($stage['data_fine'])); ?>
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Stato</label>
                            <?php
                            $stato_colors = [
                                'pianificato' => 'bg-yellow-100 text-yellow-800',
                                'in_corso' => 'bg-blue-100 text-blue-800',
                                'completato' => 'bg-green-100 text-green-800'
                            ];
                            ?>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $stato_colors[$stage['stato']]; ?>">
                                <?php echo ucfirst($stage['stato']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($stage['descrizione']): ?>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-500">Descrizione</label>
                        <p class="text-gray-900 mt-1"><?php echo nl2br(htmlspecialchars($stage['descrizione'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($stage['note']): ?>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-500">Note</label>
                        <p class="text-gray-900 mt-1"><?php echo nl2br(htmlspecialchars($stage['note'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Card Progresso Ore -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Progresso Ore</h2>
                    
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Ore completate</span>
                            <span class="text-sm font-bold text-blue-600"><?php echo $progresso; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div class="bg-blue-600 h-4 rounded-full transition-all duration-300" 
                                 style="width: <?php echo min($progresso, 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600"><?php echo $stage['ore_effettuate']; ?></div>
                            <div class="text-sm text-blue-500">Ore Effettuate</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-gray-600"><?php echo $stage['ore_totali_previste']; ?></div>
                            <div class="text-sm text-gray-500">Ore Previste</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                <?php echo max(0, $stage['ore_totali_previste'] - $stage['ore_effettuate']); ?>
                            </div>
                            <div class="text-sm text-green-500">Ore Rimanenti</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600"><?php echo count($giorni_stage); ?></div>
                            <div class="text-sm text-purple-500">Giorni Registrati</div>
                        </div>
                    </div>
                </div>

                <!-- Card Registro Presenze -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Registro Presenze</h2>
                        <button onclick="apriModalRegistraOre()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm flex items-center">
                            <i class="fas fa-plus mr-1"></i> Nuova Registrazione
                        </button>
                    </div>
                    
                    <?php if (!empty($giorni_stage)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Data</th>
                                    <th class="px-4 py-2 text-left">Presenza</th>
                                    <th class="px-4 py-2 text-left">Ore</th>
                                    <th class="px-4 py-2 text-left">Note</th>
                                    <th class="px-4 py-2 text-left">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($giorni_stage as $giorno): ?>
                                <tr>
                                    <td class="px-4 py-2"><?php echo date('d/m/Y', strtotime($giorno['data'])); ?></td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $giorno['presenza'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $giorno['presenza'] ? 'Presente' : 'Assente'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 font-medium"><?php echo $giorno['ore_effettuate']; ?>h</td>
                                    <td class="px-4 py-2 text-gray-600"><?php echo htmlspecialchars($giorno['note']); ?></td>
                                    <td class="px-4 py-2">
                                        <button onclick="modificaGiorno(<?php echo $giorno['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900 text-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-calendar-times text-4xl mb-3"></i>
                        <p>Nessuna registrazione di presenza ancora inserita.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Colonna Destra - Sidebar -->
            <div class="space-y-6">
                <!-- Card Tutor -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Tutor</h2>
                    
                    <?php if ($tutor): ?>
                        <?php if ($tutor['tutor_scolastico_nome']): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-500">Tutor Scolastico</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($tutor['tutor_scolastico_nome']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tutor['nome_tutor_aziendale']): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-500">Tutor Aziendale</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($tutor['nome_tutor_aziendale']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tutor['azienda']): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-500">Azienda</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($tutor['azienda']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tutor['telefono_azienda']): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-500">Telefono</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($tutor['telefono_azienda']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tutor['email_azienda']): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-500">Email</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($tutor['email_azienda']); ?></p>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">Nessun tutor assegnato</p>
                    <?php endif; ?>
                </div>

                <!-- Card Documenti -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Documenti</h2>
                        <button onclick="apriModalUploadDocumento()" 
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm flex items-center">
                            <i class="fas fa-upload mr-1"></i> Carica
                        </button>
                    </div>
                    
                    <?php if (!empty($documenti)): ?>
                    <div class="space-y-2">
                        <?php foreach ($documenti as $doc): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['nome_file']); ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo ucfirst($doc['tipo_documento']); ?> - 
                                    <?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?>
                                </div>
                            </div>
                            <a href="../uploads/stage/<?php echo $doc['nome_file']; ?>" 
                               class="text-blue-600 hover:text-blue-900" download>
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-file-alt text-3xl mb-2"></i>
                        <p>Nessun documento caricato</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Card Azioni Rapide -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Azioni Rapide</h2>
                    
                    <div class="space-y-3">
                        <a href="stage_calendario_blocco.php?stage_id=<?php echo $stage_id; ?>" 
                           class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-alt mr-2"></i> Gestisci Calendario
                        </a>
                        
                        <?php if ($stage['stato'] == 'in_corso'): ?>
                        <button onclick="completaStage()" 
                                class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check mr-2"></i> Completa Stage
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($stage['stato'] == 'pianificato'): ?>
                        <button onclick="cancellaStage()" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i> Cancella Stage
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Registra Ore -->
    <div id="modalRegistraOre" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-semibold mb-4">Registra Ore Stage</h3>
            <form id="formRegistraOre" method="POST" action="../api/stage_api.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="registra_presenza_giorno">
                <input type="hidden" name="stage_id" value="<?php echo $stage_id; ?>">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input type="date" name="data" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ore Effettuate</label>
                        <input type="number" name="ore_effettuate" min="0" max="8" step="0.5" required 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="presenza" id="presenza" checked class="mr-2">
                        <label for="presenza" class="text-sm text-gray-700">Studente presente</label>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="chiudiModalRegistraOre()" 
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                        Annulla
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        Salva
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function apriModalRegistraOre() {
            document.getElementById('modalRegistraOre').classList.remove('hidden');
        }

        function chiudiModalRegistraOre() {
            document.getElementById('modalRegistraOre').classList.add('hidden');
        }

        function modificaGiorno(giorno_id) {
            // Implementa modifica giorno esistente
            alert('Modifica giorno: ' + giorno_id);
        }

        function completaStage() {
            if (confirm('Sei sicuro di voler segnare questo stage come completato?')) {
                // Implementa completamento stage
                window.location.href = '../api/stage_api.php?action=completa_stage&stage_id=<?php echo $stage_id; ?>';
            }
        }

        function cancellaStage() {
            if (confirm('Sei sicuro di voler cancellare questo stage?')) {
                // Implementa cancellazione stage
                window.location.href = '../api/stage_api.php?action=delete_stage&stage_id=<?php echo $stage_id; ?>';
            }
        }

        function apriModalUploadDocumento() {
            // Implementa modal upload documento
            alert('Apri modal upload documento');
        }

        // Chiudi modal cliccando fuori
        document.getElementById('modalRegistraOre').addEventListener('click', function(e) {
            if (e.target === this) {
                chiudiModalRegistraOre();
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>