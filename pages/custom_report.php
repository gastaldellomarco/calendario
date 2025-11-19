<?php
require_once '../includes/header.php';
require_once '../config/database.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">🔧 Report Personalizzati</h1>
        <p class="text-gray-600">Query builder visuale per creare report su misura</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar - Query Builder -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                <h3 class="text-lg font-semibold mb-4">Query Builder</h3>
                
                <!-- Selezione Tabelle -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tabelle</label>
                    <div class="space-y-2" id="tablesList">
                        <?php
                        $tables = [
                            'docenti' => '👨‍🏫 Docenti',
                            'classi' => '👥 Classi', 
                            'aule' => '🏫 Aule',
                            'calendario_lezioni' => '📅 Lezioni',
                            'materie' => '📚 Materie',
                            'sedi' => '🏢 Sedi',
                            'anni_scolastici' => '🎓 Anni Scolastici'
                        ];
                        
                        foreach ($tables as $key => $label) {
                            echo '
                            <label class="flex items-center">
                                <input type="checkbox" name="tables" value="' . $key . '" class="table-checkbox rounded border-gray-300">
                                <span class="ml-2 text-sm">' . $label . '</span>
                            </label>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Campi Selezionati -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Campi da Includere</label>
                    <div id="selectedFields" class="border border-gray-300 rounded-md p-3 min-h-32 max-h-48 overflow-y-auto">
                        <p class="text-sm text-gray-500 text-center">Seleziona una tabella per vedere i campi disponibili</p>
                    </div>
                </div>

                <!-- Filtri WHERE -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filtri (WHERE)</label>
                    <div id="whereConditions">
                        <div class="where-condition mb-2">
                            <select class="field-select w-full mb-1 border border-gray-300 rounded px-2 py-1 text-sm">
                                <option value="">Seleziona campo</option>
                            </select>
                            <select class="operator-select w-full mb-1 border border-gray-300 rounded px-2 py-1 text-sm">
                                <option value="=">=</option>
                                <option value="!=">≠</option>
                                <option value=">">></option>
                                <option value="<"><</option>
                                <option value=">=">≥</option>
                                <option value="<=">≤</option>
                                <option value="LIKE">CONTINE</option>
                                <option value="IN">IN</option>
                            </select>
                            <input type="text" class="value-input w-full border border-gray-300 rounded px-2 py-1 text-sm" placeholder="Valore">
                        </div>
                    </div>
                    <button onclick="addWhereCondition()" class="text-sm text-blue-600 hover:text-blue-800">+ Aggiungi condizione</button>
                </div>

                <!-- Raggruppamento -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Raggruppa per (GROUP BY)</label>
                    <select id="groupBy" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" multiple>
                        <option value="">Seleziona campo</option>
                    </select>
                </div>

                <!-- Ordinamento -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ordina per (ORDER BY)</label>
                    <select id="orderBy" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Seleziona campo</option>
                    </select>
                    <select id="orderDirection" class="w-full mt-1 border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="ASC">Crescente (A-Z)</option>
                        <option value="DESC">Decrescente (Z-A)</option>
                    </select>
                </div>

                <!-- Azioni -->
                <div class="space-y-2">
                    <button onclick="previewReport()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        👁️ Anteprima Report
                    </button>
                    <button onclick="saveQuery()" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        💾 Salva Query
                    </button>
                    <button onclick="exportReport()" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                        📤 Esporta Dati
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content - Anteprima e Risultati -->
        <div class="lg:col-span-3">
            <!-- Query SQL Generata -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Query SQL Generata</h3>
                <div class="bg-gray-800 text-green-400 p-4 rounded-md font-mono text-sm overflow-x-auto">
                    <code id="generatedQuery">SELECT * FROM docenti LIMIT 10</code>
                </div>
            </div>

            <!-- Anteprima Risultati -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Anteprima Risultati</h3>
                    <div class="text-sm text-gray-500">
                        <span id="resultCount">0</span> record trovati
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full table-auto" id="previewTable">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Colonna</th>
                                <th class="px-4 py-2 text-left">Tipo</th>
                                <th class="px-4 py-2 text-left">Esempio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                    Esegui l'anteprima per vedere i risultati
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Query Salvate -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h3 class="text-lg font-semibold mb-4">💾 Query Salvate</h3>
                <div id="savedQueries" class="space-y-2">
                    <!-- Le query salvate verranno caricate qui -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Struttura dati per i campi delle tabelle
const tableSchemas = {
    docenti: [
        { name: 'id', type: 'int', label: 'ID' },
        { name: 'cognome', type: 'varchar', label: 'Cognome' },
        { name: 'nome', type: 'varchar', label: 'Nome' },
        { name: 'email', type: 'varchar', label: 'Email' },
        { name: 'ore_settimanali_contratto', type: 'int', label: 'Ore Contratto' },
        { name: 'stato', type: 'enum', label: 'Stato' }
    ],
    classi: [
        { name: 'id', type: 'int', label: 'ID' },
        { name: 'nome', type: 'varchar', label: 'Nome Classe' },
        { name: 'anno_corso', type: 'int', label: 'Anno Corso' },
        { name: 'numero_studenti', type: 'int', label: 'Numero Studenti' },
        { name: 'ore_settimanali_previste', type: 'int', label: 'Ore Settimanali' },
        { name: 'stato', type: 'enum', label: 'Stato' }
    ],
    aule: [
        { name: 'id', type: 'int', label: 'ID' },
        { name: 'nome', type: 'varchar', label: 'Nome Aula' },
        { name: 'codice', type: 'varchar', label: 'Codice' },
        { name: 'tipo', type: 'enum', label: 'Tipo' },
        { name: 'capienza', type: 'int', label: 'Capienza' },
        { name: 'attiva', type: 'boolean', label: 'Attiva' }
    ]
    // ... altri schemi tabella
};

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    // Gestione selezione tabelle
    document.querySelectorAll('.table-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateAvailableFields);
    });
    
    loadSavedQueries();
});

function updateAvailableFields() {
    const selectedTables = Array.from(document.querySelectorAll('.table-checkbox:checked'))
        .map(cb => cb.value);
    
    const fieldsContainer = document.getElementById('selectedFields');
    const fieldSelects = document.querySelectorAll('.field-select');
    const groupBySelect = document.getElementById('groupBy');
    const orderBySelect = document.getElementById('orderBy');
    
    // Reset
    fieldsContainer.innerHTML = '';
    fieldSelects.forEach(select => {
        select.innerHTML = '<option value="">Seleziona campo</option>';
    });
    groupBySelect.innerHTML = '<option value="">Seleziona campo</option>';
    orderBySelect.innerHTML = '<option value="">Seleziona campo</option>';
    
    if (selectedTables.length === 0) {
        fieldsContainer.innerHTML = '<p class="text-sm text-gray-500 text-center">Seleziona una tabella per vedere i campi disponibili</p>';
        return;
    }
    
    // Popola campi per ogni tabella selezionata
    selectedTables.forEach(tableName => {
        if (tableSchemas[tableName]) {
            const tableFields = tableSchemas[tableName];
            
            // Aggiungi ai select
            tableFields.forEach(field => {
                const option = new Option(`${tableName}.${field.name} (${field.label})`, `${tableName}.${field.name}`);
                
                fieldSelects.forEach(select => {
                    select.add(option.cloneNode(true));
                });
                
                groupBySelect.add(option.cloneNode(true));
                orderBySelect.add(option.cloneNode(true));
            });
            
            // Aggiungi ai campi selezionati (checkbox)
            const tableSection = document.createElement('div');
            tableSection.className = 'mb-3';
            tableSection.innerHTML = `<div class="font-medium text-sm mb-1">${tableName}</div>`;
            
            tableFields.forEach(field => {
                const fieldId = `${tableName}_${field.name}`;
                tableSection.innerHTML += `
                    <label class="flex items-center mb-1">
                        <input type="checkbox" value="${tableName}.${field.name}" 
                               class="field-checkbox rounded border-gray-300" 
                               onchange="updateGeneratedQuery()">
                        <span class="ml-2 text-xs">${field.label} (${field.name})</span>
                    </label>
                `;
            });
            
            fieldsContainer.appendChild(tableSection);
        }
    });
    
    updateGeneratedQuery();
}

function addWhereCondition() {
    const container = document.getElementById('whereConditions');
    const newCondition = document.createElement('div');
    newCondition.className = 'where-condition mb-2';
    newCondition.innerHTML = `
        <select class="field-select w-full mb-1 border border-gray-300 rounded px-2 py-1 text-sm" onchange="updateGeneratedQuery()">
            <option value="">Seleziona campo</option>
        </select>
        <select class="operator-select w-full mb-1 border border-gray-300 rounded px-2 py-1 text-sm" onchange="updateGeneratedQuery()">
            <option value="=">=</option>
            <option value="!=">≠</option>
            <option value=">">></option>
            <option value="<"><</option>
            <option value=">=">≥</option>
            <option value="<=">≤</option>
            <option value="LIKE">CONTINE</option>
            <option value="IN">IN</option>
        </select>
        <input type="text" class="value-input w-full border border-gray-300 rounded px-2 py-1 text-sm" 
               placeholder="Valore" oninput="updateGeneratedQuery()">
        <button onclick="this.parentElement.remove(); updateGeneratedQuery();" 
                class="text-red-600 hover:text-red-800 text-sm mt-1">✕ Rimuovi</button>
    `;
    
    // Popola il field-select
    const fieldSelect = newCondition.querySelector('.field-select');
    document.querySelectorAll('.field-select option').forEach(option => {
        if (option.value) {
            fieldSelect.add(option.cloneNode(true));
        }
    });
    
    container.appendChild(newCondition);
}

function updateGeneratedQuery() {
    const selectedTables = Array.from(document.querySelectorAll('.table-checkbox:checked'))
        .map(cb => cb.value);
    
    const selectedFields = Array.from(document.querySelectorAll('.field-checkbox:checked'))
        .map(cb => cb.value);
    
    const whereConditions = Array.from(document.querySelectorAll('.where-condition'))
        .map(condition => {
            const field = condition.querySelector('.field-select').value;
            const operator = condition.querySelector('.operator-select').value;
            const value = condition.querySelector('.value-input').value;
            
            if (field && value) {
                return `${field} ${operator} '${value}'`;
            }
            return null;
        })
        .filter(cond => cond !== null);
    
    const groupBy = document.getElementById('groupBy').value;
    const orderBy = document.getElementById('orderBy').value;
    const orderDirection = document.getElementById('orderDirection').value;
    
    // Costruisci query SQL
    let query = 'SELECT ';
    query += selectedFields.length > 0 ? selectedFields.join(', ') : '*';
    query += ' FROM ' + selectedTables.join(', ');
    
    if (whereConditions.length > 0) {
        query += ' WHERE ' + whereConditions.join(' AND ');
    }
    
    if (groupBy) {
        query += ' GROUP BY ' + groupBy;
    }
    
    if (orderBy) {
        query += ' ORDER BY ' + orderBy + ' ' + orderDirection;
    }
    
    query += ' LIMIT 100';
    
    document.getElementById('generatedQuery').textContent = query;
}

async function previewReport() {
    const query = document.getElementById('generatedQuery').textContent;
    
    try {
        const response = await fetch('../api/reports_api.php?action=get_custom_report&custom_query=' + encodeURIComponent(query));
        const data = await response.json();
        
        if (data.success) {
            displayPreviewResults(data.data);
        } else {
            alert('Errore: ' + data.message);
        }
    } catch (error) {
        alert('Errore nel caricamento anteprima: ' + error.message);
    }
}

function displayPreviewResults(results) {
    const table = document.getElementById('previewTable');
    const countElement = document.getElementById('resultCount');
    
    if (results.length === 0) {
        table.innerHTML = `
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left">Colonna</th>
                    <th class="px-4 py-2 text-left">Tipo</th>
                    <th class="px-4 py-2 text-left">Esempio</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                        Nessun risultato trovato
                    </td>
                </tr>
            </tbody>
        `;
        countElement.textContent = '0';
        return;
    }
    
    // Prepara intestazioni
    const headers = Object.keys(results[0]);
    let theadHTML = '<thead><tr class="bg-gray-50">';
    headers.forEach(header => {
        theadHTML += `<th class="px-4 py-2 text-left">${header}</th>`;
    });
    theadHTML += '</tr></thead>';
    
    // Prepara corpo
    let tbodyHTML = '<tbody>';
    results.slice(0, 10).forEach(row => { // Mostra solo prime 10 righe per anteprima
        tbodyHTML += '<tr class="border-b hover:bg-gray-50">';
        headers.forEach(header => {
            tbodyHTML += `<td class="px-4 py-2 text-sm">${row[header] ?? 'NULL'}</td>`;
        });
        tbodyHTML += '</tr>';
    });
    tbodyHTML += '</tbody>';
    
    table.innerHTML = theadHTML + tbodyHTML;
    countElement.textContent = results.length;
}

function saveQuery() {
    const query = document.getElementById('generatedQuery').textContent;
    const name = prompt('Nome per salvare la query:');
    
    if (name) {
        const savedQueries = JSON.parse(localStorage.getItem('savedQueries') || '[]');
        savedQueries.push({
            name: name,
            query: query,
            created: new Date().toISOString()
        });
        
        localStorage.setItem('savedQueries', JSON.stringify(savedQueries));
        loadSavedQueries();
        alert('Query salvata con successo!');
    }
}

function loadSavedQueries() {
    const savedQueries = JSON.parse(localStorage.getItem('savedQueries') || '[]');
    const container = document.getElementById('savedQueries');
    
    if (savedQueries.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">Nessuna query salvata</p>';
        return;
    }
    
    container.innerHTML = savedQueries.map((q, index) => `
        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
            <div>
                <div class="font-medium">${q.name}</div>
                <div class="text-xs text-gray-500">${new Date(q.created).toLocaleDateString()}</div>
            </div>
            <div class="flex gap-2">
                <button onclick="loadQuery(${index})" class="text-blue-600 hover:text-blue-800 text-sm">Carica</button>
                <button onclick="deleteQuery(${index})" class="text-red-600 hover:text-red-800 text-sm">Elimina</button>
            </div>
        </div>
    `).join('');
}

function loadQuery(index) {
    const savedQueries = JSON.parse(localStorage.getItem('savedQueries') || '[]');
    if (savedQueries[index]) {
        document.getElementById('generatedQuery').textContent = savedQueries[index].query;
        previewReport();
    }
}

function deleteQuery(index) {
    if (confirm('Sei sicuro di voler eliminare questa query?')) {
        const savedQueries = JSON.parse(localStorage.getItem('savedQueries') || '[]');
        savedQueries.splice(index, 1);
        localStorage.setItem('savedQueries', JSON.stringify(savedQueries));
        loadSavedQueries();
    }
}

function exportReport() {
    const query = document.getElementById('generatedQuery').textContent;
    const format = prompt('Formato esportazione (csv, excel, json):', 'csv');
    
    if (format && ['csv', 'excel', 'json'].includes(format)) {
        window.open(`../api/reports_api.php?action=export_custom&format=${format}&query=${encodeURIComponent(query)}`, '_blank');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>