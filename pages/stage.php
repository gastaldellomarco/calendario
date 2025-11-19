<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$anno_scolastico_corrente = Database::queryOne(
    "SELECT id FROM anni_scolastici WHERE attivo = 1"
);

$filtro_classe = $_GET['classe_id'] ?? '';
$filtro_stato = $_GET['stato'] ?? '';
$filtro_periodo = $_GET['periodo'] ?? '';

$where_conditions = ["c.anno_scolastico_id = ?"];
$params = [$anno_scolastico_corrente['id']];

if ($filtro_classe) {
    $where_conditions[] = "sp.classe_id = ?";
    $params[] = $filtro_classe;
}

if ($filtro_stato) {
    $where_conditions[] = "sp.stato = ?";
    $params[] = $filtro_stato;
}

if ($filtro_periodo === 'in_corso') {
    $where_conditions[] = "CURDATE() BETWEEN sp.data_inizio AND sp.data_fine";
} elseif ($filtro_periodo === 'futuri') {
    $where_conditions[] = "sp.data_inizio > CURDATE()";
} elseif ($filtro_periodo === 'passati') {
    $where_conditions[] = "sp.data_fine < CURDATE()";
}

$where_sql = implode(" AND ", $where_conditions);

$stage = Database::queryAll("
    SELECT 
        sp.*,
        c.nome as classe_nome,
        p.nome as percorso_nome,
        CONCAT(d.cognome, ' ', d.nome) as tutor_scolastico,
        st.nome_tutor_aziendale,
        st.azienda
    FROM stage_periodi sp
    JOIN classi c ON sp.classe_id = c.id
    JOIN percorsi_formativi p ON c.percorso_formativo_id = p.id
    LEFT JOIN stage_tutor st ON sp.id = st.stage_periodo_id
    LEFT JOIN docenti d ON st.docente_id = d.id
    WHERE $where_sql
    ORDER BY sp.data_inizio DESC
", $params);

$classi = Database::queryAll("
    SELECT id, nome FROM classi 
    WHERE anno_scolastico_id = ? AND stato = 'attiva'
    ORDER BY nome
", [$anno_scolastico_corrente['id']]);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Stage - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Gestione Stage</h1>
            <a href="stage_form.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> Nuovo Stage
            </a>
        </div>

        <!-- Filtri -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                    <select name="stato" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Tutti gli stati</option>
                        <option value="pianificato" <?php echo $filtro_stato == 'pianificato' ? 'selected' : ''; ?>>Pianificato</option>
                        <option value="in_corso" <?php echo $filtro_stato == 'in_corso' ? 'selected' : ''; ?>>In Corso</option>
                        <option value="completato" <?php echo $filtro_stato == 'completato' ? 'selected' : ''; ?>>Completato</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                    <select name="periodo" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Tutti i periodi</option>
                        <option value="in_corso" <?php echo $filtro_periodo == 'in_corso' ? 'selected' : ''; ?>>In Corso</option>
                        <option value="futuri" <?php echo $filtro_periodo == 'futuri' ? 'selected' : ''; ?>>Futuri</option>
                        <option value="passati" <?php echo $filtro_periodo == 'passati' ? 'selected' : ''; ?>>Passati</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg w-full">
                        <i class="fas fa-filter mr-2"></i> Filtra
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabella Stage -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periodo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ore</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progresso</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($stage as $s): 
                            $progresso = $s['ore_totali_previste'] > 0 ? 
                                round(($s['ore_effettuate'] / $s['ore_totali_previste']) * 100, 1) : 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($s['classe_nome']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($s['percorso_nome']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo date('d/m/Y', strtotime($s['data_inizio'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($s['data_fine'])); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php 
                                        $giorni = (strtotime($s['data_fine']) - strtotime($s['data_inizio'])) / (60 * 60 * 24) + 1;
                                        echo $giorni . ' giorni';
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo $s['ore_effettuate']; ?> / <?php echo $s['ore_totali_previste']; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-green-600 h-2 rounded-full" 
                                             style="width: <?php echo min($progresso, 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo $progresso; ?>%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <?php if ($s['tutor_scolastico']): ?>
                                        <div class="text-gray-900"><?php echo htmlspecialchars($s['tutor_scolastico']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($s['azienda']): ?>
                                        <div class="text-gray-500 text-xs"><?php echo htmlspecialchars($s['azienda']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $stato_colors = [
                                    'pianificato' => 'bg-yellow-100 text-yellow-800',
                                    'in_corso' => 'bg-blue-100 text-blue-800',
                                    'completato' => 'bg-green-100 text-green-800',
                                    'cancellato' => 'bg-red-100 text-red-800'
                                ];
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $stato_colors[$s['stato']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo ucfirst($s['stato']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="stage_dettaglio.php?id=<?php echo $s['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i> Dettaglio
                                </a>
                                <a href="stage_form.php?id=<?php echo $s['id']; ?>" 
                                   class="text-green-600 hover:text-green-900 mr-3">
                                    <i class="fas fa-edit"></i> Modifica
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($stage)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                Nessuno stage trovato con i filtri selezionati.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>