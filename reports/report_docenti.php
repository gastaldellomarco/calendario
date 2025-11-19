<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';

$anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
$sede_id = $_GET['sede_id'] ?? '';
$data_inizio = $_GET['data_inizio'] ?? '';
$data_fine = $_GET['data_fine'] ?? '';

// Query complesse per statistiche docenti
$stats_docenti = [];
$carico_lavoro = [];
$competenze = [];
$vincoli = [];

try {
    // Statistiche generali docenti
    $sql = "
        SELECT 
            COUNT(*) as totale_docenti,
            AVG(d.ore_settimanali_contratto) as media_ore_contratto,
            SUM(CASE WHEN d.stato = 'attivo' THEN 1 ELSE 0 END) as docenti_attivi,
            COUNT(DISTINCT d.sede_principale_id) as sedi_coperte,
            (SELECT COUNT(*) FROM docenti_materie WHERE abilitato = 1) as competenze_totali
        FROM docenti d
        WHERE (:sede_id = '' OR d.sede_principale_id = :sede_id)
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['sede_id' => $sede_id]);
    $stats_docenti = $stmt->fetch(PDO::FETCH_ASSOC);

    // Carico lavoro docenti
    $sql = "
        SELECT 
            d.id,
            CONCAT(d.cognome, ' ', d.nome) as docente,
            s.nome as sede,
            d.ore_settimanali_contratto as ore_contratto,
            COALESCE(SUM(cmd.ore_settimanali), 0) as ore_assegnate,
            COALESCE((
                SELECT COUNT(*) 
                FROM calendario_lezioni cl 
                WHERE cl.docente_id = d.id 
                AND cl.stato IN ('svolta', 'confermata')
                AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
                AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
            ), 0) as ore_effettuate,
            CASE 
                WHEN d.ore_settimanali_contratto > 0 THEN 
                    ROUND((COALESCE(SUM(cmd.ore_settimanali), 0) / d.ore_settimanali_contratto) * 100, 1)
                ELSE 0 
            END as percentuale_carico
        FROM docenti d
        LEFT JOIN sedi s ON d.sede_principale_id = s.id
        LEFT JOIN classi_materie_docenti cmd ON d.id = cmd.docente_id AND cmd.attivo = 1
        WHERE d.stato = 'attivo'
        AND (:sede_id = '' OR d.sede_principale_id = :sede_id)
        GROUP BY d.id, d.cognome, d.nome, s.nome, d.ore_settimanali_contratto
        ORDER BY percentuale_carico DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $carico_lavoro = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Competenze e gap
    $sql = "
        SELECT 
            m.nome as materia,
            COUNT(DISTINCT dm.docente_id) as docenti_abilitati,
            (SELECT COUNT(*) FROM classi_materie_docenti cmd 
             WHERE cmd.materia_id = m.id AND cmd.attivo = 1) as classi_assegnate,
            CASE 
                WHEN COUNT(DISTINCT dm.docente_id) = 0 THEN 'CRITICO'
                WHEN COUNT(DISTINCT dm.docente_id) < 2 THEN 'ATTENZIONE'
                ELSE 'OK'
            END as stato_copertura
        FROM materie m
        LEFT JOIN docenti_materie dm ON m.id = dm.materia_id AND dm.abilitato = 1
        WHERE m.attiva = 1
        GROUP BY m.id, m.nome
        HAVING docenti_abilitati < 2 OR classi_assegnate > docenti_abilitati * 3
        ORDER BY docenti_abilitati ASC, classi_assegnate DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $competenze = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vincoli docenti
    $sql = "
        SELECT 
            d.id,
            CONCAT(d.cognome, ' ', d.nome) as docente,
            COUNT(vd.id) as numero_vincoli,
            GROUP_CONCAT(DISTINCT vd.tipo) as tipi_vincoli
        FROM docenti d
        LEFT JOIN vincoli_docenti vd ON d.id = vd.docente_id AND vd.attivo = 1
        WHERE d.stato = 'attivo'
        AND (:sede_id = '' OR d.sede_principale_id = :sede_id)
        GROUP BY d.id, d.cognome, d.nome
        HAVING COUNT(vd.id) > 0
        ORDER BY numero_vincoli DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['sede_id' => $sede_id]);
    $vincoli = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Errore nel caricamento dati: " . $e->getMessage() . "</div>";
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header con export -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">📈 Report Docenti</h1>
            <p class="text-gray-600">Analisi completa del personale docente</p>
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

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-2xl font-bold text-blue-600"><?= $stats_docenti['docenti_attivi'] ?? 0 ?></div>
            <div class="text-sm text-gray-600">Docenti Attivi</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-2xl font-bold text-green-600"><?= round($stats_docenti['media_ore_contratto'] ?? 0, 1) ?></div>
            <div class="text-sm text-gray-600">Ore Medie Contratto</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-2xl font-bold text-purple-600"><?= $stats_docenti['competenze_totali'] ?? 0 ?></div>
            <div class="text-sm text-gray-600">Competenze Totali</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-2xl font-bold text-orange-600"><?= $stats_docenti['sedi_coperte'] ?? 0 ?></div>
            <div class="text-sm text-gray-600">Sedi Coperte</div>
        </div>
    </div>

    <!-- Carico Lavoro -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">📊 Carico di Lavoro</h2>
        
        <!-- Grafico distribuzione ore -->
        <div class="mb-6">
            <canvas id="caricoLavoroChart" height="100"></canvas>
        </div>

        <!-- Tabella dettagliata -->
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left">Docente</th>
                        <th class="px-4 py-2 text-center">Sede</th>
                        <th class="px-4 py-2 text-center">Ore Contratto</th>
                        <th class="px-4 py-2 text-center">Ore Assegnate</th>
                        <th class="px-4 py-2 text-center">Ore Effettuate</th>
                        <th class="px-4 py-2 text-center">Differenza</th>
                        <th class="px-4 py-2 text-center">% Carico</th>
                        <th class="px-4 py-2 text-center">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carico_lavoro as $docente): 
                        $differenza = $docente['ore_assegnate'] - $docente['ore_contratto'];
                        $stato = match(true) {
                            $docente['percentuale_carico'] > 110 => 'critico',
                            $docente['percentuale_carico'] > 100 => 'attenzione',
                            $docente['percentuale_carico'] < 80 => 'sotto',
                            default => 'normale'
                        };
                        $colore_stato = match($stato) {
                            'critico' => 'bg-red-100 text-red-800',
                            'attenzione' => 'bg-yellow-100 text-yellow-800',
                            'sotto' => 'bg-blue-100 text-blue-800',
                            default => 'bg-green-100 text-green-800'
                        };
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2"><?= htmlspecialchars($docente['docente']) ?></td>
                        <td class="px-4 py-2 text-center"><?= htmlspecialchars($docente['sede']) ?></td>
                        <td class="px-4 py-2 text-center"><?= $docente['ore_contratto'] ?></td>
                        <td class="px-4 py-2 text-center"><?= $docente['ore_assegnate'] ?></td>
                        <td class="px-4 py-2 text-center"><?= $docente['ore_effettuate'] ?></td>
                        <td class="px-4 py-2 text-center <?= $differenza > 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= $differenza > 0 ? "+$differenza" : $differenza ?>
                        </td>
                        <td class="px-4 py-2 text-center"><?= $docente['percentuale_carico'] ?>%</td>
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

    <!-- Competenze e Gap -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">🎯 Competenze e Gap</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left">Materia</th>
                        <th class="px-4 py-2 text-center">Docenti Abilitati</th>
                        <th class="px-4 py-2 text-center">Classi Assegnate</th>
                        <th class="px-4 py-2 text-center">Stato Copertura</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($competenze as $materia): 
                        $colore_stato = match($materia['stato_copertura']) {
                            'CRITICO' => 'bg-red-100 text-red-800',
                            'ATTENZIONE' => 'bg-yellow-100 text-yellow-800',
                            default => 'bg-green-100 text-green-800'
                        };
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2"><?= htmlspecialchars($materia['materia']) ?></td>
                        <td class="px-4 py-2 text-center"><?= $materia['docenti_abilitati'] ?></td>
                        <td class="px-4 py-2 text-center"><?= $materia['classi_assegnate'] ?></td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $colore_stato ?>">
                                <?= $materia['stato_copertura'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Vincoli -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">⏰ Vincoli Orari</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left">Docente</th>
                        <th class="px-4 py-2 text-center">Numero Vincoli</th>
                        <th class="px-4 py-2 text-center">Tipi Vincoli</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vincoli as $vincolo): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2"><?= htmlspecialchars($vincolo['docente']) ?></td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-medium">
                                <?= $vincolo['numero_vincoli'] ?> vincoli
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center text-sm text-gray-600">
                            <?= htmlspecialchars($vincolo['tipi_vincoli']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_docenti_pdf&${params.toString()}`, '_blank');
}

function exportExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_docenti_excel&${params.toString()}`, '_blank');
}

// Grafico carico lavoro
const ctx = document.getElementById('caricoLavoroChart').getContext('2d');
const caricoLavoroChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($carico_lavoro, 'docente')) ?>,
        datasets: [{
            label: 'Ore Contratto',
            data: <?= json_encode(array_column($carico_lavoro, 'ore_contratto')) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)'
        }, {
            label: 'Ore Assegnate',
            data: <?= json_encode(array_column($carico_lavoro, 'ore_assegnate')) ?>,
            backgroundColor: 'rgba(255, 159, 64, 0.6)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Ore Settimanali'
                }
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>