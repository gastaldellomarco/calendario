<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';

$anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
$sede_id = $_GET['sede_id'] ?? '';
$data_inizio = $_GET['data_inizio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fine = $_GET['data_fine'] ?? date('Y-m-d');

$occupazione_aule = [];
$utilizzo_tipologie = [];
$conflitti_aule = [];

try {
    // Occupazione aule
    $sql = "
        SELECT 
            a.id,
            a.nome as aula,
            a.codice,
            s.nome as sede,
            a.tipo,
            a.capienza,
            COUNT(cl.id) as lezioni_totali,
            COUNT(DISTINCT DATE(cl.data_lezione)) as giorni_utilizzo,
            ROUND(COUNT(cl.id) * 100.0 / (30 * 8), 1) as percentuale_occupazione,
            AVG(CASE WHEN cl.aula_id = a.id THEN 1 ELSE 0 END) as tasso_utilizzo_preferito
        FROM aule a
        LEFT JOIN sedi s ON a.sede_id = s.id
        LEFT JOIN calendario_lezioni cl ON a.id = cl.aula_id 
            AND cl.stato IN ('svolta', 'confermata', 'pianificata')
            AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
            AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
        WHERE a.attiva = 1
        AND (:sede_id = '' OR a.sede_id = :sede_id)
        GROUP BY a.id, a.nome, a.codice, s.nome, a.tipo, a.capienza
        ORDER BY percentuale_occupazione DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $occupazione_aule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Utilizzo per tipologia
    $sql = "
        SELECT 
            a.tipo,
            COUNT(DISTINCT a.id) as numero_aule,
            AVG(COUNT(cl.id)) as lezioni_media_per_aula,
            SUM(COUNT(cl.id)) as lezioni_totali,
            ROUND(AVG(COUNT(cl.id)) * 100.0 / (30 * 8), 1) as occupazione_media
        FROM aule a
        LEFT JOIN calendario_lezioni cl ON a.id = cl.aula_id 
            AND cl.stato IN ('svolta', 'confermata', 'pianificata')
            AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
            AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
        WHERE a.attiva = 1
        AND (:sede_id = '' OR a.sede_id = :sede_id)
        GROUP BY a.tipo
        ORDER BY lezioni_totali DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $utilizzo_tipologie = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conflitti aule
    $sql = "
        SELECT 
            a.nome as aula,
            s.nome as sede,
            COUNT(co.id) as numero_conflitti,
            MIN(co.data_conflitto) as primo_conflitto,
            MAX(co.data_conflitto) as ultimo_conflitto
        FROM aule a
        LEFT JOIN sedi s ON a.sede_id = s.id
        LEFT JOIN conflitti_orario co ON a.id = co.aula_id 
            AND co.tipo = 'doppia_aula'
            AND (:data_inizio = '' OR co.data_conflitto >= :data_inizio)
            AND (:data_fine = '' OR co.data_conflitto <= :data_fine)
        WHERE a.attiva = 1
        AND (:sede_id = '' OR a.sede_id = :sede_id)
        GROUP BY a.id, a.nome, s.nome
        HAVING COUNT(co.id) > 0
        ORDER BY numero_conflitti DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $conflitti_aule = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Errore nel caricamento dati: " . $e->getMessage() . "</div>";
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🏫 Report Aule</h1>
            <p class="text-gray-600">Analisi utilizzo spazi e ottimizzazione</p>
        </div>
        <div class="flex gap-2">
            <button onclick="exportPDF()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                <i class="fas fa-file-pdf mr-2"></i>PDF
            </button>
            <button onclick="exportExcel()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                <i class="fas fa-file-excel mr-2"></i>Excel
            </button>
        </div>
    </div>

    <!-- Occupazione Aule -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">📊 Occupazione Aule</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left">Aula</th>
                        <th class="px-4 py-2 text-center">Sede</th>
                        <th class="px-4 py-2 text-center">Tipo</th>
                        <th class="px-4 py-2 text-center">Capienza</th>
                        <th class="px-4 py-2 text-center">Lezioni Totali</th>
                        <th class="px-4 py-2 text-center">Giorni Utilizzo</th>
                        <th class="px-4 py-2 text-center">% Occupazione</th>
                        <th class="px-4 py-2 text-center">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($occupazione_aule as $aula): 
                        $stato = match(true) {
                            $aula['percentuale_occupazione'] > 90 => 'sovrautilizzo',
                            $aula['percentuale_occupazione'] < 30 => 'sottoutilizzo',
                            default => 'ottimale'
                        };
                        $colore_stato = match($stato) {
                            'sovrautilizzo' => 'bg-red-100 text-red-800',
                            'sottoutilizzo' => 'bg-yellow-100 text-yellow-800',
                            default => 'bg-green-100 text-green-800'
                        };
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">
                            <div class="flex items-center">
                                <i class="fas fa-door-open text-gray-400 mr-2"></i>
                                <?= htmlspecialchars($aula['aula']) ?>
                                <span class="text-xs text-gray-500 ml-2">(<?= htmlspecialchars($aula['codice']) ?>)</span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-center"><?= htmlspecialchars($aula['sede']) ?></td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                <?= strtoupper($aula['tipo']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center"><?= $aula['capienza'] ?> posti</td>
                        <td class="px-4 py-2 text-center"><?= $aula['lezioni_totali'] ?></td>
                        <td class="px-4 py-2 text-center"><?= $aula['giorni_utilizzo'] ?></td>
                        <td class="px-4 py-2 text-center font-semibold 
                            <?= $aula['percentuale_occupazione'] > 90 ? 'text-red-600' : 
                               ($aula['percentuale_occupazione'] < 30 ? 'text-yellow-600' : 'text-green-600') ?>">
                            <?= $aula['percentuale_occupazione'] ?>%
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $colore_stato ?>">
                                <?= strtoupper($stato) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Utilizzo Tipologie -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">🎯 Utilizzo per Tipologia</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Grafico utilizzo -->
            <div>
                <canvas id="utilizzoTipologieChart" height="250"></canvas>
            </div>
            
            <!-- Tabella dettagli -->
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left">Tipologia</th>
                            <th class="px-4 py-2 text-center">Numero Aule</th>
                            <th class="px-4 py-2 text-center">Lezioni/Aula</th>
                            <th class="px-4 py-2 text-center">Lezioni Totali</th>
                            <th class="px-4 py-2 text-center">Occupazione Media</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilizzo_tipologie as $tipologia): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-medium">
                                    <?= strtoupper($tipologia['tipo']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center"><?= $tipologia['numero_aule'] ?></td>
                            <td class="px-4 py-2 text-center"><?= round($tipologia['lezioni_media_per_aula'], 1) ?></td>
                            <td class="px-4 py-2 text-center"><?= $tipologia['lezioni_totali'] ?></td>
                            <td class="px-4 py-2 text-center font-semibold 
                                <?= $tipologia['occupazione_media'] > 90 ? 'text-red-600' : 
                                   ($tipologia['occupazione_media'] < 30 ? 'text-yellow-600' : 'text-green-600') ?>">
                                <?= $tipologia['occupazione_media'] ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Conflitti Aule -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">⚠️ Conflitti di Utilizzo</h2>
        
        <?php if (empty($conflitti_aule)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                <p>Nessun conflitto di utilizzo aule nel periodo selezionato</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left">Aula</th>
                            <th class="px-4 py-2 text-center">Sede</th>
                            <th class="px-4 py-2 text-center">Conflitti Totali</th>
                            <th class="px-4 py-2 text-center">Primo Conflitto</th>
                            <th class="px-4 py-2 text-center">Ultimo Conflitto</th>
                            <th class="px-4 py-2 text-center">Gravità</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conflitti_aule as $conflitto): 
                            $gravita = match(true) {
                                $conflitto['numero_conflitti'] > 10 => 'alta',
                                $conflitto['numero_conflitti'] > 5 => 'media',
                                default => 'bassa'
                            };
                            $colore_gravita = match($gravita) {
                                'alta' => 'bg-red-100 text-red-800',
                                'media' => 'bg-yellow-100 text-yellow-800',
                                default => 'bg-green-100 text-green-800'
                            };
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium"><?= htmlspecialchars($conflitto['aula']) ?></td>
                            <td class="px-4 py-2 text-center"><?= htmlspecialchars($conflitto['sede']) ?></td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                                    <?= $conflitto['numero_conflitti'] ?> conflitti
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center text-sm"><?= $conflitto['primo_conflitto'] ?></td>
                            <td class="px-4 py-2 text-center text-sm"><?= $conflitto['ultimo_conflitto'] ?></td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $colore_gravita ?>">
                                    <?= strtoupper($gravita) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_aule_pdf&${params.toString()}`, '_blank');
}

function exportExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_aule_excel&${params.toString()}`, '_blank');
}

// Grafico utilizzo tipologie
const tipologieData = <?= json_encode($utilizzo_tipologie) ?>;
const utilizzoCtx = document.getElementById('utilizzoTipologieChart').getContext('2d');
new Chart(utilizzoCtx, {
    type: 'doughnut',
    data: {
        labels: tipologieData.map(t => t.tipo.toUpperCase()),
        datasets: [{
            data: tipologieData.map(t => t.lezioni_totali),
            backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            title: {
                display: true,
                text: 'Distribuzione Lezioni per Tipologia Aula'
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>