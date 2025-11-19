<?php
/**
 * Componente Lezione Card
 * 
 * @param array $lezione Dati lezione
 * @param bool $show_details Mostra dettagli completi
 * @param bool $draggable Rende la card draggable
 * @param bool $editable Mostra pulsanti modifica/elimina
 */
function lezione_card($lezione, $show_details = false, $draggable = false, $editable = false) {
    $colore_materia = substr(md5($lezione['materia_id']), 0, 6);
    $luminosita = hexdec(substr($colore_materia, 0, 2)) > 128 ? 'text-gray-800' : 'text-white';
    
    // Icona stato
    $stato_icons = [
        'pianificata' => 'far fa-clock',
        'confermata' => 'fas fa-check-circle',
        'svolta' => 'fas fa-check-double',
        'cancellata' => 'fas fa-ban',
        'sostituita' => 'fas fa-exchange-alt'
    ];
    $stato_icon = $stato_icons[$lezione['stato']] ?? 'far fa-clock';
    
    // Colore stato
    $stato_colors = [
        'pianificata' => 'bg-blue-100 text-blue-800',
        'confermata' => 'bg-green-100 text-green-800',
        'svolta' => 'bg-purple-100 text-purple-800',
        'cancellata' => 'bg-red-100 text-red-800',
        'sostituita' => 'bg-orange-100 text-orange-800'
    ];
    $stato_color = $stato_colors[$lezione['stato']] ?? 'bg-gray-100 text-gray-800';
    
    $draggable_attr = $draggable ? 'draggable="true"' : '';
?>
<div class="lezione-card <?= $draggable ? 'draggable-lezione cursor-move' : 'cursor-pointer' ?> m-1 p-2 rounded-lg shadow-sm hover:shadow-md transition-all duration-200"
     <?= $draggable_attr ?>
     data-lezione-id="<?= $lezione['id'] ?>"
     data-classe-id="<?= $lezione['classe_id'] ?>"
     data-materia-id="<?= $lezione['materia_id'] ?>"
     data-docente-id="<?= $lezione['docente_id'] ?>"
     data-aula-id="<?= $lezione['aula_id'] ?>"
     style="background-color: #<?= $colore_materia ?>; border-left: 4px solid #<?= substr(md5($lezione['classe_id']), 0, 6) ?>">
    
    <!-- Header -->
    <div class="flex justify-between items-start mb-1">
        <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold <?= $luminosita ?> truncate flex items-center">
                <?= htmlspecialchars($lezione['classe_nome'] ?? $lezione['nome_classe'] ?? '') ?>
                <span class="ml-2 text-xs px-1.5 py-0.5 rounded-full <?= $stato_color ?>">
                    <i class="<?= $stato_icon ?> mr-1"></i><?= ucfirst($lezione['stato']) ?>
                </span>
            </div>
        </div>
        
        <?php if ($editable): ?>
        <div class="flex space-x-1 ml-2">
            <button class="edit-lezione text-xs <?= $luminosita ?> opacity-70 hover:opacity-100 transition-opacity"
                    data-lezione-id="<?= $lezione['id'] ?>"
                    title="Modifica">
                <i class="fas fa-edit"></i>
            </button>
            <button class="delete-lezione text-xs <?= $luminosita ?> opacity-70 hover:opacity-100 transition-opacity"
                    data-lezione-id="<?= $lezione['id'] ?>"
                    title="Elimina">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Contenuto Principale -->
    <div class="text-xs <?= $luminosita ?> opacity-90 truncate">
        <?= htmlspecialchars($lezione['materia_nome'] ?? $lezione['nome_materia'] ?? '') ?>
    </div>
    
    <div class="text-xs <?= $luminosita ?> opacity-80 truncate">
        <?= htmlspecialchars($lezione['docente_nome'] ?? $lezione['nome_docente'] ?? '') ?>
    </div>
    
    <?php if ($lezione['aula_nome'] ?? $lezione['nome_aula'] ?? ''): ?>
    <div class="text-xs <?= $luminosita ?> opacity-70 truncate flex items-center">
        <i class="fas fa-door-open mr-1"></i>
        <?= htmlspecialchars($lezione['aula_nome'] ?? $lezione['nome_aula'] ?? '') ?>
    </div>
    <?php endif; ?>
    
    <!-- Dettagli Estesi -->
    <?php if ($show_details): ?>
        <?php if ($lezione['argomento'] ?? ''): ?>
        <div class="mt-2 pt-2 border-t border-opacity-20 <?= $luminosita ?> border-current">
            <div class="text-xs font-semibold <?= $luminosita ?>">Argomento:</div>
            <div class="text-xs <?= $luminosita ?> opacity-90"><?= htmlspecialchars($lezione['argomento']) ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($lezione['note'] ?? ''): ?>
        <div class="mt-1 text-xs <?= $luminosita ?> opacity-80">
            <i class="fas fa-sticky-note mr-1"></i><?= htmlspecialchars(substr($lezione['note'], 0, 50)) ?><?= strlen($lezione['note']) > 50 ? '...' : '' ?>
        </div>
        <?php endif; ?>
        
        <div class="mt-1 text-xs <?= $luminosita ?> opacity-60 flex justify-between">
            <span>
                <i class="fas fa-clock mr-1"></i><?= substr($lezione['ora_inizio'] ?? '', 0, 5) ?>-<?= substr($lezione['ora_fine'] ?? '', 0, 5) ?>
            </span>
            <?php if ($lezione['modalita'] ?? ''): ?>
            <span>
                <i class="fas fa-<?= $lezione['modalita'] === 'online' ? 'video' : ($lezione['modalita'] === 'mista' ? 'blender' : 'user') ?> mr-1"></i>
                <?= ucfirst($lezione['modalita']) ?>
            </span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Info Compact -->
        <div class="mt-1 text-xs <?= $luminosita ?> opacity-60 flex justify-between">
            <span><?= substr($lezione['ora_inizio'] ?? '', 0, 5) ?></span>
            <?php if ($lezione['aula_nome'] ?? $lezione['nome_aula'] ?? ''): ?>
            <span><?= htmlspecialchars($lezione['aula_nome'] ?? $lezione['nome_aula'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Indicatore Conflitti -->
    <?php if ($lezione['has_conflitti'] ?? false): ?>
    <div class="mt-1 text-center">
        <span class="inline-block w-3 h-3 bg-red-500 rounded-full" title="Conflitti presenti"></span>
    </div>
    <?php endif; ?>
</div>
<?php
}
?>