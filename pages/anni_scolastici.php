<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// ✅ CORRETTO: Imposta current_page e page_title
$current_page = 'anni_scolastici';
$page_title = 'Gestione Anni Scolastici';

// Verifica permessi
if (!in_array($_SESSION['ruolo'], ['preside', 'vice_preside', 'amministratore'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$success = '';

// Gestione azioni
if ($_POST) {
    if (isset($_POST['aggiungi_anno'])) {
        $anno = sanitizeInput($_POST['anno'] ?? '');
        $data_inizio = sanitizeInput($_POST['data_inizio'] ?? '');
        $data_fine = sanitizeInput($_POST['data_fine'] ?? '');
        $settimane_lezione = isset($_POST['settimane_lezione']) ? (int)$_POST['settimane_lezione'] : 0;
        $attivo = isset($_POST['attivo']) ? 1 : 0;

        // Validazione date
        if ($data_fine <= $data_inizio) {
            $error = "Errore: La data fine deve essere successiva alla data inizio";
        } else {
            try {
                // Se si sta attivando un anno, disattiva gli altri
                if ($attivo) {
                    $pdo->exec("UPDATE anni_scolastici SET attivo = 0");
                }

                $stmt = $pdo->prepare("INSERT INTO anni_scolastici (anno, data_inizio, data_fine, settimane_lezione, attivo) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$anno, $data_inizio, $data_fine, $settimane_lezione, $attivo]);
                $success = "Anno scolastico aggiunto con successo";
            } catch (PDOException $e) {
                $error = "Errore nel salvataggio: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['attiva_anno'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        try {
            $pdo->beginTransaction();
            
            // Disattiva tutti gli anni
            $pdo->exec("UPDATE anni_scolastici SET attivo = 0");
            
            // Attiva l'anno selezionato
            $stmt = $pdo->prepare("UPDATE anni_scolastici SET attivo = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            // Aggiorna configurazione
            $stmt = $pdo->prepare("SELECT anno FROM anni_scolastici WHERE id = ?");
            $stmt->execute([$id]);
            $anno_corrente = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE configurazioni SET valore = ? WHERE chiave = 'anno_scolastico_corrente'");
            $stmt->execute([$anno_corrente]);
            
            $pdo->commit();
            $success = "Anno scolastico attivato con successo";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Errore nell'attivazione: " . $e->getMessage();
        }
    }
}

// Carica anni scolastici
try {
    $stmt = $pdo->query("SELECT * FROM anni_scolastici ORDER BY data_inizio DESC");
    $anni = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $anni = [];
    $error = "Errore nel caricamento anni: " . $e->getMessage();
}

// ✅ CORRETTO: Include header DOPO aver impostato $current_page e $page_title
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Pagina -->
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Gestione Anni Scolastici</h1>
    <button onclick="openAnnoForm()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-plus mr-2"></i>Nuovo Anno
    </button>
</div>

<!-- Messaggi di Feedback -->
<?php if ($error): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-800"><?= htmlspecialchars($success) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Form Nuovo Anno -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Nuovo Anno Scolastico</h2>
    <form method="POST" id="annoForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Anno (es: 2024-2025)</label>
            <input type="text" name="anno" pattern="\d{4}-\d{4}" required placeholder="2024-2025"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Data Inizio</label>
            <input type="date" name="data_inizio" id="data_inizio" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Data Fine</label>
            <input type="date" name="data_fine" id="data_fine" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Settimane Lezione</label>
            <input type="number" name="settimane_lezione" id="settimane_lezione" min="1" max="52" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-center space-x-2 pt-6">
            <input type="checkbox" name="attivo" value="1" id="attivo" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
            <label for="attivo" class="text-sm font-medium text-gray-700">Attiva come anno corrente</label>
        </div>
        <div class="flex items-end">
            <button type="submit" name="aggiungi_anno" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium">
                <i class="fas fa-plus mr-2"></i>Aggiungi Anno
            </button>
        </div>
    </form>
    
    <!-- Calcolo Settimane -->
    <div class="mt-4 bg-blue-50 border border-blue-200 rounded-md p-3">
        <p class="text-sm text-blue-800">
            <strong>Calcolo Automatico:</strong> <span id="calcolo_settimane">Inserisci le date per calcolare le settimane</span>
        </p>
    </div>
</div>

<!-- Tabella Anni Scolastici -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Anno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Inizio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Fine</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Settimane</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($anni as $anno): ?>
                <tr class="hover:bg-gray-50 <?= $anno['attivo'] ? 'bg-green-50' : '' ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($anno['anno']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?= date('d/m/Y', strtotime($anno['data_inizio'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?= date('d/m/Y', strtotime($anno['data_fine'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?= $anno['settimane_lezione'] ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            <?= $anno['attivo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $anno['attivo'] ? 'Attivo' : 'Inattivo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <?php if (!$anno['attivo']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $anno['id'] ?>">
                                <button type="submit" name="attiva_anno" 
                                        class="text-green-600 hover:text-green-900 font-medium"
                                        onclick="return confirm('Attivare questo anno scolastico?')">
                                    <i class="fas fa-check mr-1"></i>Attiva
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-gray-400">
                                <i class="fas fa-check-circle"></i> Attivo
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Calcolo automatico settimane
document.getElementById('data_inizio')?.addEventListener('change', calcolaSettimane);
document.getElementById('data_fine')?.addEventListener('change', calcolaSettimane);

function calcolaSettimane() {
    const inizio = document.getElementById('data_inizio').value;
    const fine = document.getElementById('data_fine').value;
    
    if (inizio && fine) {
        const start = new Date(inizio);
        const end = new Date(fine);
        
        if (end > start) {
            const diffTime = Math.abs(end - start);
            const diffWeeks = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 7));
            document.getElementById('settimane_lezione').value = diffWeeks;
            document.getElementById('calcolo_settimane').textContent = 
                `Calcolate automaticamente ${diffWeeks} settimane di lezione`;
        }
    }
}

function openAnnoForm() {
    alert('Modulo visualizzato sopra. Compila il form e clicca "Aggiungi Anno"');
}
</script>

    <script>
        // Calcolo automatico settimane
        document.getElementById('data_inizio').addEventListener('change', calcolaSettimane);
        document.getElementById('data_fine').addEventListener('change', calcolaSettimane);

        function calcolaSettimane() {
            const inizio = document.getElementById('data_inizio').value;
            const fine = document.getElementById('data_fine').value;
            
            if (inizio && fine) {
                const start = new Date(inizio);
                const end = new Date(fine);
                
                if (end > start) {
                    const diffTime = Math.abs(end - start);
                    const diffWeeks = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 7));
                    document.getElementById('settimane_lezione').value = diffWeeks;
                    document.getElementById('calcolo_settimane').textContent = 
                        `Calcolate automaticamente ${diffWeeks} settimane di lezione`;
                }
            }
        }

        function modificaAnno(id) {
            window.location.href = 'anno_form.php?id=' + id;
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>