<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

$current_page = 'disponibilita_docenti';
$page_title = 'Disponibilità Docenti';

// Filtri
$sede_id = $_GET['sede_id'] ?? '';
$materia_id = $_GET['materia_id'] ?? '';
$giorno_settimana = $_GET['giorno_settimana'] ?? date('N'); // 1=Lunedì, 7=Domenica

// Settimana di riferimento
$data_riferimento = $_GET['data_riferimento'] ?? date('Y-m-d');
$inizio_settimana = date('Y-m-d', strtotime('monday this week', strtotime($data_riferimento)));

// Docenti
$where_docenti = "WHERE d.stato = 'attivo'";
$params_docenti = [];
if ($sede_id) {
    $where_docenti .= " AND d.sede_principale_id = ?";
    $params_docenti[] = $sede_id;
}

$stmt_docenti = $db->prepare("
    SELECT d.id, d.cognome, d.nome, s.nome as sede_nome
    FROM docenti d
    JOIN sedi s ON d.sede_principale_id = s.id
    $where_docenti
    ORDER BY d.cognome, d.nome
");
if ($stmt_docenti) {
    $stmt_docenti->execute($params_docenti);
    $docenti = $stmt_docenti->fetchAll();
} else {
    error_log("Errore preparazione query docenti disponibilità");
    $docenti = [];
}

// Sedie e materie per filtri
$sedi = $db->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome")->fetchAll();
$materie = $db->query("SELECT id, nome FROM materie WHERE attiva = 1 ORDER BY nome")->fetchAll();

// Giorni della settimana
$giorni_settimana = [];
for ($i = 0; $i < 7; $i++) {
    $giorni_settimana[] = [
        'data' => date('Y-m-d', strtotime($inizio_settimana . " +$i days")),
        'nome' => date('D', strtotime($inizio_settimana . " +$i days")),
        'numero' => $i + 1
    ];
}
?>

<?php include '../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Disponibilità Docenti
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Vista rapida della disponibilità dei docenti per la settimana
            </p>
        </div>
    </div>

    <!-- Filtri -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Sede -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Sede</label>
                <select name="sede_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tutte le sedi</option>
                    <?php foreach ($sedi as $sede): ?>
                        <option value="<?php echo $sede['id']; ?>" <?php echo $sede_id == $sede['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sede['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Materia -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Materia</label>
                <select name="materia_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tutte le materie</option>
                    <?php foreach ($materie as $materia): ?>
                        <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($materia['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Settimana -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Settimana</label>
                <input type="week" name="data_riferimento" value="<?php echo date('Y-\WW', strtotime($data_riferimento)); ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Azioni -->
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i>Filtra
                </button>
            </div>
        </form>
    </div>

    <!-- Griglia Disponibilità -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">
                            Docente
                        </th>
                        <?php foreach ($giorni_settimana as $giorno): ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r">
                                <?php echo $giorno['nome']; ?><br>
                                <span class="text-xs font-normal"><?php echo date('d/m', strtotime($giorno['data'])); ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($docenti as $docente): ?>
                        <tr class="hover:bg-gray-50">
                            <!-- Nome Docente -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r">
                                <div class="flex items-center">
                                    <img class="h-8 w-8 rounded-full mr-3" 
                                         src="https://ui-avatars.com/api/?name=<?php echo urlencode($docente['cognome'] . ' ' . $docente['nome']); ?>&background=3b82f6&color=fff" 
                                         alt="">
                                    <div>
                                        <div><?php echo htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($docente['sede_nome']); ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Giorni della settimana -->
                            <?php foreach ($giorni_settimana as $giorno): ?>
                                <?php
                                // Calcola disponibilità per il giorno
                                $disponibilita = $this->calcolaDisponibilitaGiorno($docente['id'], $giorno['data'], $materia_id);
                                ?>
                                <td class="px-4 py-4 text-center border-r cursor-pointer hover:bg-gray-100"
                                    onclick="mostraDettaglioGiorno(<?php echo $docente['id']; ?>, '<?php echo $giorno['data']; ?>')">
                                    
                                    <!-- Indicatore visivo -->
                                    <div class="flex flex-col items-center">
                                        <!-- Cerchio indicatore -->
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold
                                            <?php echo $disponibilita['colore']; ?>"
                                            title="<?php echo htmlspecialchars($disponibilita['tooltip']); ?>">
                                            <?php echo $disponibilita['ore_impegnate']; ?>
                                        </div>
                                        
                                        <!-- Testo sotto -->
                                        <div class="mt-1 text-xs text-gray-600">
                                            <?php echo $disponibilita['testo']; ?>
                                        </div>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legenda -->
    <div class="mt-6 bg-white shadow rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-900 mb-2">Legenda Color</h3>
        <div class="flex flex-wrap gap-4 text-xs">
            <div class="flex items-center">
                <div class="w-4 h-4 rounded-full bg-green-500 mr-2"></div>
                <span>Disponibile (>75%)</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 rounded-full bg-yellow-400 mr-2"></div>
                <span>Parzialmente (25-75%)</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 rounded-full bg-red-500 mr-2"></div>
                <span>Pieno (<25%)</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 rounded-full bg-gray-300 mr-2"></div>
                <span>Non disponibile</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Dettaglio Giorno -->
<div id="modalDettaglio" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitolo">Dettaglio Giorno</h3>
                <button onclick="chiudiModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mt-4" id="modalContenuto">
                <!-- Contenuto caricato via AJAX -->
            </div>
            
            <div class="mt-4 flex justify-end">
                <button onclick="chiudiModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Chiudi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function mostraDettaglioGiorno(docente_id, data) {
    // Mostra loading
    document.getElementById('modalContenuto').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-500"></div>';
    document.getElementById('modalDettaglio').classList.remove('hidden');
    
    // Carica dati via AJAX
    fetch(`../api/disponibilita_api.php?action=dettaglio_giorno&docente_id=${docente_id}&data=${data}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitolo').textContent = data.titolo;
                document.getElementById('modalContenuto').innerHTML = data.html;
            } else {
                document.getElementById('modalContenuto').innerHTML = '<div class="text-center py-8 text-red-500">Errore nel caricamento</div>';
            }
        })
        .catch(error => {
            document.getElementById('modalContenuto').innerHTML = '<div class="text-center py-8 text-red-500">Errore di connessione</div>';
        });
}

function chiudiModal() {
    document.getElementById('modalDettaglio').classList.add('hidden');
}

// Helper function per calcolare disponibilità (simulata)
function calcolaDisponibilitaGiorno(docenteId, data, materiaId) {
    // Questa funzione sarebbe implementata lato server
    // Qui solo per esempio
    return {
        colore: 'bg-green-500',
        ore_impegnate: '2',
        testo: '2/6 ore',
        tooltip: '2 ore impegnate su 6 totali'
    };
}
</script>

<?php include '../includes/footer.php'; ?>