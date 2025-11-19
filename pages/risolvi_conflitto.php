<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/NotificheManager.php';
require_once __DIR__ . '/../algorithm/SuggerimentiRisoluzione.php';

// Solo admin e preside possono accedere
checkRole(['amministratore', 'preside']);

$page_title = "Risolvi Conflitto";
$current_page = 'conflitti';

$conflitto_id = intval($_GET['id'] ?? 0);

if ($conflitto_id <= 0) {
    header('Location: conflitti.php');
    exit;
}

// Ottieni dettagli conflitto
$sql = "SELECT c.*, 
               d.id as docente_id, d.cognome as docente_cognome, d.nome as docente_nome,
               cl.id as classe_id, cl.nome as classe_nome,
               a.id as aula_id, a.nome as aula_nome,
               m.id as materia_id, m.nome as materia_nome,
               l.id as lezione_id, l.data_lezione, l.slot_id,
               os.ora_inizio, os.ora_fine
        FROM conflitti_orario c
        LEFT JOIN docenti d ON c.docente_id = d.id
        LEFT JOIN classi cl ON c.classe_id = cl.id
        LEFT JOIN aule a ON c.aula_id = a.id
        LEFT JOIN calendario_lezioni l ON c.lezione_id = l.id
        LEFT JOIN materie m ON l.materia_id = m.id
        LEFT JOIN orari_slot os ON l.slot_id = os.id
        WHERE c.id = ?";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([$conflitto_id]);
$conflitto = $stmt->fetch();

if (!$conflitto) {
    header('Location: conflitti.php');
    exit;
}

// Inizializza gestore suggerimenti
$suggerimentiManager = new SuggerimentiRisoluzione($pdo);

// Ottieni suggerimenti in base al tipo di conflitto
$suggerimenti = [];
if ($conflitto['lezione_id']) {
    switch ($conflitto['tipo']) {
        case 'doppia_assegnazione_docente':
        case 'doppia_aula':
        case 'vincolo_docente':
            $suggerimenti['slot_alternativi'] = $suggerimentiManager->suggerisciSlotAlternativi($conflitto['lezione_id'], 5);
            $suggerimenti['docenti_alternativi'] = $suggerimentiManager->suggerisciDocentiAlternativi($conflitto['materia_id'], $conflitto['data_lezione'], $conflitto['slot_id']);
            break;
            
        case 'aula_non_adeguata':
            $suggerimenti['aule_alternative'] = $suggerimentiManager->suggerisciAuleAlternative(null, $conflitto['data_lezione'], $conflitto['slot_id'], $conflitto['sede_id'] ?? 1);
            break;
    }
}

include '../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Risolvi Conflitto
            </h1>
            <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                        <?php echo [
                            'critico' => 'bg-red-100 text-red-800',
                            'error' => 'bg-orange-100 text-orange-800',
                            'warning' => 'bg-yellow-100 text-yellow-800'
                        ][$conflitto['gravita']] ?? 'bg-gray-100 text-gray-800'; ?>">
                        <?php echo ucfirst($conflitto['gravita']); ?>
                    </span>
                </div>
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    <i class="fas fa-calendar-day mr-1"></i>
                    <?php echo date('d/m/Y', strtotime($conflitto['data_conflitto'])); ?>
                </div>
            </div>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
            <a href="conflitti.php" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>Torna ai conflitti
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Dettagli Conflitto -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Dettagli Conflitto
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                            <dd class="mt-1 text-sm text-gray-900 capitalize">
                                <?php echo str_replace('_', ' ', $conflitto['tipo']); ?>
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Gravità</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo [
                                        'critico' => 'bg-red-100 text-red-800',
                                        'error' => 'bg-orange-100 text-orange-800',
                                        'warning' => 'bg-yellow-100 text-yellow-800'
                                    ][$conflitto['gravita']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo ucfirst($conflitto['gravita']); ?>
                                </span>
                            </dd>
                        </div>
                        
                        <?php if ($conflitto['docente_cognome']): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Docente</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <?php echo htmlspecialchars($conflitto['docente_cognome'] . ' ' . $conflitto['docente_nome']); ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($conflitto['classe_nome']): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Classe</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <?php echo htmlspecialchars($conflitto['classe_nome']); ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($conflitto['aula_nome']): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Aula</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <?php echo htmlspecialchars($conflitto['aula_nome']); ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($conflitto['data_lezione']): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Data Lezione</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <?php echo date('d/m/Y', strtotime($conflitto['data_lezione'])); ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($conflitto['ora_inizio']): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Orario</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <?php echo date('H:i', strtotime($conflitto['ora_inizio'])) . ' - ' . date('H:i', strtotime($conflitto['ora_fine'])); ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    
                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500">Descrizione</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo htmlspecialchars($conflitto['descrizione']); ?>
                        </dd>
                    </div>
                    
                    <?php if ($conflitto['dati_conflitto']): ?>
                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500">Dettagli Tecnici</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono bg-gray-100 p-3 rounded">
                            <pre><?php echo htmlspecialchars(json_encode(json_decode($conflitto['dati_conflitto']), JSON_PRETTY_PRINT)); ?></pre>
                        </dd>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Opzioni di Risoluzione -->
        <div class="space-y-6">
            <!-- Suggerimenti Automatici -->
            <?php if (!empty($suggerimenti)): ?>
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                            Suggerimenti Automatici
                        </h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <?php if (!empty($suggerimenti['slot_alternativi'])): ?>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Slot Alternativi</h4>
                                <div class="space-y-2">
                                    <?php foreach ($suggerimenti['slot_alternativi'] as $suggerimento): ?>
                                        <button class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 applica-soluzione"
                                                data-tipo="cambia_slot"
                                                data-lezione-id="<?php echo $conflitto['lezione_id']; ?>"
                                                data-slot-id="<?php echo $suggerimento['slot_id']; ?>"
                                                data-data-lezione="<?php echo $suggerimento['data_suggerita']; ?>">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="text-sm font-medium"><?php echo date('d/m/Y', strtotime($suggerimento['data_suggerita'])); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo $suggerimento['ora_inizio'] . ' - ' . $suggerimento['ora_fine']; ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xs <?php echo $suggerimento['punteggio'] > 80 ? 'text-green-600' : ($suggerimento['punteggio'] > 60 ? 'text-yellow-600' : 'text-gray-500'); ?>">
                                                        <?php echo $suggerimento['punteggio']; ?>%
                                                    </p>
                                                    <p class="text-xs text-gray-400">Compatibilità</p>
                                                </div>
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($suggerimenti['docenti_alternativi'])): ?>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Docenti Alternativi</h4>
                                <div class="space-y-2">
                                    <?php foreach ($suggerimenti['docenti_alternativi'] as $suggerimento): ?>
                                        <button class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 applica-soluzione"
                                                data-tipo="cambia_docente"
                                                data-lezione-id="<?php echo $conflitto['lezione_id']; ?>"
                                                data-docente-id="<?php echo $suggerimento['docente_id']; ?>">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($suggerimento['docente_nome']); ?></p>
                                                    <p class="text-xs text-gray-500">Disponibile</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xs <?php echo $suggerimento['punteggio'] > 80 ? 'text-green-600' : ($suggerimento['punteggio'] > 60 ? 'text-yellow-600' : 'text-gray-500'); ?>">
                                                        <?php echo $suggerimento['punteggio']; ?>%
                                                    </p>
                                                    <p class="text-xs text-gray-400">Compatibilità</p>
                                                </div>
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($suggerimenti['aule_alternative'])): ?>
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Aule Alternative</h4>
                                <div class="space-y-2">
                                    <?php foreach ($suggerimenti['aule_alternative'] as $suggerimento): ?>
                                        <button class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 applica-soluzione"
                                                data-tipo="cambia_aula"
                                                data-lezione-id="<?php echo $conflitto['lezione_id']; ?>"
                                                data-aula-id="<?php echo $suggerimento['aula_id']; ?>">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($suggerimento['aula_nome']); ?></p>
                                                    <p class="text-xs text-gray-500">Capienza: <?php echo $suggerimento['capienza']; ?> posti</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xs <?php echo $suggerimento['punteggio'] > 80 ? 'text-green-600' : ($suggerimento['punteggio'] > 60 ? 'text-yellow-600' : 'text-gray-500'); ?>">
                                                        <?php echo $suggerimento['punteggio']; ?>%
                                                    </p>
                                                    <p class="text-xs text-gray-400">Adeguatezza</p>
                                                </div>
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Soluzioni Manuali -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        <i class="fas fa-cogs text-blue-500 mr-2"></i>
                        Soluzioni Manuali
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6 space-y-4">
                    <!-- Cambia Slot -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock text-gray-400 mt-1"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-700">Cambia Orario</h4>
                            <p class="text-xs text-gray-500 mt-1">Sposta la lezione in un altro slot orario</p>
                            <button class="mt-2 inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 soluzione-manuale"
                                    data-tipo="cambia_slot_manuale">
                                Seleziona Slot
                            </button>
                        </div>
                    </div>

                    <!-- Cambia Docente -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user text-gray-400 mt-1"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-700">Cambia Docente</h4>
                            <p class="text-xs text-gray-500 mt-1">Assegna un docente alternativo</p>
                            <button class="mt-2 inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 soluzione-manuale"
                                    data-tipo="cambia_docente_manuale">
                                Seleziona Docente
                            </button>
                        </div>
                    </div>

                    <!-- Cambia Aula -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-door-open text-gray-400 mt-1"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-700">Cambia Aula</h4>
                            <p class="text-xs text-gray-500 mt-1">Assegna un'aula alternativa</p>
                            <button class="mt-2 inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 soluzione-manuale"
                                    data-tipo="cambia_aula_manuale">
                                Seleziona Aula
                            </button>
                        </div>
                    </div>

                    <!-- Ignora Conflitto -->
                    <div class="flex items-start pt-4 border-t border-gray-200">
                        <div class="flex-shrink-0">
                            <i class="fas fa-eye-slash text-gray-400 mt-1"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-700">Ignora Conflitto</h4>
                            <p class="text-xs text-gray-500 mt-1">Segna come risolto senza modifiche</p>
                            <button class="mt-2 inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50"
                                    id="ignora-conflitto">
                                Ignora
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Conferma -->
    <div id="modal-conferma" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <i class="fas fa-check text-green-600 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2" id="modal-titolo">Conferma Risoluzione</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="modal-descrizione">
                        Sei sicuro di voler applicare questa soluzione?
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="conferma-risoluzione" 
                            class="px-4 py-2 bg-green-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300">
                        Conferma
                    </button>
                    <button id="annulla-risoluzione" 
                            class="ml-2 px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Annulla
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let soluzioneSelezionata = null;

document.addEventListener('DOMContentLoaded', function() {
    // Applica soluzione automatica
    document.querySelectorAll('.applica-soluzione').forEach(button => {
        button.addEventListener('click', function() {
            soluzioneSelezionata = {
                tipo: this.dataset.tipo,
                lezione_id: this.dataset.lezioneId,
                slot_id: this.dataset.slotId,
                docente_id: this.dataset.docenteId,
                aula_id: this.dataset.aulaId,
                data_lezione: this.dataset.dataLezione
            };
            
            document.getElementById('modal-titolo').textContent = 'Conferma Soluzione Automatica';
            document.getElementById('modal-descrizione').textContent = 
                'Sei sicuro di voler applicare questa soluzione automatica?';
            document.getElementById('modal-conferma').classList.remove('hidden');
        });
    });

    // Soluzioni manuali
    document.querySelectorAll('.soluzione-manuale').forEach(button => {
        button.addEventListener('click', function() {
            const tipo = this.dataset.tipo;
            alert('Funzionalità di selezione manuale da implementare');
            // Qui si aprirebbe un modal per selezionare slot/docente/aula manualmente
        });
    });

    // Ignora conflitto
    document.getElementById('ignora-conflitto').addEventListener('click', function() {
        if (confirm('Ignorare questo conflitto? Sarà marcato come risolto senza modifiche.')) {
            fetch('../api/conflitti_api.php?action=ignora', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    conflitto_id: <?php echo $conflitto_id; ?>,
                    motivo: 'Ignorato manualmente'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'conflitti.php';
                }
            });
        }
    });

    // Conferma risoluzione
    document.getElementById('conferma-risoluzione').addEventListener('click', function() {
        if (soluzioneSelezionata) {
            fetch('../api/conflitti_api.php?action=risolvi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conflitto_id: <?php echo $conflitto_id; ?>,
                    soluzione: soluzioneSelezionata
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'conflitti.php?risolto=1';
                } else {
                    alert('Errore nell\'applicare la soluzione: ' + (data.message || 'Errore sconosciuto'));
                }
            });
        }
    });

    // Annulla risoluzione
    document.getElementById('annulla-risoluzione').addEventListener('click', function() {
        document.getElementById('modal-conferma').classList.add('hidden');
        soluzioneSelezionata = null;
    });
});
</script>

<?php include '../includes/footer.php'; ?>