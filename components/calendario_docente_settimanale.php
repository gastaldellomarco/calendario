<?php
// ✅ CORREZIONE: Controllo variabili e gestione errori
if (!isset($data_inizio) || !isset($data_fine)) {
    echo '<div class="text-red-500 p-4">Errore: Variabili mancanti per la vista docente</div>';
    return;
}

// Se non è selezionato un docente, mostra messaggio
if (empty($docente_id)) {
    echo '<div class="text-center text-gray-500 py-8">';
    echo '<i class="fas fa-chalkboard-teacher text-3xl mb-4"></i>';
    echo '<p>Seleziona un docente dal filtro sopra per visualizzare il suo orario settimanale</p>';
    echo '</div>';
    return;
}

// Carica dati docente con gestione errori
try {
    $stmt_docente = $db->prepare("
        SELECT id, cognome, nome, ore_settimanali_contratto, max_ore_giorno 
        FROM docenti 
        WHERE id = ? AND stato = 'attivo'
    ");
    
    if (!$stmt_docente) {
        throw new Exception("Errore preparazione query docente");
    }
    
    $stmt_docente->execute([$docente_id]);
    $docente = $stmt_docente->fetch();
    
    if (!$docente) {
        echo '<div class="text-red-500 p-4">Docente non trovato o non attivo (ID: ' . htmlspecialchars($docente_id) . ')</div>';
        return;
    }
    
    // Carica slot orari
    $stmt_slot = $db->query("
        SELECT id, numero_slot, ora_inizio, ora_fine, tipo, durata_minuti 
        FROM orari_slot 
        WHERE attivo = 1 
        ORDER BY numero_slot
    ");
    
    if ($stmt_slot) {
        $slot_orari = $stmt_slot->fetchAll();
    } else {
        $slot_orari = [];
        error_log("Errore query slot orari");
    }
    
    // Carica lezioni del docente per la settimana
    $params = [
        $data_inizio->format('Y-m-d'),
        $data_fine->format('Y-m-d'),
        $docente_id
    ];
    
    $stmt_lezioni = $db->prepare("
        SELECT 
            cl.*,
            c.nome as classe_nome,
            m.nome as materia_nome,
            m.codice as materia_codice,
            a.nome as aula_nome,
            s.nome as sede_nome,
            os.ora_inizio,
            os.ora_fine,
            os.durata_minuti
        FROM calendario_lezioni cl
        JOIN classi c ON cl.classe_id = c.id
        JOIN materie m ON cl.materia_id = m.id
        LEFT JOIN aule a ON cl.aula_id = a.id
        JOIN sedi s ON cl.sede_id = s.id
        JOIN orari_slot os ON cl.slot_id = os.id
        WHERE cl.data_lezione BETWEEN ? AND ? 
        AND cl.docente_id = ?
        AND cl.stato != 'cancellata'
        ORDER BY cl.data_lezione, os.ora_inizio
    ");
    
    if ($stmt_lezioni) {
        $stmt_lezioni->execute($params);
        $lezioni = $stmt_lezioni->fetchAll();
    } else {
        $lezioni = [];
        error_log("Errore preparazione query lezioni");
    }
    
    // Calcola ore per giorno e totali
    $ore_per_giorno = [];
    $ore_totali_settimana = 0;
    $lezioni_gruppate = [];
    
    foreach ($lezioni as $lezione) {
        $data = $lezione['data_lezione'];
        $slot_id = $lezione['slot_id'];
        $ore_lezione = ($lezione['durata_minuti'] ?? 60) / 60; // Default 60 minuti se non specificato
        
        // Raggruppa per data e slot
        if (!isset($lezioni_gruppate[$data])) {
            $lezioni_gruppate[$data] = [];
        }
        if (!isset($lezioni_gruppate[$data][$slot_id])) {
            $lezioni_gruppate[$data][$slot_id] = [];
        }
        $lezioni_gruppate[$data][$slot_id][] = $lezione;
        
        // Calcola ore per giorno
        if (!isset($ore_per_giorno[$data])) {
            $ore_per_giorno[$data] = 0;
        }
        $ore_per_giorno[$data] += $ore_lezione;
        $ore_totali_settimana += $ore_lezione;
    }
    
} catch (Exception $e) {
    error_log("Errore caricamento vista docente settimanale: " . $e->getMessage());
    echo '<div class="text-red-500 p-4">Errore nel caricamento dei dati: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $slot_orari = [];
    $lezioni_gruppate = [];
    $ore_per_giorno = [];
    $ore_totali_settimana = 0;
    return;
}
?>

<!-- Header Docente -->
<div class="bg-blue-50 rounded-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">
                Orario Settimanale - <?= htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']) ?>
            </h2>
            <p class="text-gray-600 mt-1">
                Contratto: <?= $docente['ore_settimanali_contratto'] ?> ore settimanali
                | Max giornaliero: <?= $docente['max_ore_giorno'] ?> ore
                | Settimana: <?= $data_inizio->format('d/m/Y') ?> - <?= $data_fine->format('d/m/Y') ?>
            </p>
        </div>
        <div class="mt-4 lg:mt-0">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="text-center">
                    <div class="text-2xl font-bold <?= $ore_totali_settimana > $docente['ore_settimanali_contratto'] ? 'text-red-600' : 'text-green-600' ?>">
                        <?= number_format($ore_totali_settimana, 1) ?>
                    </div>
                    <div class="text-gray-600">Ore questa settimana</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        <?= $docente['ore_settimanali_contratto'] ?>
                    </div>
                    <div class="text-gray-600">Ore contratto</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riepilogo Giornaliero -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Riepilogo Ore Giornaliere</h3>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <?php for ($i = 0; $i < 5; $i++): 
            $giorno = (clone $data_inizio)->modify("+$i days");
            $data_giorno = $giorno->format('Y-m-d');
            $ore_giorno = $ore_per_giorno[$data_giorno] ?? 0;
            $is_overlimit = $ore_giorno > $docente['max_ore_giorno'];
        ?>
            <div class="text-center p-4 rounded-lg border <?= $is_overlimit ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50' ?>">
                <div class="text-sm font-semibold text-gray-700 mb-2">
                    <?= $giorno->format('D d/m') ?>
                </div>
                <div class="text-2xl font-bold <?= $is_overlimit ? 'text-red-600' : 'text-green-600' ?>">
                    <?= number_format($ore_giorno, 1) ?>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    ore
                </div>
                <?php if ($is_overlimit): ?>
                    <div class="text-xs text-red-500 mt-1">
                        <i class="fas fa-exclamation-triangle"></i> Supera limite
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Griglia Orario -->
<?php if (empty($slot_orari)): ?>
    <div class="text-center p-8 text-gray-500 bg-white rounded-lg shadow">
        <i class="fas fa-exclamation-triangle text-3xl mb-4"></i>
        <p>Nessuno slot orario configurato</p>
        <p class="text-sm mt-2">
            <a href="../pages/orari_slot.php" class="text-blue-500 hover:text-blue-700">
                Configura gli slot orari
            </a>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse bg-white">
            <thead>
                <tr>
                    <th class="border border-gray-200 bg-gray-50 p-3 text-sm font-semibold text-gray-700 w-24">
                        Ora
                    </th>
                    <?php for ($i = 0; $i < 5; $i++): 
                        $giorno = (clone $data_inizio)->modify("+$i days");
                        $is_oggi = $giorno->format('Y-m-d') === date('Y-m-d');
                    ?>
                        <th class="border border-gray-200 p-3 text-center <?= $is_oggi ? 'bg-blue-50' : 'bg-white' ?>">
                            <div class="text-sm font-semibold <?= $is_oggi ? 'text-blue-600' : 'text-gray-700' ?>">
                                <?= $giorno->format('D') ?>
                            </div>
                            <div class="text-lg font-bold <?= $is_oggi ? 'text-blue-600' : 'text-gray-900' ?>">
                                <?= $giorno->format('d') ?>
                            </div>
                        </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slot_orari as $slot): ?>
                    <tr>
                        <td class="border border-gray-200 bg-gray-50 p-3 text-center text-sm font-medium text-gray-600">
                            <?= substr($slot['ora_inizio'], 0, 5) ?><br>
                            <span class="text-xs text-gray-500"><?= substr($slot['ora_fine'], 0, 5) ?></span>
                        </td>
                        
                        <?php for ($i = 0; $i < 5; $i++): 
                            $giorno = (clone $data_inizio)->modify("+$i days");
                            $data_giorno = $giorno->format('Y-m-d');
                            $lezioni_giorno = $lezioni_gruppate[$data_giorno][$slot['id']] ?? [];
                        ?>
                            <td class="border border-gray-200 p-1 min-h-16 bg-white">
                                <?php foreach ($lezioni_giorno as $lezione): ?>
                                    <div class="lezione-card m-1 p-2 rounded-lg shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                                         data-lezione-id="<?= $lezione['id'] ?>"
                                         style="background-color: #f0f9ff; border-left: 4px solid #3b82f6;">
                                        <div class="text-xs font-semibold text-gray-800 truncate">
                                            <?= htmlspecialchars($lezione['classe_nome'] ?? 'N/A') ?>
                                        </div>
                                        <div class="text-xs text-gray-700 opacity-90 truncate">
                                            <?= htmlspecialchars($lezione['materia_nome'] ?? 'N/A') ?>
                                        </div>
                                        <?php if (!empty($lezione['aula_nome'])): ?>
                                            <div class="text-xs text-gray-500 opacity-70 truncate">
                                                <i class="fas fa-door-open mr-1"></i><?= htmlspecialchars($lezione['aula_nome']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-500 opacity-60 mt-1">
                                            <?= $lezione['durata_minuti'] ?? 60 ?> min
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($lezioni_giorno)): ?>
                                    <div class="text-center py-4">
                                        <span class="text-gray-200">
                                            <i class="fas fa-minus text-sm"></i>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Legenda -->
<div class="mt-6 bg-gray-50 rounded-lg p-4">
    <h4 class="font-semibold text-gray-700 mb-2">Legenda:</h4>
    <div class="flex flex-wrap gap-4 text-sm">
        <div class="flex items-center">
            <div class="w-4 h-4 bg-green-100 border-l-4 border-green-500 mr-2"></div>
            <span class="text-gray-600">Nel limite ore</span>
        </div>
        <div class="flex items-center">
            <div class="w-4 h-4 bg-red-100 border-l-4 border-red-500 mr-2"></div>
            <span class="text-gray-600">Supera limite giornaliero</span>
        </div>
        <div class="flex items-center">
            <div class="w-4 h-4 bg-blue-100 border-l-4 border-blue-500 mr-2"></div>
            <span class="text-gray-600">Lezione programmata</span>
        </div>
    </div>
</div>