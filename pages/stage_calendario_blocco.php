<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$stage_id = $_GET['stage_id'] ?? null;
if (!$stage_id) {
    header('Location: stage.php');
    exit;
}

$stage = Database::queryOne("
    SELECT sp.*, c.nome as classe_nome
    FROM stage_periodi sp
    JOIN classi c ON sp.classe_id = c.id
    WHERE sp.id = ?
", [$stage_id]);

if (!$stage) {
    header('Location: stage.php');
    exit;
}

// Recupera lezioni in conflitto
$lezioni_conflitto = Database::queryAll("
    SELECT 
        cl.*,
        m.nome as materia_nome,
        CONCAT(d.cognome, ' ', d.nome) as docente_nome,
        a.nome as aula_nome,
        os.ora_inizio, os.ora_fine
    FROM calendario_lezioni cl
    JOIN materie m ON cl.materia_id = m.id
    JOIN docenti d ON cl.docente_id = d.id
    LEFT JOIN aule a ON cl.aula_id = a.id
    JOIN orari_slot os ON cl.slot_id = os.id
    WHERE cl.classe_id = ?
    AND cl.data_lezione BETWEEN ? AND ?
    AND cl.stato != 'cancellata'
    ORDER BY cl.data_lezione, os.ora_inizio
", [$stage['classe_id'], $stage['data_inizio'], $stage['data_fine']]);

// Raggruppa lezioni per data
$lezioni_per_data = [];
foreach ($lezioni_conflitto as $lezione) {
    $data = $lezione['data_lezione'];
    if (!isset($lezioni_per_data[$data])) {
        $lezioni_per_data[$data] = [];
    }
    $lezioni_per_data[$data][] = $lezione;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Calendario Stage - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Gestione Calendario Stage</h1>
                    <p class="text-gray-600 mt-2">
                        Stage: <?php echo htmlspecialchars($stage['classe_nome']); ?> - 
                        <?php echo date('d/m/Y', strtotime($stage['data_inizio'])); ?> - 
                        <?php echo date('d/m/Y', strtotime($stage['data_fine'])); ?>
                    </p>
                </div>
                <a href="stage_dettaglio.php?id=<?php echo $stage_id; ?>" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Torna allo Stage
                </a>
            </div>
        </div>

        <!-- Riepilogo Conflitti -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Conflitti con Calendario</h2>
                <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                    <?php echo count($lezioni_conflitto); ?> lezioni in conflitto
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600"><?php echo count($lezioni_per_data); ?></div>
                    <div class="text-sm text-blue-500">Giornate con conflitti</div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">
                        <?php 
                        $ore_totali = array_sum(array_column($lezioni_conflitto, 'ore_effettive'));
                        echo $ore_totali;
                        ?>
                    </div>
                    <div class="text-sm text-yellow-500">Ore totali in conflitto</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">
                        <?php echo count(array_unique(array_column($lezioni_conflitto, 'docente_id'))); ?>
                    </div>
                    <div class="text-sm text-green-500">Docenti coinvolti</div>
                </div>
            </div>
        </div>

        <!-- Opzioni di Gestione -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Opzioni di Gestione</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Opzione 1: Cancella Tutto -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start mb-3">
                        <div class="bg-red-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-trash text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Cancella Tutte le Lezioni</h3>
                            <p class="text-sm text-gray-600 mt-1">Elimina tutte le lezioni nel periodo di stage</p>
                        </div>
                    </div>
                    <button onclick="cancellaTutteLezioni()" 
                            class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg text-sm">
                        Cancella <?php echo count($lezioni_conflitto); ?> Lezioni
                    </button>
                </div>

                <!-- Opzione 2: Sposta Dopo Stage -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start mb-3">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-arrow-right text-blue-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Sposta Dopo Stage</h3>
                            <p class="text-sm text-gray-600 mt-1">Sposta le lezioni dopo la fine dello stage</p>
                        </div>
                    </div>
                    <button onclick="spostaDopoStage()" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg text-sm">
                        Sposta Lezioni
                    </button>
                </div>

                <!-- Opzione 3: Gestione Manuale -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start mb-3">
                        <div class="bg-green-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-edit text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Gestione Manuale</h3>
                            <p class="text-sm text-gray-600 mt-1">Gestisci manualmente ogni lezione</p>
                        </div>
                    </div>
                    <button onclick="mostraGestioneManuale()" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg text-sm">
                        Gestisci Manualmente
                    </button>
                </div>
            </div>
        </div>

        <!-- Dettaglio Lezioni in Conflitto -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Dettaglio Lezioni in Conflitto</h2>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($lezioni_per_data)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-check-circle text-4xl mb-3 text-green-500"></i>
                        <p class="text-lg">Nessuna lezione in conflitto trovata!</p>
                        <p class="text-sm">Il calendario è già libero per il periodo di stage.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lezioni_per_data as $data => $lezioni_giorno): ?>
                    <div class="border-b border-gray-200 last:border-b-0">
                        <div class="bg-gray-50 px-6 py-3">
                            <h3 class="font-semibold text-gray-800">
                                <?php echo date('l d/m/Y', strtotime($data)); ?>
                                <span class="text-sm font-normal text-gray-600 ml-2">
                                    (<?php echo count($lezioni_giorno); ?> lezioni)
                                </span>
                            </h3>
                        </div>
                        
                        <div class="px-6 py-4">
                            <div class="space-y-3">
                                <?php foreach ($lezioni_giorno as $lezione): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-4">
                                        <input type="checkbox" 
                                               name="lezioni_selezionate[]" 
                                               value="<?php echo $lezione['id']; ?>"
                                               class="lezione-checkbox rounded border-gray-300">
                                        
                                        <div class="w-16 text-sm font-medium text-gray-900">
                                            <?php echo substr($lezione['ora_inizio'], 0, 5); ?>
                                        </div>
                                        
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($lezione['materia_nome']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <?php echo htmlspecialchars($lezione['docente_nome']); ?>
                                                <?php if ($lezione['aula_nome']): ?>
                                                    - <?php echo htmlspecialchars($lezione['aula_nome']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                            <?php echo $lezione['ore_effettive']; ?>h
                                        </span>
                                        <div class="flex space-x-1">
                                            <button onclick="spostaLezione(<?php echo $lezione['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 p-1"
                                                    title="Sposta lezione">
                                                <i class="fas fa-arrow-right"></i>
                                            </button>
                                            <button onclick="cancellaLezione(<?php echo $lezione['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900 p-1"
                                                    title="Cancella lezione">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Azioni per giorno -->
                            <div class="flex justify-end space-x-2 mt-3">
                                <button onclick="cancellaGiorno('<?php echo $data; ?>')" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                    Cancella Giornata
                                </button>
                                <button onclick="spostaGiorno('<?php echo $data; ?>')" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                    Sposta Giornata
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Azioni Globali -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <input type="checkbox" id="selezionaTutto" class="rounded border-gray-300">
                                <label for="selezionaTutto" class="text-sm text-gray-700">Seleziona tutto</label>
                                <span class="text-sm text-gray-600" id="contatoreSelezionate">0 lezioni selezionate</span>
                            </div>
                            
                            <div class="flex space-x-3">
                                <button onclick="cancellaSelezionate()" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Cancella Selezionate
                                </button>
                                <button onclick="spostaSelezionate()" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Sposta Selezionate
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Sposta Lezioni -->
    <div id="modalSposta" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-semibold mb-4">Sposta Lezioni</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nuova Data</label>
                    <input type="date" id="nuovaData" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slot Orario</label>
                    <select id="nuovoSlot" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Mantieni slot originale</option>
                        <!-- Gli slot verrebbero caricati via AJAX -->
                    </select>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                        <span class="text-sm text-yellow-700">Verifica che non ci siano conflitti nella nuova data</span>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="chiudiModalSposta()" 
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                    Annulla
                </button>
                <button type="button" onclick="confermaSposta()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Sposta Lezioni
                </button>
            </div>
        </div>
    </div>

    <script>
        // Gestione selezione lezioni
        document.getElementById('selezionaTutto').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.lezione-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            aggiornaContatore();
        });

        function aggiornaContatore() {
            const selezionate = document.querySelectorAll('.lezione-checkbox:checked').length;
            document.getElementById('contatoreSelezionate').textContent = 
                selezionate + ' lezioni selezionate';
        }

        // Aggiungi event listener a tutti i checkbox
        document.querySelectorAll('.lezione-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', aggiornaContatore);
        });

        // Funzioni azioni di massa
        function cancellaTutteLezioni() {
            if (confirm(`Sei sicuro di voler cancellare tutte le ${<?php echo count($lezioni_conflitto); ?>} lezioni?`)) {
                eseguiAzioneMassa('cancella', 'tutte');
            }
        }

        function spostaDopoStage() {
            apriModalSposta();
        }

        function mostraGestioneManuale() {
            // Implementa gestione manuale
            alert('Modalità gestione manuale - implementa interfaccia drag & drop');
        }

        function cancellaGiorno(data) {
            if (confirm('Sei sicuro di voler cancellare tutte le lezioni di questa giornata?')) {
                eseguiAzioneMassa('cancella', 'giorno', data);
            }
        }

        function spostaGiorno(data) {
            // Implementa spostamento giornata
            alert('Sposta giornata: ' + data);
        }

        function cancellaLezione(lezione_id) {
            if (confirm('Sei sicuro di voler cancellare questa lezione?')) {
                eseguiAzioneSingola('cancella', lezione_id);
            }
        }

        function spostaLezione(lezione_id) {
            // Implementa spostamento singola lezione
            alert('Sposta lezione: ' + lezione_id);
        }

        function cancellaSelezionate() {
            const selezionate = getLezioniSelezionate();
            if (selezionate.length === 0) {
                alert('Seleziona almeno una lezione');
                return;
            }
            
            if (confirm(`Sei sicuro di voler cancellare ${selezionate.length} lezioni?`)) {
                eseguiAzioneMassa('cancella', 'selezionate', selezionate);
            }
        }

        function spostaSelezionate() {
            const selezionate = getLezioniSelezionate();
            if (selezionate.length === 0) {
                alert('Seleziona almeno una lezione');
                return;
            }
            
            apriModalSposta(selezionate);
        }

        function getLezioniSelezionate() {
            const checkboxes = document.querySelectorAll('.lezione-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function apriModalSposta(lezioni_ids = null) {
            document.getElementById('modalSposta').classList.remove('hidden');
            // Qui potresti pre-caricare dati per lo spostamento
        }

        function chiudiModalSposta() {
            document.getElementById('modalSposta').classList.add('hidden');
        }

        function confermaSposta() {
            const nuovaData = document.getElementById('nuovaData').value;
            const nuovoSlot = document.getElementById('nuovoSlot').value;
            
            if (!nuovaData) {
                alert('Seleziona una data');
                return;
            }
            
            const selezionate = getLezioniSelezionate();
            eseguiAzioneMassa('sposta', 'selezionate', selezionate, { data: nuovaData, slot: nuovoSlot });
        }

        function eseguiAzioneSingola(azione, lezione_id, parametri = {}) {
            // Implementa chiamata API per azione singola
            console.log('Azione singola:', azione, lezione_id, parametri);
        }

        function eseguiAzioneMassa(azione, tipo, parametri = {}, opzioni = {}) {
            // Implementa chiamata API per azioni di massa
            console.log('Azione massa:', azione, tipo, parametri, opzioni);
            
            // Esempio implementazione
            fetch('../api/stage_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'blocca_calendario_stage',
                    stage_id: <?php echo $stage_id; ?>,
                    azione: azione,
                    tipo: tipo,
                    parametri: parametri,
                    opzioni: opzioni
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operazione completata con successo!');
                    location.reload();
                } else {
                    alert('Errore: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'operazione');
            });
        }

        // Chiudi modal cliccando fuori
        document.getElementById('modalSposta').addEventListener('click', function(e) {
            if (e.target === this) {
                chiudiModalSposta();
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>