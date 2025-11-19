<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';

$anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
$sede_id = $_GET['sede_id'] ?? '';
$data_inizio = $_GET['data_inizio'] ?? '';
$data_fine = $_GET['data_fine'] ?? '';

$completamento_anno = [];
$qualita_calendario = [];
$distribuzione_attivita = [];
$timeline_anno = [];

try {
    // Completamento anno scolastico
    $sql = "
        SELECT 
            a.anno as anno_scolastico,
            a.data_inizio,
            a.data_fine,
            DATEDIFF(a.data_fine, a.data_inizio) as giorni_totali,
            DATEDIFF(CURDATE(), a.data_inizio) as giorni_trascorsi,
            COUNT(DISTINCT cl.data_lezione) as giorni_lezione_effettivi,
            COUNT(cl.id) as lezioni_totali,
            SUM(CASE WHEN cl.stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte,
            SUM(CASE WHEN cl.stato = 'cancellata' THEN 1 ELSE 0 END) as lezioni_cancellate,
            ROUND(COUNT(DISTINCT cl.data_lezione) * 100.0 / DATEDIFF(a.data_fine, a.data_inizio), 1) as percentuale_completamento
        FROM anni_scolastici a
        LEFT JOIN classi c ON a.id = c.anno_scolastico_id
        LEFT JOIN calendario_lezioni cl ON c.id = cl.classe_id 
            AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
            AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
        WHERE a.id = COALESCE(NULLIF(:anno_scolastico_id, ''), (SELECT id FROM anni_scolastici WHERE attivo = 1 LIMIT 1))
        GROUP BY a.id, a.anno, a.data_inizio, a.data_fine
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id ?: null,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $completamento_anno = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Qualità calendario
    $sql = "
        SELECT 
            COUNT(*) as lezioni_totali,
            SUM(CASE WHEN cl.stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte,
            SUM(CASE WHEN cl.stato = 'cancellata' THEN 1 ELSE 0 END) as lezioni_cancellate,
            SUM(CASE WHEN cl.stato = 'sostituita' THEN 1 ELSE 0 END) as lezioni_sostituite,
            COUNT(DISTINCT co.id) as conflitti_totali,
            SUM(CASE WHEN co.risolto = 1 THEN 1 ELSE 0 END) as conflitti_risolti,
            SUM(CASE WHEN cl.modificato_manualmente = 1 THEN 1 ELSE 0 END) as modifiche_manuali,
            ROUND(SUM(CASE WHEN cl.stato = 'svolta' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as tasso_successo
        FROM calendario_lezioni cl
        LEFT JOIN conflitti_orario co ON cl.id = co.lezione_id
        WHERE (:anno_scolastico_id = '' OR EXISTS (
            SELECT 1 FROM classi c WHERE c.id = cl.classe_id AND c.anno_scolastico_id = :anno_scolastico_id
        ))
        AND (:sede_id = '' OR cl.sede_id = :sede_id)
        AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
        AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $qualita_calendario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Distribuzione attività
    $sql = "
        SELECT 
            DAYOFWEEK(cl.data_lezione) as giorno_settimana,
            COUNT(*) as lezioni_giorno,
            AVG(os.durata_minuti) as durata_media_lezioni,
            COUNT(DISTINCT cl.docente_id) as docenti_attivi_giorno,
            COUNT(DISTINCT cl.classe_id) as classi_attive_giorno
        FROM calendario_lezioni cl
        JOIN orari_slot os ON cl.slot_id = os.id
        WHERE cl.stato IN ('svolta', 'confermata')
        AND (:anno_scolastico_id = '' OR EXISTS (
            SELECT 1 FROM classi c WHERE c.id = cl.classe_id AND c.anno_scolastico_id = :anno_scolastico_id
        ))
        AND (:sede_id = '' OR cl.sede_id = :sede_id)
        AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
        AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
        GROUP BY DAYOFWEEK(cl.data_lezione)
        ORDER BY giorno_settimana
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $distribuzione_attivita = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Timeline anno scolastico
    $sql = "
        SELECT 
            DATE_FORMAT(cl.data_lezione, '%Y-%m') as mese_anno,
            COUNT(*) as lezioni_mese,
            COUNT(DISTINCT cl.docente_id) as docenti_attivi_mese,
            COUNT(DISTINCT cl.classe_id) as classi_attive_mese,
            SUM(CASE WHEN cl.stato = 'svolta' THEN 1 ELSE 0 END) as lezioni_svolte_mese
        FROM calendario_lezioni cl
        WHERE (:anno_scolastico_id = '' OR EXISTS (
            SELECT 1 FROM classi c WHERE c.id = cl.classe_id AND c.anno_scolastico_id = :anno_scolastico_id
        ))
        AND (:sede_id = '' OR cl.sede_id = :sede_id)
        AND (:data_inizio = '' OR cl.data_lezione >= :data_inizio)
        AND (:data_fine = '' OR cl.data_lezione <= :data_fine)
        GROUP BY DATE_FORMAT(cl.data_lezione, '%Y-%m')
        ORDER BY mese_anno
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'anno_scolastico_id' => $anno_scolastico_id,
        'sede_id' => $sede_id,
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine
    ]);
    $timeline_anno = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Errore nel caricamento dati: " . $e->getMessage() . "</div>";
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">📅 Report Calendario</h1>
            <p class="text-gray-600">Analisi completa dell'anno scolastico e distribuzione attività</p>
        </div>
        <div class="flex gap-2">
            <button onclick="exportPDF()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                <i class="fas fa-file-pdf mr-2"></i>PDF
            </button>
            <button onclick="exportExcel()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                <i class="fas fa-file-excel mr-2"></i>Excel
            </button>
            <button onclick="exportICal()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                <i class="fas fa-calendar-alt mr-2"></i>iCal
            </button>
        </div>
    </div>

    <!-- Completamento Anno -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">🎯 Completamento Anno Scolastico</h2>
        
        <?php if (!empty($completamento_anno)): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?= $completamento_anno['percentuale_completamento'] ?? 0 ?>%</div>
                <div class="text-sm text-gray-600">Completamento</div>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600"><?= $completamento_anno['lezioni_svolte'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Lezioni Svolte</div>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600"><?= $completamento_anno['giorni_trascorsi'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Giorni Trascorsi</div>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <div class="text-2xl font-bold text-purple-600"><?= $completamento_anno['giorni_totali'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Giorni Totali</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-4">
            <div class="flex justify-between text-sm text-gray-600 mb-1">
                <span>Inizio: <?= $completamento_anno['data_inizio'] ?></span>
                <span>Oggi: <?= date('Y-m-d') ?></span>
                <span>Fine: <?= $completamento_anno['data_fine'] ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4">
                <div class="bg-blue-600 h-4 rounded-full" 
                     style="width: <?= min($completamento_anno['percentuale_completamento'] ?? 0, 100) ?>%">
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-500">
                Nessun dato disponibile per l'anno scolastico selezionato
            </div>
        <?php endif; ?>
    </div>

    <!-- Qualità Calendario -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">📈 Qualità del Calendario</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600"><?= $qualita_calendario['tasso_successo'] ?? 0 ?>%</div>
                <div class="text-sm text-gray-600">Tasso Successo</div>
            </div>
            <div class="text-center p-4 bg-red-50 rounded-lg">
                <div class="text-2xl font-bold text-red-600"><?= $qualita_calendario['conflitti_totali'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Conflitti Totali</div>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600"><?= $qualita_calendario['lezioni_cancellate'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Lezioni Cancellate</div>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?= $qualita_calendario['modifiche_manuali'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Modifiche Manuali</div>
            </div>
        </div>

        <!-- Grafico qualità -->
        <div class="h-64">
            <canvas id="qualitaChart"></canvas>
        </div>
    </div>

    <!-- Distribuzione Attività -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">⏰ Distribuzione Attività per Giorno</h2>
        
        <div class="h-64">
            <canvas id="distribuzioneGiorniChart"></canvas>
        </div>
        
        <div class="mt-6 overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left">Giorno</th>
                        <th class="px-4 py-2 text-center">Lezioni</th>
                        <th class="px-4 py-2 text-center">Durata Media</th>
                        <th class="px-4 py-2 text-center">Docenti Attivi</th>
                        <th class="px-4 py-2 text-center">Classi Attive</th>
                        <th class="px-4 py-2 text-center">Intensità</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $nomi_giorni = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
                    foreach ($distribuzione_attivita as $distribuzione): 
                        $intensita = match(true) {
                            $distribuzione['lezioni_giorno'] > 50 => 'molto alta',
                            $distribuzione['lezioni_giorno'] > 30 => 'alta',
                            $distribuzione['lezioni_giorno'] > 15 => 'media',
                            default => 'bassa'
                        };
                        $colore_intensita = match($intensita) {
                            'molto alta' => 'bg-red-100 text-red-800',
                            'alta' => 'bg-orange-100 text-orange-800',
                            'media' => 'bg-yellow-100 text-yellow-800',
                            default => 'bg-green-100 text-green-800'
                        };
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium"><?= $nomi_giorni[$distribuzione['giorno_settimana']] ?></td>
                        <td class="px-4 py-2 text-center"><?= $distribuzione['lezioni_giorno'] ?></td>
                        <td class="px-4 py-2 text-center"><?= round($distribuzione['durata_media_lezioni'], 0) ?> min</td>
                        <td class="px-4 py-2 text-center"><?= $distribuzione['docenti_attivi_giorno'] ?></td>
                        <td class="px-4 py-2 text-center"><?= $distribuzione['classi_attive_giorno'] ?></td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $colore_intensita ?>">
                                <?= strtoupper($intensita) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Timeline Anno -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">📅 Timeline Anno Scolastico</h2>
        
        <div class="h-64">
            <canvas id="timelineChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_calendario_pdf&${params.toString()}`, '_blank');
}

function exportExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_calendario_excel&${params.toString()}`, '_blank');
}

function exportICal() {
    const params = new URLSearchParams(window.location.search);
    window.open(`../../api/reports_api.php?action=export_calendario_ical&${params.toString()}`, '_blank');
}

// Grafico qualità calendario
const qualitaCtx = document.getElementById('qualitaChart').getContext('2d');
new Chart(qualitaCtx, {
    type: 'bar',
    data: {
        labels: ['Lezioni Svolte', 'Lezioni Cancellate', 'Conflitti', 'Modifiche Manuali'],
        datasets: [{
            label: 'Quantità',
            data: [
                <?= $qualita_calendario['lezioni_svolte'] ?? 0 ?>,
                <?= $qualita_calendario['lezioni_cancellate'] ?? 0 ?>,
                <?= $qualita_calendario['conflitti_totali'] ?? 0 ?>,
                <?= $qualita_calendario['modifiche_manuali'] ?? 0 ?>
            ],
            backgroundColor: ['#10B981', '#EF4444', '#F59E0B', '#3B82F6']
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Grafico distribuzione giorni
const distribuzioneData = <?= json_encode($distribuzione_attivita) ?>;
const giorniLabels = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
const giorniData = [0,0,0,0,0,0,0];
distribuzioneData.forEach(item => {
    giorniData[item.giorno_settimana - 1] = item.lezioni_giorno;
});

const distribuzioneCtx = document.getElementById('distribuzioneGiorniChart').getContext('2d');
new Chart(distribuzioneCtx, {
    type: 'line',
    data: {
        labels: giorniLabels,
        datasets: [{
            label: 'Lezioni per Giorno',
            data: giorniData,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Grafico timeline
const timelineData = <?= json_encode($timeline_anno) ?>;
const timelineCtx = document.getElementById('timelineChart').getContext('2d');
new Chart(timelineCtx, {
    type: 'bar',
    data: {
        labels: timelineData.map(t => t.mese_anno),
        datasets: [{
            label: 'Lezioni per Mese',
            data: timelineData.map(t => t.lezioni_mese),
            backgroundColor: '#10B981'
        }, {
            label: 'Lezioni Svolte',
            data: timelineData.map(t => t.lezioni_svolte_mese),
            backgroundColor: '#3B82F6'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>