<?php
// dashboard.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$current_page = "dashboard";
$page_title = "Dashboard";

// Connessione al database
try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupero statistiche
try {
    // Totale Docenti
    $stmt_docenti = $pdo->query("SELECT COUNT(*) as total FROM docenti WHERE stato = 'attivo'");
    $total_docenti = $stmt_docenti->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Totale Classi
    $stmt_classi = $pdo->query("SELECT COUNT(*) as total FROM classi WHERE stato = 'attiva'");
    $total_classi = $stmt_classi->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ore Pianificate questa settimana
    $inizio_settimana = date('Y-m-d', strtotime('monday this week'));
    $fine_settimana = date('Y-m-d', strtotime('sunday this week'));
    $stmt_ore = $pdo->prepare("
        SELECT SUM(ore_effettive) as total_ore 
        FROM calendario_lezioni 
        WHERE data_lezione BETWEEN ? AND ? AND stato IN ('pianificata', 'confermata')
    ");
    $stmt_ore->execute([$inizio_settimana, $fine_settimana]);
    $total_ore = $stmt_ore->fetch(PDO::FETCH_ASSOC)['total_ore'] ?? 0;
    
    // Conflitti Aperti
    $stmt_conflitti = $pdo->query("SELECT COUNT(*) as total FROM conflitti_orario WHERE risolto = 0");
    $total_conflitti = $stmt_conflitti->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
    error_log("Errore nel recupero statistiche: " . $e->getMessage());
    $total_docenti = $total_classi = $total_ore = $total_conflitti = 0;
}

// Recupero notifiche non lette
try {
    $stmt_notifiche = $pdo->prepare("
        SELECT id, tipo, priorita, titolo, messaggio, created_at 
        FROM notifiche 
        WHERE utente_id = ? AND letta = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt_notifiche->execute([$_SESSION['user_id']]);
    $notifiche = $stmt_notifiche->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero notifiche: " . $e->getMessage());
    $notifiche = [];
}

// Recupero slot orari per calendario
try {
    $stmt_slot = $pdo->query("SELECT id, numero_slot, ora_inizio, ora_fine FROM orari_slot WHERE attivo = 1 ORDER BY numero_slot");
    $slot_orari = $stmt_slot->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero slot orari: " . $e->getMessage());
    $slot_orari = [];
}

// Recupero lezioni per la settimana corrente
try {
    $stmt_lezioni = $pdo->prepare("
        SELECT cl.data_lezione, cl.slot_id, cl.classe_id, cl.materia_id, cl.docente_id, cl.aula_id, cl.stato,
               c.nome as classe_nome, m.nome as materia_nome, m.tipo as materia_tipo,
               CONCAT(d.cognome, ' ', d.nome) as docente_nome,
               a.nome as aula_nome
        FROM calendario_lezioni cl
        LEFT JOIN classi c ON cl.classe_id = c.id
        LEFT JOIN materie m ON cl.materia_id = m.id
        LEFT JOIN docenti d ON cl.docente_id = d.id
        LEFT JOIN aule a ON cl.aula_id = a.id
        WHERE cl.data_lezione BETWEEN ? AND ?
        ORDER BY cl.data_lezione, cl.slot_id
    ");
    $stmt_lezioni->execute([$inizio_settimana, $fine_settimana]);
    $lezioni_settimana = $stmt_lezioni->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero lezioni: " . $e->getMessage());
    $lezioni_settimana = [];
}

// Organizza lezioni per data e slot per facilitare la visualizzazione
$lezioni_per_giorno = [];
foreach ($lezioni_settimana as $lezione) {
    $data = $lezione['data_lezione'];
    $slot_id = $lezione['slot_id'];
    if (!isset($lezioni_per_giorno[$data])) {
        $lezioni_per_giorno[$data] = [];
    }
    $lezioni_per_giorno[$data][$slot_id] = $lezione;
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-600 mt-1">Benvenuto, <?php echo htmlspecialchars(getLoggedUserName()); ?> 
                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded ml-2"><?php echo htmlspecialchars(getLoggedUserRole()); ?></span></p>
                </div>
                <div class="text-sm text-gray-500">
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Sezione Statistiche -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Card Docenti -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chalkboard-teacher text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-3xl font-bold text-gray-900"><?php echo $total_docenti; ?></h3>
                        <p class="text-gray-600">Docenti Attivi</p>
                    </div>
                </div>
            </div>

            <!-- Card Classi -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-3xl font-bold text-gray-900"><?php echo $total_classi; ?></h3>
                        <p class="text-gray-600">Classi Attive</p>
                    </div>
                </div>
            </div>

            <!-- Card Ore Settimanali -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-3xl font-bold text-gray-900"><?php echo number_format($total_ore, 1); ?></h3>
                        <p class="text-gray-600">Ore Questa Settimana</p>
                    </div>
                </div>
            </div>

            <!-- Card Conflitti -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-3xl font-bold text-gray-900"><?php echo $total_conflitti; ?></h3>
                        <p class="text-gray-600">Conflitti Aperti</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Colonna sinistra: Calendario e Azioni Rapide -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Calendario Settimanale -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Calendario Settimanale</h2>
                        <p class="text-gray-600 text-sm"><?php echo date('d/m/Y', strtotime($inizio_settimana)); ?> - <?php echo date('d/m/Y', strtotime($fine_settimana)); ?></p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orario</th>
                                    <?php
                                    $giorni_settimana = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
                                    for ($i = 0; $i < 6; $i++) {
                                        $data_giorno = date('Y-m-d', strtotime($inizio_settimana . " +$i days"));
                                        $classe_oggi = (date('Y-m-d') == $data_giorno) ? 'bg-blue-50' : '';
                                        echo "<th class=\"px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider $classe_oggi\">";
                                        echo $giorni_settimana[$i] . "<br><span class=\"text-xs font-normal\">" . date('d/m', strtotime($data_giorno)) . "</span>";
                                        echo "</th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($slot_orari as $slot): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 border-r">
                                        <?php echo date('H:i', strtotime($slot['ora_inizio'])); ?>-<?php echo date('H:i', strtotime($slot['ora_fine'])); ?>
                                    </td>
                                    <?php
                                    for ($i = 0; $i < 6; $i++) {
                                        $data_giorno = date('Y-m-d', strtotime($inizio_settimana . " +$i days"));
                                        $classe_oggi = (date('Y-m-d') == $data_giorno) ? 'bg-blue-50' : '';
                                        $lezione = $lezioni_per_giorno[$data_giorno][$slot['id']] ?? null;
                                        
                                        echo "<td class=\"px-2 py-2 text-center text-xs border-r $classe_oggi\" style=\"min-width: 120px;\">";
                                        
                                        if ($lezione) {
                                            $colori_materie = [
                                                'culturale' => 'bg-blue-100 border-blue-300 text-blue-800',
                                                'professionale' => 'bg-green-100 border-green-300 text-green-800',
                                                'laboratoriale' => 'bg-purple-100 border-purple-300 text-purple-800',
                                                'stage' => 'bg-yellow-100 border-yellow-300 text-yellow-800',
                                                'sostegno' => 'bg-red-100 border-red-300 text-red-800'
                                            ];
                                            $tipo_materia = $lezione['materia_tipo'] ?? 'culturale';
                                            $colore = $colori_materie[$tipo_materia] ?? 'bg-gray-100 border-gray-300 text-gray-800';
                                            
                                            echo "<div class=\"lezione-tooltip $colore border rounded p-1 cursor-help\" 
                                                  data-tooltip=\"" . htmlspecialchars(
                                                $lezione['classe_nome'] . " - " . 
                                                $lezione['materia_nome'] . "\\n" .
                                                "Docente: " . $lezione['docente_nome'] . "\\n" .
                                                "Aula: " . ($lezione['aula_nome'] ?? 'N/A')
                                            ) . "\">";
                                            echo "<div class=\"font-medium truncate\">" . htmlspecialchars($lezione['classe_nome']) . "</div>";
                                            echo "<div class=\"truncate text-xs\">" . htmlspecialchars($lezione['materia_nome']) . "</div>";
                                            echo "</div>";
                                        } else {
                                            echo "<span class=\"text-gray-400\">-</span>";
                                        }
                                        
                                        echo "</td>";
                                    }
                                    ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Legenda -->
                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-wrap items-center gap-4 text-xs">
                            <span class="font-medium">Legenda:</span>
                            <span class="flex items-center"><span class="w-3 h-3 bg-blue-100 border border-blue-300 mr-1"></span>Culturale</span>
                            <span class="flex items-center"><span class="w-3 h-3 bg-green-100 border border-green-300 mr-1"></span>Professionale</span>
                            <span class="flex items-center"><span class="w-3 h-3 bg-purple-100 border border-purple-300 mr-1"></span>Laboratorio</span>
                            <span class="flex items-center"><span class="w-3 h-3 bg-yellow-100 border border-yellow-300 mr-1"></span>Stage</span>
                            <span class="flex items-center"><span class="w-3 h-3 bg-red-100 border border-red-300 mr-1"></span>Sostegno</span>
                        </div>
                    </div>
                </div>

                <!-- Azioni Rapide -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Azioni Rapide</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                            <button onclick="generaCalendario()" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                <i class="fas fa-calendar-plus text-blue-600 text-2xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Genera Calendario</span>
                            </button>
                            
                            <a href="<?php echo BASE_URL; ?>/pages/docente_form.php?action=add" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                                <i class="fas fa-user-plus text-green-600 text-2xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Aggiungi Docente</span>
                            </a>
                            
                            <a href="<?php echo BASE_URL; ?>/pages/classe_form.php?action=add" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                                <i class="fas fa-users text-purple-600 text-2xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Aggiungi Classe</span>
                            </a>
                            
                            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="flex flex-col items-center justify-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors">
                                <i class="fas fa-exchange-alt text-yellow-600 text-2xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Gestisci Sostituzioni</span>
                            </a>
                            
                            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="flex flex-col items-center justify-center p-4 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                <i class="fas fa-chart-bar text-red-600 text-2xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Visualizza Report</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonna destra: Notifiche -->
            <div class="space-y-8">
                <!-- Notifiche Recenti -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">Notifiche Recenti</h2>
                        <a href="notifiche.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Vedi tutte</a>
                    </div>
                    <div class="divide-y divide-gray-200">
                        <?php if (empty($notifiche)): ?>
                            <div class="p-6 text-center text-gray-500">
                                <i class="fas fa-bell-slash text-2xl mb-2"></i>
                                <p>Nessuna notifica non letta</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifiche as $notifica): ?>
                            <div class="p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $notifica['priorita'] == 'alta' ? 'bg-red-100 text-red-800' : 
                                               ($notifica['priorita'] == 'media' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($notifica['priorita']); ?>
                                    </span>
                                    <button onclick="segnaComeLetta(<?php echo $notifica['id']; ?>)" class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                                <h3 class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($notifica['titolo']); ?></h3>
                                <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($notifica['messaggio']); ?></p>
                                <span class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($notifica['created_at'])); ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Sistema -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Info Sistema</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Versione DB:</span>
                            <span class="font-medium">2.0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Utenti Online:</span>
                            <span class="font-medium">1</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Ultimo Backup:</span>
                            <span class="font-medium">Oggi 08:00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Font Awesome per le icone -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Tooltip Script -->
<script>
// Tooltip per le lezioni
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('.lezione-tooltip');
    
    tooltips.forEach(tooltip => {
        const tooltipText = tooltip.getAttribute('data-tooltip');
        
        tooltip.addEventListener('mouseenter', function(e) {
            const tooltipEl = document.createElement('div');
            tooltipEl.className = 'fixed z-50 px-3 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg shadow-sm tooltip-popup';
            tooltipEl.textContent = tooltipText.replace(/\\n/g, '\n');
            document.body.appendChild(tooltipEl);
            
            const rect = tooltip.getBoundingClientRect();
            tooltipEl.style.left = (rect.left + rect.width/2 - tooltipEl.offsetWidth/2) + 'px';
            tooltipEl.style.top = (rect.top - tooltipEl.offsetHeight - 10) + 'px';
            
            tooltip._tooltipEl = tooltipEl;
        });
        
        tooltip.addEventListener('mouseleave', function() {
            if (tooltip._tooltipEl) {
                tooltip._tooltipEl.remove();
            }
        });
    });
});

// Funzione per generare calendario
function generaCalendario() {
    if (confirm('Vuoi generare il calendario automaticamente per i prossimi 30 giorni?')) {
        // Mostra loading
        const originalText = event.target.innerHTML;
        event.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generazione...';
        event.target.disabled = true;
        
        // Simula chiamata API (da implementare)
        setTimeout(() => {
            alert('Calendario generato con successo!');
            event.target.innerHTML = originalText;
            event.target.disabled = false;
            location.reload();
        }, 2000);
    }
}

// Funzione per segnare notifica come letta
function segnaComeLetta(notificaId) {
    fetch('api/segna_notifica_letta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ notifica_id: notificaId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Rimuove la notifica dalla vista
            const notificaEl = document.querySelector(`[onclick="segnaComeLetta(${notificaId})"]`).closest('.p-4');
            notificaEl.style.opacity = '0';
            setTimeout(() => notificaEl.remove(), 300);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore durante l\'aggiornamento della notifica');
    });
}
</script>

<style>
.tooltip-popup {
    white-space: pre-line;
    max-width: 300px;
    z-index: 10000;
    pointer-events: none;
}

.lezione-tooltip {
    transition: all 0.2s ease;
    min-height: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.lezione-tooltip:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>