<?php
// ✅ CORREZIONE: Controllo variabili
if (!isset($data_inizio) || !isset($data_fine) || !isset($sede_id) || !isset($classe_id) || !isset($docente_id) || !isset($anno_scolastico_id)) {
    echo '<div class="text-red-500 p-4">Errore: Variabili mancanti per il calendario</div>';
    return;
}

// Carica slot orari con gestione errori
try {
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
    
    // Carica lezioni per la settimana
    $params = [];
    $where_conditions = ["cl.data_lezione BETWEEN ? AND ?"];
    $params[] = $data_inizio->format('Y-m-d');
    $params[] = $data_fine->format('Y-m-d');
    
    if ($sede_id) {
        $where_conditions[] = "cl.sede_id = ?";
        $params[] = $sede_id;
    }
    if ($classe_id) {
        $where_conditions[] = "cl.classe_id = ?";
        $params[] = $classe_id;
    }
    if ($docente_id) {
        $where_conditions[] = "cl.docente_id = ?";
        $params[] = $docente_id;
    }
    if ($anno_scolastico_id) {
        $where_conditions[] = "c.anno_scolastico_id = ?";
        $params[] = $anno_scolastico_id;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            cl.*,
            c.nome as classe_nome,
            m.nome as materia_nome,
            m.codice as materia_codice,
            CONCAT(d.cognome, ' ', d.nome) as docente_nome,
            a.nome as aula_nome,
            s.nome as sede_nome,
            os.ora_inizio,
            os.ora_fine
        FROM calendario_lezioni cl
        JOIN classi c ON cl.classe_id = c.id
        JOIN materie m ON cl.materia_id = m.id
        JOIN docenti d ON cl.docente_id = d.id
        LEFT JOIN aule a ON cl.aula_id = a.id
        JOIN sedi s ON cl.sede_id = s.id
        JOIN orari_slot os ON cl.slot_id = os.id
        WHERE $where_sql
        ORDER BY cl.data_lezione, os.ora_inizio
    ";
    
    $stmt_lezioni = $db->prepare($sql);
    if ($stmt_lezioni) {
        $stmt_lezioni->execute($params);
        $lezioni = $stmt_lezioni->fetchAll();
    } else {
        $lezioni = [];
        error_log("Errore preparazione query lezioni");
    }
    
    // Raggruppa lezioni per data e slot
    $lezioni_gruppate = [];
    foreach ($lezioni as $lezione) {
        $data = $lezione['data_lezione'];
        $slot_id = $lezione['slot_id'];
        if (!isset($lezioni_gruppate[$data])) {
            $lezioni_gruppate[$data] = [];
        }
        if (!isset($lezioni_gruppate[$data][$slot_id])) {
            $lezioni_gruppate[$data][$slot_id] = [];
        }
        $lezioni_gruppate[$data][$slot_id][] = $lezione;
    }
    
} catch (Exception $e) {
    error_log("Errore caricamento calendario settimanale: " . $e->getMessage());
    echo '<div class="text-red-500 p-4">Errore nel caricamento dei dati: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $slot_orari = [];
    $lezioni_gruppate = [];
    return;
}
?>

<div class="overflow-x-auto">
    <table class="w-full border-collapse bg-white">
        <thead>
            <tr>
                <th class="border border-gray-200 bg-gray-50 p-3 text-sm font-semibold text-gray-700 w-24">
                    Ora
                </th>
                <?php for ($i = 0; $i < 7; $i++): 
                    $giorno = (clone $data_inizio)->modify("+$i days");
                    $is_oggi = $giorno->format('Y-m-d') === date('Y-m-d');
                    $is_weekend = in_array($giorno->format('N'), [6, 7]);
                ?>
                    <th class="border border-gray-200 p-3 text-center <?= $is_oggi ? 'bg-blue-50' : ($is_weekend ? 'bg-gray-50' : 'bg-white') ?>">
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
            <?php if (empty($slot_orari)): ?>
                <tr>
                    <td colspan="8" class="text-center p-8 text-gray-500">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                        <p>Nessuno slot orario configurato</p>
                        <p class="text-sm mt-2">
                            <a href="../pages/orari_slot.php" class="text-blue-500 hover:text-blue-700">
                                Configura gli slot orari
                            </a>
                        </p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($slot_orari as $slot): ?>
                    <tr>
                        <td class="border border-gray-200 bg-gray-50 p-3 text-center text-sm font-medium text-gray-600">
                            <?= substr($slot['ora_inizio'], 0, 5) ?><br>
                            <span class="text-xs text-gray-500"><?= substr($slot['ora_fine'], 0, 5) ?></span>
                        </td>
                        
                        <?php for ($i = 0; $i < 7; $i++): 
                            $giorno = (clone $data_inizio)->modify("+$i days");
                            $data_giorno = $giorno->format('Y-m-d');
                            $lezioni_giorno = $lezioni_gruppate[$data_giorno][$slot['id']] ?? [];
                            $is_weekend = in_array($giorno->format('N'), [6, 7]);
                        ?>
                            <td class="border border-gray-200 p-1 min-h-16 <?= $is_weekend ? 'bg-gray-50' : 'bg-white' ?>">
                                <?php foreach ($lezioni_giorno as $lezione): 
                                    // ✅ CORREZIONE: Generazione colore sicura
                                    $colore_base = $lezione['materia_id'] ?? 'default';
                                    $colore_materia = substr(md5($colore_base), 0, 6);
                                ?>
                                    <div class="lezione-card m-1 p-2 rounded-lg shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                                         data-lezione-id="<?= $lezione['id'] ?>"
                                         style="background-color: #f3f4f6; border-left: 4px solid #<?= $colore_materia ?>">
                                        <div class="text-xs font-semibold text-gray-800 truncate">
                                            <?= htmlspecialchars($lezione['classe_nome'] ?? 'N/A') ?>
                                        </div>
                                        <div class="text-xs text-gray-700 opacity-90 truncate">
                                            <?= htmlspecialchars($lezione['materia_nome'] ?? 'N/A') ?>
                                        </div>
                                        <div class="text-xs text-gray-600 opacity-80 truncate">
                                            <?= htmlspecialchars($lezione['docente_nome'] ?? 'N/A') ?>
                                        </div>
                                        <?php if (!empty($lezione['aula_nome'])): ?>
                                            <div class="text-xs text-gray-500 opacity-70 truncate">
                                                <i class="fas fa-door-open mr-1"></i><?= htmlspecialchars($lezione['aula_nome']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($lezioni_giorno)): ?>
                                    <div class="text-center py-4">
                                        <span class="text-gray-300">
                                            <i class="fas fa-plus-circle text-lg"></i>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>