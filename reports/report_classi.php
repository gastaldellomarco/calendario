<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';

$anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
$sede_id = $_GET['sede_id'] ?? '';
$data_inizio = $_GET['data_inizio'] ?? '';
$data_fine = $_GET['data_fine'] ?? '';

$copertura_classi = [];
$composizione_ore = [];
$distribuzione_oraria = [];

try {
    // Copertura ore per classe
    $sql = "
        SELECT 
            c.nome as classe,
            p.nome as percorso,
            s.nome as sede,
            c.ore_settimanali_previste as ore_previste,
            COALESCE(SUM(cmd.ore_settimanali), 0) as ore_assegnate,
            COALESCE((
                SELECT COUNT(*) 
                FROM calendario_lezioni cl 
                WHERE cl.classe_id = c.id 
                AND cl.stato IN ('svolta', 'confermata')
                AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
                AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
            ), 0) as ore_effettuate,
            CASE 
                WHEN c.ore_settimanali_previste > 0 THEN 
                    ROUND((COALESCE(SUM(cmd.ore_settimanali), 0) / c.ore_settimanali_previste) * 100, 1)
                ELSE 0 
            END as percentuale_copertura
        FROM classi c
        LEFT JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
        LEFT JOIN sedi s ON c.sede_id = s.id
        LEFT JOIN classi_materie_docenti cmd ON c.id = cmd.classe_id AND cmd.attivo = 1
        WHERE c.stato = 'attiva'
        AND (:anno_scolastico_id = '' OR c.anno_scolastico_id = :anno_scolastico_id)
        AND (:sede_id = '' OR c.sede_id = :sede_id)
        GROUP BY c.id, c.nome, p.nome, s.nome, c.ore_settimanali_previste
        ORDER BY percentuale_copertura ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $copertura_classi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Composizione ore per tipologia materia
    $sql = "
        SELECT 
            c.nome as classe,
            m.tipo as tipologia_materia,
            SUM(cmd.ore_settimanali) as ore_settimanali,
            COUNT(DISTINCT m.id) as numero_materie
        FROM classi c
        JOIN classi_materie_docenti cmd ON c.id = cmd.classe_id AND cmd.attivo = 1
        JOIN materie m ON cmd.materia_id = m.id
        WHERE c.stato = 'attiva'
        AND (:anno_scolastico_id = '' OR c.anno_scolastico_id = :anno_scolastico_id)
        AND (:sede_id = '' OR c.sede_id = :sede_id)
        GROUP BY c.id, c.nome, m.tipo
        ORDER BY c.nome, ore_settimanali DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id
    ]);
    $composizione_ore = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Distribuzione oraria per giorno
    $sql = "
        SELECT 
            c.nome as classe,
            DAYOFWEEK(cl.data_lezione) as giorno_settimana,
            COUNT(*) as ore_giornaliere
        FROM classi c
        JOIN calendario_lezioni cl ON c.id = cl.classe_id
        WHERE c.stato = 'attiva'
        AND cl.stato IN ('svolta', 'confermata')
        AND (:anno_scolastico_id = '' OR c.anno_scolastico_id = :anno_scolastico_id)
        AND (:sede_id = '' OR c.sede_id = :sede_id)
        AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
        AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
        GROUP BY c.id, c.nome, DAYOFWEEK(cl.data_lezione)
        ORDER BY c.nome, giorno_settimana
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $distribuzione_oraria = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Errore nel caricamento dati: " . $e->getMessage() . "</div>";
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">📚 Report Classi</h1>
            <p class="text-gray-600">Analisi copertura ore e distribuzione oraria</p>
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

    <!-- Copertura Ore -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">📊 Copertura Ore</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left">Classe</th>
                        <th class="px-4 py-2 text-center">Percorso</th>
                        <th class="px-4 py-2 text-center">Sede</th>
                        <th class="px-4 py-2 text-center">Ore Previste</th>
                        <th class="px-4 py-2 text-center">Ore Assegnate</th>
                        <th class="px-4 py-2 text-center">Ore Effettuate</th>
                        <th class="px-4 py-2 text-center">% Copertura</th>
                        <th class="px-4 py-2 text-center">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($copertura_classi as $classe): 
                        $stato = match(true) {
                            $classe['percentuale_copertura'] < 70 => 'critico',
                            $classe['percentuale_copertura'] < 85 => 'attenzione',
                            default => 'ok'
                        };
                        $colore_stato = match($stato) {
                            'critico' => 'bg-red-100 text-red-800',
                            'attenzione' => 'bg-yellow-100 text-yellow-800',
                            default => 'bg-green-100 text-green-800'
                        };
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium"><?= htmlspecialchars($classe['classe']) ?></td>
                        <td class="px-4 py-2 text-center"><?= htmlspecialchars($classe['percorso']) ?></td>
                        <td class="px-4 py-2 text-center"><?= htmlspecialchars($classe['sede']) ?></td>
                        <td class="px-4 py-2 text-center"><?= $classe['ore_previste'] ?></td>
                        <td class="px-4 py-2 text-center"><?= $classe['ore_assegnate'] ?></td>
                        <td class="px-4 py-2 text-center"><?= $classe['ore_effettuate'] ?></td>
                        <td class="px-4 py-2 text-center font-semibold"><?= $classe['percentuale_copertura'] ?>%</td>
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

    <!-- Composizione Ore -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">🎨 Composizione Ore per Tipologia</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Grafico composizione -->
            <div>
                <canvas id="composizioneChart" height="250"></canvas>
            </div>
            
            <!-- Tabella dettagli -->
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left">Classe</th>
                            <th class="px-4 py-2 text-center">Tipologia</th>
                            <th class="px-4 py-2 text-center">Ore Sett.</th>
                            <th class="px-4 py-2 text-center">Materie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($composizione_ore as $composizione): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm"><?= htmlspecialchars($composizione['classe']) ?></td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                    <?= strtoupper($composizione['tipologia_materia']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center"><?= $composizione['ore_settimanali'] ?></td>
                            <td class="px-4 py-2 text-center"><?= $composizione['numero_materie'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Distribuzione Oraria -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">⏰ Distribuzione Oraria per Giorno</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left">Classe</th>
                        <th class="px-4 py-2 text-center">Lunedì</th>
                        <th class="px-4 py-2 text-center">Martedì</th>
                        <th class="px-4 py-2 text-center">Mercoledì</th>
                        <th class="px-4 py-2 text-center">Giovedì</th>
                        <th class="px-4 py-2 text-center">Venerdì</th>
                        <th class="px-4 py-2 text-center">Sabato</th>
                        <th class="px-4 py-2 text-center">Media Giorno</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Raggruppa per classe
                    $classi_distribuzione = [];
                    foreach ($distribuzione_oraria as $distribuzione) {
                        $classi_distribuzione[$distribuzione['classe']][$distribuzione['giorno_settimana']] = $distribuzione['ore_giornaliere'];
                    }
                    
                    $nomi_giorni = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
                    
                    foreach ($classi_distribuzione as $classe_nome => $giorni): 
                        $totale_ore = array_sum($giorni);
                        $giorni_validi = count(array_filter($giorni));
                        $media_giorno = $giorni_validi > 0 ? round($totale_ore / $giorni_validi, 1) : 0;
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium"><?= htmlspecialchars($classe_nome) ?></td>
                        <?php for ($giorno = 1; $giorno <= 6; $giorno++): ?>
                        <td class="px-4 py-2 text-center">
                            <span class="inline-block px-2 py-1 rounded 
                                <?= ($giorni[$giorno] ?? 0) > 6 ? 'bg-red-100 text-red-800' : 
                                   (($giorni[$giorno] ?? 0) > 4 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') ?>">
                                <?= $giorni[$giorno] ?? 0 ?>
                            </span>
                        </td>
                        <?php endfor; ?>
                        <td class="px-4 py-2 text-center font-semibold 
                            <?= $media_giorno > 6 ? 'text-red-600' : 
                               ($media_giorno > 4 ? 'text-green-600' : 'text-gray-600') ?>">
                            <?= $media_giorno ?>
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
    window.open(`../../api/reports_api.php?action=export_classi_pdf&${params.toString()}`, '_blank');
}

function exportExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_classi_excel&${params.toString()}`, '_blank');
}

// Grafico composizione ore
const composizioneData = <?= json_encode($composizione_ore) ?>;
const classiUniche = [...new Set(composizioneData.map(item => item.classe))];
const tipologie = ['culturale', 'professionale', 'laboratoriale', 'stage', 'sostegno'];

const datasets = tipologie.map((tipologia, index) => {
    const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];
    return {
        label: tipologia.toUpperCase(),
        data: classiUniche.map(classe => {
            const item = composizioneData.find(d => d.classe === classe && d.tipologia_materia === tipologia);
            return item ? item.ore_settimanali : 0;
        }),
        backgroundColor: colors[index]
    };
});

const composizioneCtx = document.getElementById('composizioneChart').getContext('2d');
new Chart(composizioneCtx, {
    type: 'bar',
    data: {
        labels: classiUniche,
        datasets: datasets
    },
    options: {
        responsive: true,
        scales: {
            x: {
                stacked: true
            },
            y: {
                stacked: true,
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