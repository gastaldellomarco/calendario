<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$anno_scolastico_corrente = Database::queryOne(
    "SELECT id, anno FROM anni_scolastici WHERE attivo = 1"
);

$filtro_anno = $_GET['anno_scolastico_id'] ?? $anno_scolastico_corrente['id'];
$filtro_classe = $_GET['classe_id'] ?? '';

// Statistiche generali
$statistiche = Database::queryOne("
    SELECT 
        COUNT(*) as totale_stage,
        SUM(sp.ore_totali_previste) as ore_previste_totali,
        SUM(sp.ore_effettuate) as ore_effettuate_totali,
        AVG(sp.ore_effettuate / sp.ore_totali_previste) * 100 as percentuale_media,
        COUNT(CASE WHEN sp.stato = 'completato' THEN 1 END) as stage_completati,
        COUNT(CASE WHEN sp.stato = 'in_corso' THEN 1 END) as stage_in_corso
    FROM stage_periodi sp
    JOIN classi c ON sp.classe_id = c.id
    WHERE c.anno_scolastico_id = ?
", [$filtro_anno]);

// Stage per classe
$stage_per_classe = Database::queryAll("
    SELECT 
        c.nome as classe_nome,
        p.nome as percorso_nome,
        COUNT(sp.id) as numero_stage,
        SUM(sp.ore_totali_previste) as ore_previste,
        SUM(sp.ore_effettuate) as ore_effettuate,
        AVG(sp.ore_effettuate / sp.ore_totali_previste) * 100 as percentuale_completamento
    FROM classi c
    JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
    LEFT JOIN stage_periodi sp ON c.id = sp.classe_id AND sp.stato != 'cancellato'
    WHERE c.anno_scolastico_id = ?
    GROUP BY c.id, c.nome, p.nome
    ORDER BY c.nome
", [$filtro_anno]);

// Aziende partner
$aziende_partner = Database::queryAll("
    SELECT 
        st.azienda,
        COUNT(DISTINCT sp.id) as numero_stage,
        COUNT(DISTINCT sp.classe_id) as classi_coinvolte,
        AVG(sp.ore_effettuate / sp.ore_totali_previste) * 100 as percentuale_media
    FROM stage_tutor st
    JOIN stage_periodi sp ON st.stage_periodo_id = sp.id
    WHERE sp.stato != 'cancellato'
    AND EXISTS (
        SELECT 1 FROM classi c 
        WHERE c.id = sp.classe_id AND c.anno_scolastico_id = ?
    )
    GROUP BY st.azienda
    HAVING st.azienda IS NOT NULL AND st.azienda != ''
    ORDER BY numero_stage DESC
", [$filtro_anno]);

$classi = Database::queryAll("
    SELECT id, nome FROM classi 
    WHERE anno_scolastico_id = ? 
    ORDER BY nome
", [$filtro_anno]);

$anni_scolastici = Database::queryAll("
    SELECT id, anno FROM anni_scolastici 
    ORDER BY data_inizio DESC
");
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Stage - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Report Stage</h1>
                <p class="text-gray-600 mt-2">Analisi e statistiche sui periodi di stage</p>
            </div>
            <div class="flex space-x-3">
                <button onclick="esportaExcel()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Esporta Excel
                </button>
                <a href="stage.php" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Torna a Stage
                </a>
            </div>
        </div>

        <!-- Filtri -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anno Scolastico</label>
                    <select name="anno_scolastico_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <?php foreach ($anni_scolastici as $anno): ?>
                            <option value="<?php echo $anno['id']; ?>" <?php echo $filtro_anno == $anno['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($anno['anno']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Classe</label>
                    <select name="classe_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Tutte le classi</option>
                        <?php foreach ($classi as $classe): ?>
                            <option value="<?php echo $classe['id']; ?>" <?php echo $filtro_classe == $classe['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classe['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg w-full">
                        <i class="fas fa-filter mr-2"></i> Applica Filtri
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistiche Generali -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo $statistiche['totale_stage']; ?></div>
                        <div class="text-sm text-gray-600">Stage Totali</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo $statistiche['stage_completati']; ?></div>
                        <div class="text-sm text-gray-600">Stage Completati</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($statistiche['percentuale_media'], 1); ?>%
                        </div>
                        <div class="text-sm text-gray-600">Completamento Medio</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-lg mr-4">
                        <i class="fas fa-building text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800"><?php echo count($aziende_partner); ?></div>
                        <div class="text-sm text-gray-600">Aziende Partner</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Grafico Completamento per Classe -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Completamento Stage per Classe</h2>
                <div class="h-64">
                    <canvas id="graficoCompletamento"></canvas>
                </div>
            </div>

            <!-- Distribuzione Aziende -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Aziende Partner</h2>
                <div class="h-64">
                    <canvas id="graficoAziende"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabella Riepilogo Classi -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Riepilogo per Classe</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore Previste</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore Effettuate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completamento</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Differenza</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($stage_per_classe as $classe): 
                            $differenza = $classe['ore_previste'] - $classe['ore_effettuate'];
                            $percentuale = $classe['percentuale_completamento'] ? round($classe['percentuale_completamento'], 1) : 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($classe['classe_nome']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($classe['percorso_nome']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $classe['numero_stage']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $classe['ore_previste'] ? number_format($classe['ore_previste']) : '0'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $classe['ore_effettuate'] ? number_format($classe['ore_effettuate']) : '0'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-green-600 h-2 rounded-full" 
                                             style="width: <?php echo min($percentuale, 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo $percentuale; ?>%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo $differenza > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo number_format($differenza); ?> ore
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabella Aziende Partner -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Aziende Partner</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azienda</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completamento Medio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($aziende_partner as $azienda): 
                            $stato = $azienda['percentuale_media'] >= 90 ? 'Eccellente' : 
                                    ($azienda['percentuale_media'] >= 75 ? 'Buono' : 'Da migliorare');
                            $stato_color = $azienda['percentuale_media'] >= 90 ? 'green' : 
                                         ($azienda['percentuale_media'] >= 75 ? 'blue' : 'yellow');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($azienda['azienda']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $azienda['numero_stage']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $azienda['classi_coinvolte']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-20 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-<?php echo $stato_color; ?>-600 h-2 rounded-full" 
                                             style="width: <?php echo min($azienda['percentuale_media'], 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo round($azienda['percentuale_media'], 1); ?>%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    bg-<?php echo $stato_color; ?>-100 text-<?php echo $stato_color; ?>-800">
                                    <?php echo $stato; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($aziende_partner)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                Nessuna azienda partner trovata
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Grafico Completamento per Classe
        const ctxCompletamento = document.getElementById('graficoCompletamento').getContext('2d');
        const graficoCompletamento = new Chart(ctxCompletamento, {
            type: 'bar',
            data: {
                labels: [<?php 
                    echo implode(', ', array_map(function($classe) {
                        return "'" . addslashes($classe['classe_nome']) . "'";
                    }, $stage_per_classe));
                ?>],
                datasets: [{
                    label: 'Percentuale Completamento',
                    data: [<?php 
                        echo implode(', ', array_map(function($classe) {
                            return round($classe['percentuale_completamento'] ?: 0, 1);
                        }, $stage_per_classe));
                    ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentuale Completamento (%)'
                        }
                    }
                }
            }
        });

        // Grafico Aziende Partner
        const ctxAziende = document.getElementById('graficoAziende').getContext('2d');
        const graficoAziende = new Chart(ctxAziende, {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    echo implode(', ', array_map(function($azienda) {
                        return "'" . addslashes($azienda['azienda']) . "'";
                    }, array_slice($aziende_partner, 0, 5)));
                ?>],
                datasets: [{
                    data: [<?php 
                        echo implode(', ', array_map(function($azienda) {
                            return $azienda['numero_stage'];
                        }, array_slice($aziende_partner, 0, 5)));
                    ?>],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.6)',
                        'rgba(16, 185, 129, 0.6)',
                        'rgba(245, 158, 11, 0.6)',
                        'rgba(139, 92, 246, 0.6)',
                        'rgba(239, 68, 68, 0.6)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function esportaExcel() {
            // Implementa esportazione Excel
            alert('Esportazione Excel - da implementare');
        }

        function round(value, decimals) {
            return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals);
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>