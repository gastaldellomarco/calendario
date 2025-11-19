<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Verifica che l'utente sia un docente
$docente_id = null;
$user = getCurrentUser();
if ($user && $user['docente_id']) {
    $docente_id = $user['docente_id'];
} else {
    header('Location: stage.php');
    exit;
}

// Recupera stage assegnati al tutor
$stage_assegnati = Database::queryAll("
    SELECT 
        sp.*,
        c.nome as classe_nome,
        p.nome as percorso_nome,
        st.nome_tutor_aziendale,
        st.azienda,
        st.telefono_azienda,
        st.email_azienda
    FROM stage_periodi sp
    JOIN classi c ON sp.classe_id = c.id
    JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
    JOIN stage_tutor st ON sp.id = st.stage_periodo_id
    WHERE st.docente_id = ?
    AND sp.stato != 'cancellato'
    ORDER BY 
        CASE 
            WHEN sp.stato = 'in_corso' THEN 1
            WHEN sp.stato = 'pianificato' THEN 2
            WHEN sp.stato = 'completato' THEN 3
            ELSE 4
        END,
        sp.data_inizio DESC
", [$docente_id]);

// Calcola statistiche personali
$statistiche = Database::queryOne("
    SELECT 
        COUNT(*) as stage_totali,
        COUNT(CASE WHEN sp.stato = 'in_corso' THEN 1 END) as stage_in_corso,
        COUNT(CASE WHEN sp.stato = 'completato' THEN 1 END) as stage_completati,
        SUM(sp.ore_effettuate) as ore_totali_seguite
    FROM stage_periodi sp
    JOIN stage_tutor st ON sp.id = st.stage_periodo_id
    WHERE st.docente_id = ?
    AND sp.stato != 'cancellato'
", [$docente_id]);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Tutor Stage - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard Tutor Stage</h1>
            <p class="text-gray-600 mt-2">Gestisci gli stage assegnati come tutor scolastico</p>
        </div>

        <!-- Statistiche Personali -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-calendar-check text-blue-600"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo $statistiche['stage_totali']; ?></div>
                        <div class="text-sm text-gray-600">Stage Assegnati</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-play-circle text-green-600"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo $statistiche['stage_in_corso']; ?></div>
                        <div class="text-sm text-gray-600">In Corso</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-check-circle text-yellow-600"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo $statistiche['stage_completati']; ?></div>
                        <div class="text-sm text-gray-600">Completati</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-clock text-purple-600"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo $statistiche['ore_totali_seguite'] ?: 0; ?></div>
                        <div class="text-sm text-gray-600">Ore Totali</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stage Assegnati -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Stage Assegnati</h2>
            </div>
            
            <div class="divide-y divide-gray-200">
                <?php foreach ($stage_assegnati as $stage): 
                    $progresso = $stage['ore_totali_previste'] > 0 ? 
                        round(($stage['ore_effettuate'] / $stage['ore_totali_previste']) * 100, 1) : 0;
                    
                    $stato_colors = [
                        'pianificato' => 'bg-yellow-100 text-yellow-800',
                        'in_corso' => 'bg-blue-100 text-blue-800',
                        'completato' => 'bg-green-100 text-green-800'
                    ];
                ?>
                <div class="p-6 hover:bg-gray-50">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex-1">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($stage['classe_nome']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm">
                                        <?php echo date('d/m/Y', strtotime($stage['data_inizio'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($stage['data_fine'])); ?>
                                    </p>
                                </div>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $stato_colors[$stage['stato']]; ?>">
                                    <?php echo ucfirst($stage['stato']); ?>
                                </span>
                            </div>
                            
                            <?php if ($stage['azienda']): ?>
                            <div class="flex items-center text-sm text-gray-600 mb-2">
                                <i class="fas fa-building mr-2"></i>
                                <span><?php echo htmlspecialchars($stage['azienda']); ?></span>
                                <?php if ($stage['nome_tutor_aziendale']): ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-user-tie mr-1"></i>
                                    <span><?php echo htmlspecialchars($stage['nome_tutor_aziendale']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center text-sm text-gray-600 mb-3">
                                <i class="fas fa-clock mr-2"></i>
                                <span><?php echo $stage['ore_effettuate']; ?> / <?php echo $stage['ore_totali_previste']; ?> ore</span>
                            </div>
                            
                            <!-- Barra Progresso -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-medium text-gray-700">Progresso</span>
                                    <span class="text-sm font-bold text-blue-600"><?php echo $progresso; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                         style="width: <?php echo min($progresso, 100); ?>%"></div>
                                </div>
                            </div>
                            
                            <?php if ($stage['descrizione']): ?>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($stage['descrizione']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="lg:ml-6 lg:pl-6 lg:border-l lg:border-gray-200">
                            <div class="flex flex-col space-y-2">
                                <a href="stage_dettaglio.php?id=<?php echo $stage['id']; ?>" 
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm text-center flex items-center justify-center">
                                    <i class="fas fa-eye mr-2"></i> Dettaglio
                                </a>
                                
                                <?php if ($stage['stato'] == 'in_corso'): ?>
                                <button onclick="apriModalRegistraOre(<?php echo $stage['id']; ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center">
                                    <i class="fas fa-plus mr-2"></i> Registra Ore
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($stage['telefono_azienda'] || $stage['email_azienda']): ?>
                                <button onclick="apriModalContatti(<?php echo $stage['id']; ?>)" 
                                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center">
                                    <i class="fas fa-phone mr-2"></i> Contatti
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($stage['stato'] == 'in_corso'): ?>
                                <button onclick="completaStage(<?php echo $stage['id']; ?>)" 
                                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center">
                                    <i class="fas fa-check mr-2"></i> Completa
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($stage_assegnati)): ?>
                <div class="text-center py-12 text-gray-500">
                    <i class="fas fa-user-tie text-4xl mb-3"></i>
                    <p class="text-lg">Nessuno stage assegnato</p>
                    <p class="text-sm">Non sei stato assegnato come tutor per nessuno stage.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Registra Ore Rapido -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <div class="bg-blue-100 p-3 rounded-lg inline-flex mb-4">
                        <i class="fas fa-clock text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Registra Ore</h3>
                    <p class="text-sm text-gray-600 mb-4">Registra rapidamente le ore di stage</p>
                    <button onclick="apriModalRegistraOreRapido()" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg text-sm">
                        Nuova Registrazione
                    </button>
                </div>
            </div>

            <!-- Documenti -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <div class="bg-green-100 p-3 rounded-lg inline-flex mb-4">
                        <i class="fas fa-file-alt text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Documenti</h3>
                    <p class="text-sm text-gray-600 mb-4">Carica convenzioni e registri</p>
                    <button onclick="apriModalUploadDocumento()" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg text-sm">
                        Carica Documento
                    </button>
                </div>
            </div>

            <!-- Visite Aziendali -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <div class="bg-purple-100 p-3 rounded-lg inline-flex mb-4">
                        <i class="fas fa-building text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Visite</h3>
                    <p class="text-sm text-gray-600 mb-4">Pianifica visite aziendali</p>
                    <button onclick="apriModalVisita()" 
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg text-sm">
                        Nuova Visita
                    </button>
                </div>
            </div>

            <!-- Report -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <div class="bg-yellow-100 p-3 rounded-lg inline-flex mb-4">
                        <i class="fas fa-chart-bar text-yellow-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Report</h3>
                    <p class="text-sm text-gray-600 mb-4">Genera report di monitoraggio</p>
                    <button onclick="generaReport()" 
                            class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg text-sm">
                        Genera Report
                    </button>
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
                <input type="hidden" name="stage_id" id="stage_id_registra">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input type="date" name="data" required 
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
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
                        <textarea name="note" rows="3" placeholder="Note sulla giornata di stage..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
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

    <!-- Modal Contatti -->
    <div id="modalContatti" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-semibold mb-4">Contatti Azienda</h3>
            <div id="dettagliContatti" class="space-y-3">
                <!-- I dettagli verranno caricati via JavaScript -->
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="chiudiModalContatti()" 
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                    Chiudi
                </button>
            </div>
        </div>
    </div>

    <script>
        function apriModalRegistraOre(stage_id) {
            document.getElementById('stage_id_registra').value = stage_id;
            document.getElementById('modalRegistraOre').classList.remove('hidden');
        }

        function chiudiModalRegistraOre() {
            document.getElementById('modalRegistraOre').classList.add('hidden');
        }

        function apriModalRegistraOreRapido() {
            // Implementa registrazione ore rapida per stage in corso
            const stageInCorso = <?php echo json_encode(array_filter($stage_assegnati, function($s) { 
                return $s['stato'] == 'in_corso'; 
            })); ?>;
            
            if (stageInCorso.length === 0) {
                alert('Nessuno stage in corso trovato');
                return;
            }
            
            if (stageInCorso.length === 1) {
                apriModalRegistraOre(stageInCorso[0].id);
            } else {
                // Se ci sono più stage in corso, mostra selezione
                let options = stageInCorso.map(s => 
                    `<option value="${s.id}">${s.classe_nome} - ${s.azienda || 'Nessuna azienda'}</option>`
                ).join('');
                
                const selection = prompt(`Seleziona stage:\n${options}`);
                if (selection) {
                    apriModalRegistraOre(parseInt(selection));
                }
            }
        }

        function apriModalContatti(stage_id) {
            const stage = <?php echo json_encode($stage_assegnati); ?>.find(s => s.id == stage_id);
            if (!stage) return;
            
            let html = '';
            
            if (stage.nome_tutor_aziendale) {
                html += `<div class="flex items-center">
                    <i class="fas fa-user-tie text-blue-600 mr-3"></i>
                    <div>
                        <div class="font-medium">${stage.nome_tutor_aziendale}</div>
                        <div class="text-sm text-gray-600">Tutor aziendale</div>
                    </div>
                </div>`;
            }
            
            if (stage.azienda) {
                html += `<div class="flex items-center">
                    <i class="fas fa-building text-green-600 mr-3"></i>
                    <div>
                        <div class="font-medium">${stage.azienda}</div>
                        <div class="text-sm text-gray-600">Azienda</div>
                    </div>
                </div>`;
            }
            
            if (stage.telefono_azienda) {
                html += `<div class="flex items-center">
                    <i class="fas fa-phone text-purple-600 mr-3"></i>
                    <div>
                        <div class="font-medium">${stage.telefono_azienda}</div>
                        <div class="text-sm text-gray-600">Telefono</div>
                    </div>
                </div>`;
            }
            
            if (stage.email_azienda) {
                html += `<div class="flex items-center">
                    <i class="fas fa-envelope text-yellow-600 mr-3"></i>
                    <div>
                        <div class="font-medium">${stage.email_azienda}</div>
                        <div class="text-sm text-gray-600">Email</div>
                    </div>
                </div>`;
            }
            
            document.getElementById('dettagliContatti').innerHTML = html;
            document.getElementById('modalContatti').classList.remove('hidden');
        }

        function chiudiModalContatti() {
            document.getElementById('modalContatti').classList.add('hidden');
        }

        function completaStage(stage_id) {
            if (confirm('Sei sicuro di voler segnare questo stage come completato?')) {
                window.location.href = `../api/stage_api.php?action=completa_stage&stage_id=${stage_id}`;
            }
        }

        function apriModalUploadDocumento() {
            // Implementa modal upload documento
            alert('Modal upload documento - da implementare');
        }

        function apriModalVisita() {
            // Implementa modal visita aziendale
            alert('Modal visita aziendale - da implementare');
        }

        function generaReport() {
            // Implementa generazione report
            alert('Generazione report - da implementare');
        }

        // Gestione submit form registra ore
        document.getElementById('formRegistraOre').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../api/stage_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ore registrate con successo!');
                    chiudiModalRegistraOre();
                    location.reload();
                } else {
                    alert('Errore: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante la registrazione');
            });
        });

        // Chiudi modal cliccando fuori
        document.querySelectorAll('.fixed').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>