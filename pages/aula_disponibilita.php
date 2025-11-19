<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'aula_disponibilita';
$page_title = 'Disponibilità Aula';

$aula_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$aula = null;
$disponibilita = [];

try {
    $pdo = getPDOConnection();
    
    // Carica aula
    $stmt = $pdo->prepare("SELECT a.*, s.nome as sede_nome FROM aule a LEFT JOIN sedi s ON a.sede_id = s.id WHERE a.id = ?");
    $stmt->execute([$aula_id]);
    $aula = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$aula) {
        $_SESSION['error'] = "Aula non trovata";
        header('Location: aule.php');
        exit;
    }
    
    // Carica lezioni assegnate a questa aula
    $stmt = $pdo->prepare("
        SELECT c.*, cl.nome as classe_nome, d.cognome, d.nome as docente_nome, m.nome as materia_nome 
        FROM calendario_lezioni c
        LEFT JOIN classi cl ON c.classe_id = cl.id
        LEFT JOIN docenti d ON c.docente_id = d.id
        LEFT JOIN materie m ON c.materia_id = m.id
        WHERE c.aula_id = ?
        ORDER BY c.data_lezione, c.ora_inizio
    ");
    $stmt->execute([$aula_id]);
    $lezioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcola statistiche
    $total_lezioni = count($lezioni);
    $ore_totali = 0;
    $giorni_utilizzati = [];
    $classi_utilizzate = [];
    
    foreach ($lezioni as $lezione) {
        // Calcola ore
        $start = new DateTime($lezione['ora_inizio']);
        $end = new DateTime($lezione['ora_fine']);
        $interval = $start->diff($end);
        $ore_totali += $interval->h + ($interval->i / 60);
        
        // Registra giorni utilizzati
        $data = new DateTime($lezione['data_lezione']);
        $giorni_utilizzati[$data->format('Y-m-d')] = true;
        
        // Registra classi utilizzate
        if ($lezione['classe_nome'] && !isset($classi_utilizzate[$lezione['classe_nome']])) {
            $classi_utilizzate[$lezione['classe_nome']] = true;
        }
    }
    
} catch (Exception $e) {
    error_log("Errore: " . $e->getMessage());
    $_SESSION['error'] = "Errore nel caricamento dati";
    header('Location: aule.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($aula['nome']) ?></h1>
            <p class="text-gray-600 mt-1"><?= htmlspecialchars($aula['sede_nome']) ?? 'Sede' ?></p>
        </div>
        <div class="space-x-2">
            <a href="aula_form.php?id=<?= $aula['id'] ?>" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg inline-block">
                <i class="fas fa-edit mr-2"></i>Modifica
            </a>
            <a href="aule.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg inline-block">
                <i class="fas fa-arrow-left mr-2"></i>Torna
            </a>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-gray-500 text-sm">Lezioni Programmate</div>
            <div class="text-3xl font-bold text-blue-600"><?= $total_lezioni ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-gray-500 text-sm">Ore Totali</div>
            <div class="text-3xl font-bold text-green-600"><?= number_format($ore_totali, 1, ',', '.') ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-gray-500 text-sm">Giorni Utilizzati</div>
            <div class="text-3xl font-bold text-purple-600"><?= count($giorni_utilizzati) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-gray-500 text-sm">Classi Diverse</div>
            <div class="text-3xl font-bold text-orange-600"><?= count($classi_utilizzate) ?></div>
        </div>
    </div>

    <!-- Info Aula -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Informazioni Aula</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <span class="text-gray-600">Codice:</span>
                <p class="text-lg font-semibold"><?= htmlspecialchars($aula['codice']) ?></p>
            </div>
            <div>
                <span class="text-gray-600">Capienza:</span>
                <p class="text-lg font-semibold"><?= $aula['capienza'] ? htmlspecialchars($aula['capienza']) . ' posti' : '-' ?></p>
            </div>
            <div>
                <span class="text-gray-600">Tipo:</span>
                <p class="text-lg font-semibold"><?= htmlspecialchars($aula['tipo'] ?? 'Generica') ?></p>
            </div>
            <div>
                <span class="text-gray-600">Piano:</span>
                <p class="text-lg font-semibold"><?= $aula['piano'] ? htmlspecialchars($aula['piano']) . '°' : '-' ?></p>
            </div>
            <div>
                <span class="text-gray-600">Attrezzature:</span>
                <p class="text-lg font-semibold"><?= htmlspecialchars($aula['attrezzature'] ?? '-') ?></p>
            </div>
            <div>
                <span class="text-gray-600">Stato:</span>
                <p class="text-lg font-semibold">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $aula['attiva'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                        <?= $aula['attiva'] ? 'Attiva' : 'Inattiva' ?>
                    </span>
                </p>
            </div>
        </div>
        <?php if ($aula['note']): ?>
            <div class="mt-4 pt-4 border-t">
                <span class="text-gray-600">Note:</span>
                <p class="text-sm text-gray-700 mt-2"><?= htmlspecialchars($aula['note']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lezioni Programmate -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Lezioni Programmate</h2>
        </div>
        
        <?php if (empty($lezioni)): ?>
            <div class="px-6 py-8 text-center text-gray-500">
                <i class="fas fa-calendar-check text-3xl mb-3"></i>
                <p>Nessuna lezione programmata in questa aula</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orario</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Classe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Docente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Materia</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($lezioni as $lezione): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php 
                                    $data = new DateTime($lezione['data_lezione']);
                                    echo $data->format('d/m/Y');
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php 
                                    $start = new DateTime($lezione['ora_inizio']);
                                    $end = new DateTime($lezione['ora_fine']);
                                    echo $start->format('H:i') . ' - ' . $end->format('H:i');
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($lezione['classe_nome'] ?? '-') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars(($lezione['cognome'] ?? '') . ' ' . ($lezione['docente_nome'] ?? '')) ?: '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($lezione['materia_nome'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
