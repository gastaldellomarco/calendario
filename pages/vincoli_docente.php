<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Verifica permessi
if (!in_array($_SESSION['ruolo'], ['preside', 'vice_preside', 'segreteria', 'amministratore'])) {
    http_response_code(403);
    exit('Accesso negato');
}

// ✅ CORRETTO: Valida parametro GET con isset
$docente_id = isset($_GET['docente_id']) ? (int)$_GET['docente_id'] : 0;
if ($docente_id <= 0) {
    exit('ID docente non valido');
}

// ✅ CORRETTO: Usa Database class con PDO
$sql = "SELECT cognome, nome FROM docenti WHERE id = ?";
$docente = Database::queryOne($sql, [$docente_id]);

if (!$docente) {
    exit('Docente non trovato');
}

// Get vincoli esistenti
$sql_vincoli = "SELECT v.*, s.nome as sede_nome 
                       FROM vincoli_docenti v 
                       LEFT JOIN sedi s ON v.sede_id = s.id 
                       WHERE v.docente_id = ? 
                       ORDER BY v.giorno_settimana, v.ora_inizio";
$vincoli = Database::queryAll($sql_vincoli, [$docente_id]);

// Get sedi
$sql_sedi = "SELECT id, nome FROM sedi WHERE attiva = 1 ORDER BY nome";
$sedi = Database::queryAll($sql_sedi, []);

$giorni_settimana = [
    1 => 'Lunedì',
    2 => 'Martedì',
    3 => 'Mercoledì',
    4 => 'Giovedì',
    5 => 'Venerdì',
    6 => 'Sabato',
    7 => 'Domenica'
];

$tipi_vincolo = [
    'indisponibilita' => 'Indisponibilità',
    'preferenza' => 'Preferenza',
    'doppia_sede' => 'Doppia Sede'
];
?>
<div class="max-w-6xl mx-auto bg-white rounded-lg shadow-md">
    <div class="px-6 py-4 border-b">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">
                Vincoli Orari - <?= htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']) ?>
            </h3>
            <button type="button" onclick="closeModal()" 
                    class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
    </div>
    
    <div class="p-6">
        <!-- Form Aggiungi Vincolo -->
        <div class="bg-gray-50 rounded-lg p-6 mb-6">
            <h4 class="text-md font-medium text-gray-900 mb-4">Aggiungi Nuovo Vincolo</h4>
            <form id="vincoloForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <input type="hidden" name="docente_id" value="<?= $docente_id ?>">
                
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                    <select id="tipo" name="tipo" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Seleziona tipo</option>
                        <?php foreach ($tipi_vincolo as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="giorno_settimana" class="block text-sm font-medium text-gray-700 mb-1">Giorno *</label>
                    <select id="giorno_settimana" name="giorno_settimana" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Seleziona giorno</option>
                        <?php foreach ($giorni_settimana as $num => $giorno): ?>
                            <option value="<?= $num ?>"><?= $giorno ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="ora_inizio" class="block text-sm font-medium text-gray-700 mb-1">Ora Inizio</label>
                    <input type="time" id="ora_inizio" name="ora_inizio"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="ora_fine" class="block text-sm font-medium text-gray-700 mb-1">Ora Fine</label>
                    <input type="time" id="ora_fine" name="ora_fine"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                
                <div id="sedeField" class="hidden">
                    <label for="sede_id" class="block text-sm font-medium text-gray-700 mb-1">Sede</label>
                    <select id="sede_id" name="sede_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Seleziona sede</option>
                        <?php foreach ($sedi as $sede): ?>
                            <option value="<?= $sede['id'] ?>"><?= htmlspecialchars($sede['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label for="motivo" class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                    <input type="text" id="motivo" name="motivo" placeholder="Motivo del vincolo..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="attivo" name="attivo" value="1" checked
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="attivo" class="ml-2 block text-sm text-gray-700">Attivo</label>
                </div>
                
                <div class="md:col-span-5 flex justify-end">
                    <button type="button" onclick="saveVincolo()" 
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                        Aggiungi Vincolo
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Lista Vincoli Esistenti -->
        <div>
            <h4 class="text-md font-medium text-gray-900 mb-4">Vincoli Esistenti</h4>
            <?php if (empty($vincoli)): ?>
                <p class="text-gray-500 text-center py-4">Nessun vincolo definito</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Giorno</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ore</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sede</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($vincoli as $vincolo): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?= $tipi_vincolo[$vincolo['tipo']] ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?= $giorni_settimana[$vincolo['giorno_settimana']] ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?= $vincolo['ora_inizio'] ? substr($vincolo['ora_inizio'], 0, 5) : 'Tutto il giorno' ?>
                                        <?= $vincolo['ora_fine'] ? ' - ' . substr($vincolo['ora_fine'], 0, 5) : '' ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?= $vincolo['sede_nome'] ?? '-' ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?= htmlspecialchars($vincolo['motivo'] ?? '') ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?= $vincolo['attivo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $vincolo['attivo'] ? 'Attivo' : 'Inattivo' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <button onclick="deleteVincolo(<?= $vincolo['id'] ?>)" 
                                                class="text-red-600 hover:text-red-900" title="Elimina">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Gestione visualizzazione campo sede
document.getElementById('tipo').addEventListener('change', function() {
    const sedeField = document.getElementById('sedeField');
    if (this.value === 'doppia_sede') {
        sedeField.classList.remove('hidden');
    } else {
        sedeField.classList.add('hidden');
    }
});

// Inizializza lo stato del campo sede
document.addEventListener('DOMContentLoaded', function() {
    const tipoSelect = document.getElementById('tipo');
    const sedeField = document.getElementById('sedeField');
    if (tipoSelect.value === 'doppia_sede') {
        sedeField.classList.remove('hidden');
    } else {
        sedeField.classList.add('hidden');
    }
});
</script>