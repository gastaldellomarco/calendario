<?php
// ✅ CORREZIONE: Controllo variabili e gestione errori
if (!isset($data_mese_inizio) || !isset($data_mese_fine)) {
    echo '<div class="text-red-500 p-4">Errore: Variabili mancanti per la vista docente mensile</div>';
    return;
}

// Se non è selezionato un docente, mostra messaggio
if (empty($docente_id)) {
    echo '<div class="text-center text-gray-500 py-8">';
    echo '<i class="fas fa-user-graduate text-3xl mb-4"></i>';
    echo '<p>Seleziona un docente dal filtro sopra per visualizzare il suo orario mensile</p>';
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
    
    // Carica lezioni del docente per il mese
    $params = [
        $data_mese_inizio->format('Y-m-d'),
        $data_mese_fine->format('Y-m-d'),
        $docente_id
    ];
    
    $stmt_lezioni = $db->prepare("
        SELECT 
            cl.data_lezione,
            COUNT(cl.id) as numero_lezioni,
            SUM(os.durata_minuti) as minuti_totali,
            GROUP_CONCAT(DISTINCT c.nome) as classi,
            GROUP_CONCAT(DISTINCT m.nome) as materie
        FROM calendario_lezioni cl
        JOIN classi c ON cl.classe_id = c.id
        JOIN materie m ON cl.materia_id = m.id
        JOIN orari_slot os ON cl.slot_id = os.id
        WHERE cl.data_lezione BETWEEN ? AND ? 
        AND cl.docente_id = ?
        AND cl.stato != 'cancellata'
        GROUP BY cl.data_lezione
        ORDER BY cl.data_lezione
    ");
    
    if ($stmt_lezioni) {
        $stmt_lezioni->execute($params);
        $lezioni = $stmt_lezioni->fetchAll();
    } else {
        $lezioni = [];
        error_log("Errore preparazione query lezioni mensili");
    }
    
    // Calcola statistiche mese
    $giorni_lavorativi = 0;
    $ore_totali_mese = 0;
    $giorni_con_lezioni = [];
    
    foreach ($lezioni as $lezione) {
        $ore_giorno = ($lezione['minuti_totali'] ?? 0) / 60;
        $ore_totali_mese += $ore_giorno;
        $giorni_con_lezioni[$lezione['data_lezione']] = $lezione;
    }
    
    // Calcola giorni lavorativi (lun-ven)
    $current = clone $data_mese_inizio;
    while ($current <= $data_mese_fine) {
        if ($current->format('N') <= 5) { // 1=Lunedì, 5=Venerdì
            $giorni_lavorativi++;
        }
        $current->modify('+1 day');
    }
    
    $ore_medie_settimana = $giorni_lavorativi > 0 ? ($ore_totali_mese / ($giorni_lavorativi / 5)) : 0;
    
} catch (Exception $e) {
    error_log("Errore caricamento vista docente mensile: " . $e->getMessage());
    echo '<div class="text-red-500 p-4">Errore nel caricamento dei dati: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $lezioni = [];
    $ore_totali_mese = 0;
    $giorni_lavorativi = 0;
    $ore_medie_settimana = 0;
    return;
}

// Prepara calendario mensile
$primo_giorno = clone $data_mese_inizio;
$ultimo_giorno = clone $data_mese_fine;
$giorno_corrente = clone $primo_giorno;

// Trova il primo lunedì del mese per iniziare la griglia
$primo_lunedi = clone $primo_giorno;
while ($primo_lunedi->format('N') != 1) {
    $primo_lunedi->modify('-1 day');
}

// Trova l'ultima domenica del mese per finire la griglia
$ultima_domenica = clone $ultimo_giorno;
while ($ultima_domenica->format('N') != 7) {
    $ultima_domenica->modify('+1 day');
}
?>

<!-- Header Docente Mensile -->
<div class="bg-blue-50 rounded-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">
                Riepilogo Mensile - <?= htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']) ?>
            </h2>
            <p class="text-gray-600 mt-1">
                <?= $data_mese_inizio->format('F Y') ?> 
                | Contratto: <?= $docente['ore_settimanali_contratto'] ?> ore settimanali
            </p>
        </div>
        <div class="mt-4 lg:mt-0">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        <?= number_format($ore_totali_mese, 1) ?>
                    </div>
                    <div class="text-gray-600">Ore totali</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold <?= $ore_medie_settimana > $docente['ore_settimanali_contratto'] ? 'text-red-600' : 'text-green-600' ?>">
                        <?= number_format($ore_medie_settimana, 1) ?>
                    </div>
                    <div class="text-gray-600">Media settimanale</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        <?= count($lezioni) ?>
                    </div>
                    <div class="text-gray-600">Giorni con lezioni</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendario Mensile -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <!-- Intestazione giorni -->
    <div class="grid grid-cols-7 border-b bg-gray-50">
        <div class="p-3 text-center font-semibold text-gray-700 text-sm">Lunedì</div>
        <div class="p-3 text-center font-semibold text-gray-700 text-sm">Martedì</div>
        <div class="p-3 text-center font-semibold text-gray-700 text-sm">Mercoledì</div>
        <div class="p-3 text-center font-semibold text-gray-700 text-sm">Giovedì</div>
        <div class="p-3 text-center font-semibold text-gray-700 text-sm">Venerdì</div>
        <div class="p-3 text-center font-semibold text-gray-500 text-sm">Sabato</div>
        <div class="p-3 text-center font-semibold text-gray-500 text-sm">Domenica</div>
    </div>

    <!-- Griglia giorni -->
    <div class="grid grid-cols-7">
        <?php
        $current = clone $primo_lunedi;
        while ($current <= $ultima_domenica):
        ?>
            <?php for ($col = 0; $col < 7; $col++): 
                $is_current_month = $current->format('Y-m') === $data_mese_inizio->format('Y-m');
                $is_weekend = $current->format('N') >= 6;
                $is_today = $current->format('Y-m-d') === date('Y-m-d');
                $data_giorno = $current->format('Y-m-d');
                $lezione_giorno = $giorni_con_lezioni[$data_giorno] ?? null;
                $has_lezioni = $lezione_giorno !== null;
                
                // Calcola ore del giorno
                $ore_giorno = $has_lezioni ? (($lezione_giorno['minuti_totali'] ?? 0) / 60) : 0;
                $is_overlimit = $ore_giorno > $docente['max_ore_giorno'] && $ore_giorno > 0;
            ?>
                <div class="min-h-32 border-r border-b p-2 
                    <?= $is_current_month ? 'bg-white' : 'bg-gray-50' ?>
                    <?= $is_weekend ? 'bg-gray-50' : '' ?>
                    <?= $is_today ? 'ring-2 ring-blue-500' : '' ?>">
                    
                    <!-- Header giorno -->
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium 
                            <?= $is_current_month ? 'text-gray-700' : 'text-gray-400' ?>
                            <?= $is_weekend ? 'text-gray-500' : '' ?>">
                            <?= $current->format('j') ?>
                        </span>
                        <?php if ($has_lezioni): ?>
                            <span class="text-xs px-2 py-1 rounded-full 
                                <?= $is_overlimit ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                <?= number_format($ore_giorno, 1) ?>h
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Contenuto lezioni -->
                    <?php if ($has_lezioni && $is_current_month): ?>
                        <div class="space-y-1">
                            <div class="text-xs text-gray-600 font-semibold">
                                <?= $lezione_giorno['numero_lezioni'] ?> lezioni
                            </div>
                            <?php 
                            $classi = $lezione_giorno['classi'] ? explode(',', $lezione_giorno['classi']) : [];
                            $materie = $lezione_giorno['materie'] ? explode(',', $lezione_giorno['materie']) : [];
                            ?>
                            <?php if (!empty($classi)): ?>
                                <div class="text-xs text-gray-500">
                                    Classi: <?= implode(', ', array_slice($classi, 0, 2)) ?>
                                    <?= count($classi) > 2 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($materie)): ?>
                                <div class="text-xs text-gray-500">
                                    Materie: <?= implode(', ', array_slice($materie, 0, 2)) ?>
                                    <?= count($materie) > 2 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($is_current_month && !$is_weekend): ?>
                        <div class="text-center text-gray-300 mt-4">
                            <i class="fas fa-minus text-sm"></i>
                        </div>
                    <?php endif; ?>
                </div>
            <?php 
                $current->modify('+1 day');
            endfor; ?>
        <?php endwhile; ?>
    </div>
</div>

<!-- Statistiche Dettagliate -->
<div class="mt-6 bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Statistiche Dettagliate</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="text-center p-4 border rounded-lg">
            <div class="text-2xl font-bold text-blue-600"><?= $giorni_lavorativi ?></div>
            <div class="text-gray-600">Giorni lavorativi</div>
        </div>
        <div class="text-center p-4 border rounded-lg">
            <div class="text-2xl font-bold text-green-600"><?= count($lezioni) ?></div>
            <div class="text-gray-600">Giorni con lezioni</div>
        </div>
        <div class="text-center p-4 border rounded-lg">
            <div class="text-2xl font-bold text-purple-600">
                <?= number_format($ore_totali_mese / max($giorni_lavorativi, 1), 1) ?>
            </div>
            <div class="text-gray-600">Ore medie per giorno</div>
        </div>
    </div>
</div>

<!-- Legenda -->
<div class="mt-6 bg-gray-50 rounded-lg p-4">
    <h4 class="font-semibold text-gray-700 mb-2">Legenda:</h4>
    <div class="flex flex-wrap gap-4 text-sm">
        <div class="flex items-center">
            <div class="w-4 h-4 bg-green-100 border border-green-300 rounded mr-2"></div>
            <span class="text-gray-600">Nel limite ore giornaliero</span>
        </div>
        <div class="flex items-center">
            <div class="w-4 h-4 bg-red-100 border border-red-300 rounded mr-2"></div>
            <span class="text-gray-600">Supera limite giornaliero</span>
        </div>
        <div class="flex items-center">
            <div class="w-4 h-4 bg-blue-100 border-2 border-blue-500 rounded mr-2"></div>
            <span class="text-gray-600">Giorno corrente</span>
        </div>
        <div class="flex items-center">
            <div class="w-4 h-4 bg-gray-100 border border-gray-300 rounded mr-2"></div>
            <span class="text-gray-600">Fine settimana</span>
        </div>
    </div>
</div>