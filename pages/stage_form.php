<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$stage_id = $_GET['id'] ?? null;
$stage = null;
$tutor = null;

if ($stage_id) {
    $stage = Database::queryOne("
        SELECT sp.*, c.nome as classe_nome, c.anno_scolastico_id
        FROM stage_periodi sp 
        JOIN classi c ON sp.classe_id = c.id 
        WHERE sp.id = ?
    ", [$stage_id]);
    
    if ($stage) {
        $tutor = Database::queryOne("
            SELECT * FROM stage_tutor WHERE stage_periodo_id = ?
        ", [$stage_id]);
    }
}

$anno_scolastico_corrente = Database::queryOne(
    "SELECT id FROM anni_scolastici WHERE attivo = 1"
);

$classi = Database::queryAll("
    SELECT id, nome FROM classi 
    WHERE anno_scolastico_id = ? AND stato = 'attiva'
    ORDER BY nome
", [$anno_scolastico_corrente['id']]);

$docenti = Database::queryAll("
    SELECT id, CONCAT(cognome, ' ', nome) as nome_completo 
    FROM docenti 
    WHERE stato = 'attivo'
    ORDER BY cognome, nome
");

// Calcola giorni lavorativi
$giorni_lavorativi = 0;
if ($_POST) {
    $data_inizio = $_POST['data_inizio'] ?? '';
    $data_fine = $_POST['data_fine'] ?? '';
    
    if ($data_inizio && $data_fine) {
        $start = new DateTime($data_inizio);
        $end = new DateTime($data_fine);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        foreach ($period as $date) {
            $dayOfWeek = $date->format('N');
            if ($dayOfWeek < 6) { // Lunedì-Venerdì
                $giorni_lavorativi++;
            }
        }
    }
}

// Verifica conflitti con lezioni esistenti
$conflitti = [];
if ($stage_id && $stage) {
    $conflitti = Database::queryAll("
        SELECT COUNT(*) as totale, data_lezione
        FROM calendario_lezioni 
        WHERE classe_id = ? 
        AND data_lezione BETWEEN ? AND ?
        AND stato != 'cancellata'
        GROUP BY data_lezione
        ORDER BY data_lezione
    ", [$stage['classe_id'], $stage['data_inizio'], $stage['data_fine']]);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $stage_id ? 'Modifica' : 'Nuovo'; ?> Stage - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">
                    <?php echo $stage_id ? 'Modifica Stage' : 'Nuovo Stage'; ?>
                </h1>
                <p class="text-gray-600 mt-2">
                    <?php echo $stage_id ? 
                        'Modifica i dettagli dello stage per ' . htmlspecialchars($stage['classe_nome']) : 
                        'Pianifica un nuovo periodo di stage per una classe'; ?>
                </p>
            </div>

            <form id="stageForm" method="POST" action="../api/stage_api.php" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="<?php echo $stage_id ? 'update_stage' : 'create_stage'; ?>">
                <?php if ($stage_id): ?>
                    <input type="hidden" name="stage_id" value="<?php echo $stage_id; ?>">
                <?php endif; ?>

                <!-- Informazioni Base -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Informazioni Base</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Classe *</label>
                            <select name="classe_id" required 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <option value="">Seleziona classe</option>
                                <?php foreach ($classi as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                        <?php echo ($stage && $stage['classe_id'] == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                            <select name="stato" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="pianificato" <?php echo ($stage && $stage['stato'] == 'pianificato') ? 'selected' : ''; ?>>Pianificato</option>
                                <option value="in_corso" <?php echo ($stage && $stage['stato'] == 'in_corso') ? 'selected' : ''; ?>>In Corso</option>
                                <option value="completato" <?php echo ($stage && $stage['stato'] == 'completato') ? 'selected' : ''; ?>>Completato</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Data Inizio *</label>
                            <input type="date" name="data_inizio" required 
                                   value="<?php echo $stage ? $stage['data_inizio'] : ''; ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Data Fine *</label>
                            <input type="date" name="data_fine" required 
                                   value="<?php echo $stage ? $stage['data_fine'] : ''; ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ore Totali Previste *</label>
                            <input type="number" name="ore_totali_previste" required min="1"
                                   value="<?php echo $stage ? $stage['ore_totali_previste'] : ''; ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex items-end">
                            <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg w-full">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span id="giorniLavorativi"><?php echo $giorni_lavorativi; ?></span> giorni lavorativi nel periodo
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                        <textarea name="descrizione" rows="3" 
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                  placeholder="Breve descrizione dello stage..."><?php echo $stage ? htmlspecialchars($stage['descrizione']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <textarea name="note" rows="2" 
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                  placeholder="Note aggiuntive..."><?php echo $stage ? htmlspecialchars($stage['note']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Informazioni Tutor -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Informazioni Tutor</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tutor Scolastico</label>
                            <select name="tutor_scolastico_id" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <option value="">Seleziona tutor</option>
                                <?php foreach ($docenti as $docente): ?>
                                    <option value="<?php echo $docente['id']; ?>" 
                                        <?php echo ($tutor && $tutor['docente_id'] == $docente['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($docente['nome_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome Tutor Aziendale</label>
                            <input type="text" name="nome_tutor_aziendale" 
                                   value="<?php echo $tutor ? htmlspecialchars($tutor['nome_tutor_aziendale']) : ''; ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Azienda</label>
                            <input type="text" name="azienda" 
                                   value="<?php echo $tutor ? htmlspecialchars($tutor['azienda']) : ''; ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefono Azienda</label>
                            <input type="tel" name="telefono_azienda" 
                                   value="<?php echo $tutor ? htmlspecialchars($tutor['telefono_azienda']) : ''; ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Azienda</label>
                        <input type="email" name="email_azienda" 
                               value="<?php echo $tutor ? htmlspecialchars($tutor['email_azienda']) : ''; ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Conflitti con Calendario -->
                <?php if (!empty($conflitti)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-yellow-800 mb-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Conflitti con Calendario
                    </h2>
                    
                    <div class="mb-4">
                        <p class="text-yellow-700">
                            Sono state trovate <?php echo count($conflitti); ?> giornate con lezioni programmate durante il periodo di stage.
                        </p>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="cancella_lezioni_conflitto" id="cancella_lezioni_conflitto" class="mr-2">
                        <label for="cancella_lezioni_conflitto" class="text-yellow-700 font-medium">
                            Cancella automaticamente le lezioni in conflitto
                        </label>
                    </div>
                    
                    <div class="mt-3 text-sm text-yellow-600">
                        <p>Le lezioni cancellate verranno marcate come "cancellate" con motivo "stage" e potranno essere recuperate successivamente.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Azioni -->
                <div class="flex justify-between items-center pt-6">
                    <a href="stage.php" class="text-gray-600 hover:text-gray-800 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Torna alla lista
                    </a>
                    
                    <div class="space-x-3">
                        <button type="button" onclick="history.back()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                            Annulla
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg flex items-center">
                            <i class="fas fa-save mr-2"></i> 
                            <?php echo $stage_id ? 'Aggiorna Stage' : 'Crea Stage'; ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Calcola giorni lavorativi quando cambiano le date
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', calcolaGiorniLavorativi);
        });

        function calcolaGiorniLavorativi() {
            const dataInizio = document.querySelector('input[name="data_inizio"]').value;
            const dataFine = document.querySelector('input[name="data_fine"]').value;
            
            if (dataInizio && dataFine) {
                const start = new Date(dataInizio);
                const end = new Date(dataFine);
                let giorni = 0;
                
                for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
                    const dayOfWeek = date.getDay();
                    if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Escludi Domenica (0) e Sabato (6)
                        giorni++;
                    }
                }
                
                document.getElementById('giorniLavorativi').textContent = giorni;
            }
        }

        // Validazione form
        document.getElementById('stageForm').addEventListener('submit', function(e) {
            const dataInizio = document.querySelector('input[name="data_inizio"]').value;
            const dataFine = document.querySelector('input[name="data_fine"]').value;
            
            if (dataInizio && dataFine && new Date(dataFine) <= new Date(dataInizio)) {
                e.preventDefault();
                alert('La data fine deve essere successiva alla data inizio');
                return false;
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>