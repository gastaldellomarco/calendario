<?php
/**
 * Widget mini-calendario per singolo docente
 *
 * Visualizza un widget compatto o espanso con il riepilogo delle lezioni per
 * la settimana di riferimento.
 *
 * @param int $docente_id ID del docente
 * @param string|null $data_riferimento Data di riferimento (Y-m-d). Default: oggi
 * @param bool $compact Se true renderizza versione compatta
 * @return string HTML del widget
 */
function calendario_docente_widget($docente_id, $data_riferimento = null, $compact = false) {
    global $db;
    
    $data_riferimento = $data_riferimento ?: date('Y-m-d');
    $inizio_settimana = date('Y-m-d', strtotime('monday this week', strtotime($data_riferimento)));
    
    // Dati docente
    $docente = $db->query("
        SELECT id, cognome, nome, sede_principale_id 
        FROM docenti WHERE id = ?
    ", [$docente_id])->fetch();
    
    if (!$docente) {
        return '<div class="text-red-500">Docente non trovato</div>';
    }
    
    // Lezioni della settimana
    $lezioni = $db->query("
        SELECT cl.data_lezione, cl.stato, m.nome as materia_nome, c.nome as classe_nome,
               os.ora_inizio, os.ora_fine, os.numero_slot,
               a.nome as aula_nome
        FROM calendario_lezioni cl
        JOIN materie m ON cl.materia_id = m.id
        JOIN classi c ON cl.classe_id = c.id
        JOIN orari_slot os ON cl.slot_id = os.id
        LEFT JOIN aule a ON cl.aula_id = a.id
        WHERE cl.docente_id = ?
        AND cl.data_lezione BETWEEN ? AND ?
        AND cl.stato IN ('pianificata', 'confermata')
        ORDER BY cl.data_lezione, os.ora_inizio
    ", [$docente_id, $inizio_settimana, date('Y-m-d', strtotime($inizio_settimana . ' +6 days'))])->fetchAll();
    
    // Raggruppa lezioni per giorno
    $lezioni_per_giorno = [];
    foreach ($lezioni as $lezione) {
        $giorno = $lezione['data_lezione'];
        if (!isset($lezioni_per_giorno[$giorno])) {
            $lezioni_per_giorno[$giorno] = [];
        }
        $lezioni_per_giorno[$giorno][] = $lezione;
    }
    
    // Genera HTML
    ob_start();
    ?>
    
    <div class="bg-white border border-gray-200 rounded-lg <?php echo $compact ? 'p-2 text-xs' : 'p-4'; ?>">
        <!-- Header -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
                <img class="<?php echo $compact ? 'h-6 w-6' : 'h-8 w-8'; ?> rounded-full mr-2" 
                     src="https://ui-avatars.com/api/?name=<?php echo urlencode($docente['cognome'] . ' ' . $docente['nome']); ?>&background=3b82f6&color=fff" 
                     alt="">
                <div>
                    <div class="<?php echo $compact ? 'text-xs font-medium' : 'text-sm font-semibold'; ?>">
                        <?php echo htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']); ?>
                    </div>
                    <?php if (!$compact): ?>
                        <div class="text-xs text-gray-500">Settimana <?php echo date('W', strtotime($inizio_settimana)); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$compact): ?>
                <div class="text-xs text-gray-500">
                    <?php echo count($lezioni); ?> lezioni
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Griglia settimana -->
        <div class="grid grid-cols-7 gap-1">
            <?php for ($i = 0; $i < 7; $i++): 
                $giorno_data = date('Y-m-d', strtotime($inizio_settimana . " +$i days"));
                $lezioni_giorno = $lezioni_per_giorno[$giorno_data] ?? [];
                $is_oggi = $giorno_data == date('Y-m-d');
            ?>
                <div class="text-center">
                    <!-- Header giorno -->
                    <div class="<?php echo $compact ? 'text-xs' : 'text-sm'; ?> font-medium mb-1 
                                <?php echo $is_oggi ? 'text-blue-600' : 'text-gray-700'; ?>">
                        <?php echo date('D', strtotime($giorno_data)); ?><br>
                        <span class="<?php echo $compact ? 'text-xs' : 'text-sm'; ?> font-normal">
                            <?php echo date('d', strtotime($giorno_data)); ?>
                        </span>
                    </div>
                    
                    <!-- Indicatore lezioni -->
                    <div class="flex flex-col items-center space-y-1">
                        <?php if (empty($lezioni_giorno)): ?>
                            <div class="w-3 h-3 rounded-full bg-gray-200" title="Nessuna lezione"></div>
                        <?php else: ?>
                            <?php foreach (array_slice($lezioni_giorno, 0, $compact ? 2 : 3) as $lezione): ?>
                                <div class="w-3 h-3 rounded-full bg-green-500 relative group"
                                     title="<?php echo htmlspecialchars($lezione['materia_name'] . ' - ' . $lezione['classe_nome']); ?>">
                                    
                                    <?php if (!$compact): ?>
                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 hidden group-hover:block z-10">
                                            <div class="bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                                <?php echo date('H:i', strtotime($lezione['ora_inizio'])); ?> - 
                                                <?php echo htmlspecialchars($lezione['materia_nome']); ?>
                                            </div>
                                            <div class="w-3 h-3 bg-gray-900 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($lezioni_giorno) > ($compact ? 2 : 3)): ?>
                                <div class="text-xs text-gray-500">+<?php echo count($lezioni_giorno) - ($compact ? 2 : 3); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <!-- Dettaglio (solo in modalità espansa) -->
        <?php if (!$compact && !empty($lezioni)): ?>
            <div class="mt-4 border-t pt-3">
                <div class="text-xs font-medium text-gray-700 mb-2">Prossime lezioni:</div>
                <div class="space-y-2 max-h-32 overflow-y-auto">
                    <?php foreach (array_slice($lezioni, 0, 3) as $lezione): ?>
                        <div class="flex items-center justify-between text-xs">
                            <div>
                                <span class="font-medium"><?php echo date('H:i', strtotime($lezione['ora_inizio'])); ?></span>
                                <span class="text-gray-600"><?php echo htmlspecialchars($lezione['classe_nome']); ?></span>
                            </div>
                            <div class="text-gray-500 truncate ml-2" title="<?php echo htmlspecialchars($lezione['materia_nome']); ?>">
                                <?php echo htmlspecialchars($lezione['materia_nome']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    return ob_get_clean();
}