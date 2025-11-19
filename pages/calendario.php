<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_page = 'calendario';
$page_title = 'Calendario Lezioni';

// Filtri di default - Leggi tutti i parametri possibili
$sede_id = $_GET['sede_id'] ?? '';
$classe_id = $_GET['classe_id'] ?? '';
$docente_id = $_GET['docente_id'] ?? '';
$settimana = $_GET['settimana'] ?? date('Y-W');
$mese = $_GET['mese'] ?? date('Y-m');
$anno_scolastico_id = $_GET['anno_scolastico_id'] ?? '';
$tab_attivo = $_GET['tab'] ?? 'settimanale'; // Nuovo parametro per il tab

// ✅ CORREZIONE: Gestione sicura della data
try {
    // Per vista settimanale
    if (!preg_match('/^\d{4}-W\d{1,2}$/', $settimana)) {
        $settimana = date('Y-W');
    }
    
    $data_inizio = DateTime::createFromFormat('Y-W', $settimana);
    if ($data_inizio === false) {
        throw new Exception('Formato settimana non valido');
    }
    $data_inizio->modify('monday this week');
    
    $data_fine = clone $data_inizio;
    $data_fine->modify('sunday this week');
    
    // Per vista mensile
    if (!preg_match('/^\d{4}-\d{2}$/', $mese)) {
        $mese = date('Y-m');
    }
    
    $data_mese_inizio = DateTime::createFromFormat('Y-m-d', $mese . '-01');
    $data_mese_fine = clone $data_mese_inizio;
    $data_mese_fine->modify('last day of this month');
    
} catch (Exception $e) {
    // Fallback a date correnti in caso di errore
    error_log("Errore data: " . $e->getMessage());
    $settimana = date('Y-W');
    $mese = date('Y-m');
    
    $data_inizio = new DateTime();
    $data_inizio->modify('monday this week');
    $data_fine = clone $data_inizio;
    $data_fine->modify('sunday this week');
    
    $data_mese_inizio = new DateTime('first day of this month');
    $data_mese_fine = new DateTime('last day of this month');
}

// Ottieni dati per filtri
try {
    $sedi = $db->query("SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome")->fetchAll();
    $classi = $db->query("SELECT id, nome FROM classi WHERE stato = 'attiva' ORDER BY nome")->fetchAll();
    $docenti = $db->query("SELECT id, CONCAT(cognome, ' ', nome) as nome FROM docenti WHERE stato = 'attivo' ORDER BY cognome, nome")->fetchAll();
    $anni_scolastici = $db->query("SELECT id, anno FROM anni_scolastici ORDER BY data_inizio DESC")->fetchAll();
} catch (Exception $e) {
    error_log("Errore caricamento filtri: " . $e->getMessage());
    $sedi = $classi = $docenti = $anni_scolastici = [];
}

include '../includes/header.php';

// ✅ FUNZIONE: Genera URL con tutti i parametri
function generaUrl($parametri_extra = []) {
    $parametri_base = [
        'sede_id' => $_GET['sede_id'] ?? '',
        'classe_id' => $_GET['classe_id'] ?? '',
        'docente_id' => $_GET['docente_id'] ?? '',
        'anno_scolastico_id' => $_GET['anno_scolastico_id'] ?? '',
        'settimana' => $_GET['settimana'] ?? '',
        'mese' => $_GET['mese'] ?? '',
        'tab' => $_GET['tab'] ?? ''
    ];
    
    $parametri = array_merge($parametri_base, $parametri_extra);
    $parametri = array_filter($parametri); // Rimuovi valori vuoti
    
    return '?' . http_build_query($parametri);
}
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header e Filtri -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4 lg:mb-0">Calendario Lezioni</h1>
            
            <!-- Navigazione Settimana -->
            <div class="flex items-center space-x-2" id="navigazioneSettimana">
                <?php
                $prev_week = (clone $data_inizio)->modify('-1 week')->format('Y-W');
                $next_week = (clone $data_inizio)->modify('+1 week')->format('Y-W');
                $today_week = date('Y-W');
                ?>
                <a href="<?= generaUrl(['settimana' => $prev_week, 'tab' => $tab_attivo]) ?>" 
                   class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </a>
                
                <span class="text-lg font-semibold px-4">
                    <?= $data_inizio->format('d/m/Y') ?> - <?= $data_fine->format('d/m/Y') ?>
                </span>
                
                <a href="<?= generaUrl(['settimana' => $next_week, 'tab' => $tab_attivo]) ?>" 
                   class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </a>
                
                <a href="<?= generaUrl(['settimana' => $today_week, 'tab' => $tab_attivo]) ?>" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors ml-2">
                    Oggi
                </a>
            </div>
            
            <!-- Navigazione Mese -->
            <div class="flex items-center space-x-2 hidden" id="navigazioneMese">
                <?php
                $prev_month = (clone $data_mese_inizio)->modify('-1 month')->format('Y-m');
                $next_month = (clone $data_mese_inizio)->modify('+1 month')->format('Y-m');
                $today_month = date('Y-m');
                ?>
                <a href="<?= generaUrl(['mese' => $prev_month, 'tab' => $tab_attivo]) ?>" 
                   class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </a>
                
                <span class="text-lg font-semibold px-4">
                    <?= $data_mese_inizio->format('F Y') ?>
                </span>
                
                <a href="<?= generaUrl(['mese' => $next_month, 'tab' => $tab_attivo]) ?>" 
                   class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </a>
                
                <a href="<?= generaUrl(['mese' => $today_month, 'tab' => $tab_attivo]) ?>" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors ml-2">
                    Oggi
                </a>
            </div>
        </div>

        <!-- Filtri -->
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4" id="filtriForm">
            <!-- Input nascosti per mantenere i parametri -->
            <input type="hidden" name="tab" value="<?= $tab_attivo ?>" id="inputTab">
            <input type="hidden" name="settimana" value="<?= $settimana ?>" id="inputSettimana">
            <input type="hidden" name="mese" value="<?= $mese ?>" id="inputMese">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sede</label>
                <select name="sede_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tutte le sedi</option>
                    <?php foreach ($sedi as $sede): ?>
                        <option value="<?= $sede['id'] ?>" <?= $sede_id == $sede['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sede['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="filtroClasse">
                <label class="block text-sm font-medium text-gray-700 mb-1">Classe</label>
                <select name="classe_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tutte le classi</option>
                    <?php foreach ($classi as $classe): ?>
                        <option value="<?= $classe['id'] ?>" <?= $classe_id == $classe['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Docente</label>
                <select name="docente_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tutti i docenti</option>
                    <?php foreach ($docenti as $docente): ?>
                        <option value="<?= $docente['id'] ?>" <?= $docente_id == $docente['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($docente['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Anno Scolastico</label>
                <select name="anno_scolastico_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tutti</option>
                    <?php foreach ($anni_scolastici as $anno): ?>
                        <option value="<?= $anno['id'] ?>" <?= $anno_scolastico_id == $anno['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($anno['anno']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors w-full">
                    <i class="fas fa-filter mr-2"></i>Applica Filtri
                </button>
            </div>
        </form>
        
        <!-- Info Filtri Attivi -->
        <?php if ($sede_id || $classe_id || $docente_id || $anno_scolastico_id): ?>
        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="text-sm text-blue-700">
                    <strong>Filtri attivi:</strong>
                    <?php
                    $filtri_attivi = [];
                    if ($sede_id) {
                        $sede_nome = array_column($sedi, 'nome', 'id')[$sede_id] ?? 'Sede ' . $sede_id;
                        $filtri_attivi[] = "Sede: " . htmlspecialchars($sede_nome);
                    }
                    if ($classe_id) {
                        $classe_nome = array_column($classi, 'nome', 'id')[$classe_id] ?? 'Classe ' . $classe_id;
                        $filtri_attivi[] = "Classe: " . htmlspecialchars($classe_nome);
                    }
                    if ($docente_id) {
                        $docente_nome = array_column($docenti, 'nome', 'id')[$docente_id] ?? 'Docente ' . $docente_id;
                        $filtri_attivi[] = "Docente: " . htmlspecialchars($docente_nome);
                    }
                    if ($anno_scolastico_id) {
                        $anno_nome = array_column($anni_scolastici, 'anno', 'id')[$anno_scolastico_id] ?? 'Anno ' . $anno_scolastico_id;
                        $filtri_attivi[] = "Anno: " . htmlspecialchars($anno_nome);
                    }
                    echo implode(' • ', $filtri_attivi);
                    ?>
                </div>
                <a href="?" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fas fa-times mr-1"></i>Rimuovi filtri
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabs Vista -->
    <div class="bg-white rounded-lg shadow-md">
        <!-- Tab Header -->
        <div class="border-b">
            <nav class="flex -mb-px">
                <a href="<?= generaUrl(['tab' => 'settimanale']) ?>" 
                   class="tab-link py-4 px-6 text-center border-b-2 font-medium text-sm whitespace-nowrap flex-1 <?= $tab_attivo === 'settimanale' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-calendar-week mr-2"></i>Vista Settimanale
                </a>
                <a href="<?= generaUrl(['tab' => 'mensile']) ?>" 
                   class="tab-link py-4 px-6 text-center border-b-2 font-medium text-sm whitespace-nowrap flex-1 <?= $tab_attivo === 'mensile' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-calendar-alt mr-2"></i>Vista Mensile
                </a>
                <a href="<?= generaUrl(['tab' => 'docente_settimanale']) ?>" 
                   class="tab-link py-4 px-6 text-center border-b-2 font-medium text-sm whitespace-nowrap flex-1 <?= $tab_attivo === 'docente_settimanale' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>Docente Settimanale
                </a>
                <a href="<?= generaUrl(['tab' => 'docente_mensile']) ?>" 
                   class="tab-link py-4 px-6 text-center border-b-2 font-medium text-sm whitespace-nowrap flex-1 <?= $tab_attivo === 'docente_mensile' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-user-graduate mr-2"></i>Docente Mensile
                </a>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Vista Settimanale -->
            <div id="tab-settimanale" class="tab-content <?= $tab_attivo !== 'settimanale' ? 'hidden' : '' ?>">
                <?php 
                $component_path = __DIR__ . '/../components/calendario_settimanale.php';
                if (file_exists($component_path)) {
                    include $component_path;
                } else {
                    echo '<div class="text-center text-gray-500 py-8">';
                    echo '<i class="fas fa-exclamation-triangle text-3xl mb-4"></i>';
                    echo '<p>Componente calendario settimanale non trovato</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Vista Mensile -->
            <div id="tab-mensile" class="tab-content <?= $tab_attivo !== 'mensile' ? 'hidden' : '' ?>">
                <?php 
                $component_path = __DIR__ . '/../components/calendario_mensile.php';
                if (file_exists($component_path)) {
                    include $component_path;
                } else {
                    echo '<div class="text-center text-gray-500 py-8">';
                    echo '<i class="fas fa-calendar-alt text-3xl mb-4"></i>';
                    echo '<p>Vista mensile - In sviluppo</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Vista Docente Settimanale -->
            <div id="tab-docente_settimanale" class="tab-content <?= $tab_attivo !== 'docente_settimanale' ? 'hidden' : '' ?>">
                <?php 
                $component_path = __DIR__ . '/../components/calendario_docente_settimanale.php';
                if (file_exists($component_path)) {
                    include $component_path;
                } else {
                    echo '<div class="text-center text-gray-500 py-8">';
                    echo '<i class="fas fa-chalkboard-teacher text-3xl mb-4"></i>';
                    echo '<p>Vista docente settimanale - In sviluppo</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Vista Docente Mensile -->
            <div id="tab-docente_mensile" class="tab-content <?= $tab_attivo !== 'docente_mensile' ? 'hidden' : '' ?>">
                <?php 
                $component_path = __DIR__ . '/../components/calendario_docente_mensile.php';
                if (file_exists($component_path)) {
                    include $component_path;
                } else {
                    echo '<div class="text-center text-gray-500 py-8">';
                    echo '<i class="fas fa-user-graduate text-3xl mb-4"></i>';
                    echo '<p>Vista docente mensile - In sviluppo</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendario.css">
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inizializza stato navigazione in base al tab attivo
    const tabAttivo = '<?= $tab_attivo ?>';
    
    if (tabAttivo === 'settimanale' || tabAttivo === 'docente_settimanale') {
        document.getElementById('navigazioneSettimana').classList.remove('hidden');
        document.getElementById('navigazioneMese').classList.add('hidden');
    } else {
        document.getElementById('navigazioneSettimana').classList.add('hidden');
        document.getElementById('navigazioneMese').classList.remove('hidden');
    }
    
    // Mostra/nascondi filtro classe in base al tab
    if (tabAttivo === 'docente_settimanale' || tabAttivo === 'docente_mensile') {
        document.getElementById('filtroClasse').classList.add('hidden');
    } else {
        document.getElementById('filtroClasse').classList.remove('hidden');
    }

    // Gestione cambio tab via JavaScript (per UX)
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const url = new URL(this.href);
            const nuovoTab = url.searchParams.get('tab');
            
            // Aggiorna input nascosto
            document.getElementById('inputTab').value = nuovoTab;
            
            // Se stai cambiando tra settimanale/mensile, aggiorna anche gli input nascosti
            if (nuovoTab === 'settimanale' || nuovoTab === 'docente_settimanale') {
                document.getElementById('inputSettimana').name = 'settimana';
                document.getElementById('inputMese').name = '';
            } else {
                document.getElementById('inputSettimana').name = '';
                document.getElementById('inputMese').name = 'mese';
            }
            
            // Il form non viene inviato, l'utente segue il link normalmente
        });
    });

    // Gestione submit form - assicurati che il tab sia incluso
    document.getElementById('filtriForm').addEventListener('submit', function(e) {
        // Il tab è già nell'input nascosto, non serve fare nulla
    });
});

// Funzione per cambiare tab mantenendo i filtri
function cambiaTab(nuovoTab) {
    const form = document.getElementById('filtriForm');
    document.getElementById('inputTab').value = nuovoTab;
    form.submit();
}
</script>

<?php include '../includes/footer.php'; ?>