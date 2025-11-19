<?php
require_once '../../config/config.php';
requireAuth('preside');

$pageTitle = "Importa/Esporta Dati";
include '../../includes/header.php';

$importResult = '';
$exportResult = '';

// Gestione import
if (($_POST['action'] ?? '') === 'import_data') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    requireCsrfToken($csrf_token);
    
    $tipo_dato = $_POST['tipo_dato'] ?? '';
    $sovrascrivi = isset($_POST['sovrascrivi']);
    $salta_errori = isset($_POST['salta_errori']);
    
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        $importResult = processImport($tipo_dato, $file_tmp, $sovrascrivi, $salta_errori);
    } else {
        $errorCode = $_FILES['file_csv']['error'] ?? UPLOAD_ERR_NO_FILE;
        $importResult = "Errore nel caricamento file: " . $errorCode;
    }
}

// Gestione export
if (($_POST['action'] ?? '') === 'export_data') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    requireCsrfToken($csrf_token);
    
    $tipo_dato = $_POST['tipo_dato_export'] ?? '';
    $filtro_stato = $_POST['filtro_stato'] ?? '';
    $formato = $_POST['formato'] ?? 'csv';
    
    $exportResult = processExport($tipo_dato, $filtro_stato, $formato);
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Importa/Esporta Dati</h1>
        <p class="text-gray-600">Gestisci importazione ed esportazione dati di sistema</p>
    </div>

    <!-- Risultati -->
    <?php if ($importResult): ?>
    <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
        <pre class="whitespace-pre-wrap"><?php echo htmlspecialchars($importResult); ?></pre>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Importa Dati -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Importa Dati</h2>
            </div>
            <div class="p-6">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="import_data">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo Dati</label>
                            <select name="tipo_dato" required class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleziona tipo...</option>
                                <option value="docenti">Docenti</option>
                                <option value="classi">Classi</option>
                                <option value="materie">Materie</option>
                                <option value="aule">Aule</option>
                                <option value="studenti">Studenti</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">File CSV</label>
                            <input type="file" name="file_csv" accept=".csv,.txt" required 
                                   class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Formato CSV con intestazioni nella prima riga</p>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="sovrascrivi" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-900">Sovrascrivi duplicati</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="salta_errori" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-900">Salta righe con errori</span>
                            </label>
                        </div>
                        
                        <div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                Importa Dati
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Template CSV -->
                <div class="mt-6 border-t border-gray-200 pt-6">
                    <h3 class="text-md font-medium text-gray-900 mb-2">Template CSV</h3>
                    <div class="space-y-2 text-sm">
                        <p><a href="#" onclick="downloadTemplate('docenti')" class="text-blue-600 hover:text-blue-900">Template Docenti</a></p>
                        <p><a href="#" onclick="downloadTemplate('classi')" class="text-blue-600 hover:text-blue-900">Template Classi</a></p>
                        <p><a href="#" onclick="downloadTemplate('materie')" class="text-blue-600 hover:text-blue-900">Template Materie</a></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Esporta Dati -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Esporta Dati</h2>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="export_data">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo Dati</label>
                            <select name="tipo_dato_export" required class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleziona tipo...</option>
                                <option value="docenti">Docenti</option>
                                <option value="classi">Classi</option>
                                <option value="materie">Materie</option>
                                <option value="aule">Aule</option>
                                <option value="utenti">Utenti</option>
                                <option value="calendario">Calendario Lezioni</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Filtro Stato</label>
                            <select name="filtro_stato" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Tutti</option>
                                <option value="attivo">Solo attivi</option>
                                <option value="inattivo">Solo inattivi</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Formato</label>
                            <select name="formato" class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                                <option value="excel">Excel</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                                Esporta Dati
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Istruzioni -->
    <div class="mt-8 bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Istruzioni Importazione</h2>
        </div>
        <div class="p-6">
            <div class="prose max-w-none">
                <h3>Formato CSV Richiesto</h3>
                <ul>
                    <li>File deve essere in formato CSV (Comma Separated Values)</li>
                    <li>Prima riga deve contenere le intestazioni delle colonne</li>
                    <li>Encoding: UTF-8</li>
                    <li>Separatore: Virgola (,)</li>
                    <li>Testi con virgole devono essere racchiusi tra doppi apici (")</li>
                </ul>
                
                <h3>Colonne per Docenti</h3>
                <pre class="bg-gray-100 p-3 rounded text-sm">cognome,nome,email,codice_fiscale,telefono,cellulare,sede_principale_id,ore_settimanali_contratto</pre>
                
                <h3>Colonne per Classi</h3>
                <pre class="bg-gray-100 p-3 rounded text-sm">nome,anno_scolastico_id,percorso_formativo_id,anno_corso,sede_id,numero_studenti,aula_preferenziale_id</pre>
                
                <h3>Note Importanti</h3>
                <ul>
                    <li>Gli ID di riferimento (sede_id, etc.) devono esistere nel database</li>
                    <li>Le email devono essere univoche</li>
                    <li>I codici fiscale devono essere validi e univoci</li>
                    <li>In caso di errori, il sistema mostrerà i dettagli del problema</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function downloadTemplate(tipo) {
    const templates = {
        docenti: "cognome,nome,email,codice_fiscale,telefono,cellulare,sede_principale_id,ore_settimanali_contratto\nRossi,Mario,m.rossi@scuola.it,RSSMRA80A01H501U,055123456,3331234567,1,18",
        classi: "nome,anno_scolastico_id,percorso_formativo_id,anno_corso,sede_id,numero_studenti,aula_preferenziale_id\n3A-AFM,1,1,3,1,25,1",
        materie: "nome,codice,tipo,percorso_formativo_id,anno_corso,ore_settimanali,richiede_laboratorio\nMatematica,MAT01,culturale,1,1,4,0"
    };
    
    const content = templates[tipo];
    const blob = new Blob([content], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `template_${tipo}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<?php
function processImport($tipo, $file_tmp, $sovrascrivi, $salta_errori) {
    $result = "";
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        // Leggi intestazioni
        $headers = fgetcsv($handle, 1000, ",");
        
        if (!$headers) {
            return "Errore: File CSV vuoto o formato non valido";
        }
        
        $line = 1;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $line++;
            
            if (count($data) !== count($headers)) {
                $errors[] = "Riga $line: Numero di colonne non corrispondente";
                if (!$salta_errori) break;
                $skipped++;
                continue;
            }
            
            $row = array_combine($headers, $data);
            
            try {
                switch ($tipo) {
                    case 'docenti':
                        $imported += importDocente($row, $sovrascrivi);
                        break;
                    case 'classi':
                        $imported += importClasse($row, $sovrascrivi);
                        break;
                    case 'materie':
                        $imported += importMateria($row, $sovrascrivi);
                        break;
                }
            } catch (Exception $e) {
                $errors[] = "Riga $line: " . $e->getMessage();
                if (!$salta_errori) break;
                $skipped++;
            }
        }
        fclose($handle);
    }
    
    $result = "Import completato:\n";
    $result .= "- Record importati: $imported\n";
    $result .= "- Record saltati: $skipped\n";
    
    if ($errors) {
        $result .= "\nErrori:\n" . implode("\n", array_slice($errors, 0, 10));
        if (count($errors) > 10) {
            $result .= "\n... e altri " . (count($errors) - 10) . " errori";
        }
    }
    
    logActivity('import', $tipo, 0, "Import $tipo: $imported importati, $skipped saltati");
    return $result;
}

function importDocente($data, $sovrascrivi) {
    // Validazione
    if (empty($data['cognome']) || empty($data['nome']) || empty($data['email'])) {
        throw new Exception("Cognome, nome ed email obbligatori");
    }
    
    // Verifica esistenza per email
    $existing = Database::queryOne("SELECT id FROM docenti WHERE email = ?", [$data['email']]);
    
    if ($existing && !$sovrascrivi) {
        throw new Exception("Docente con email {$data['email']} già esistente");
    }
    
    $params = [
        $data['cognome'], $data['nome'], $data['email'],
        $data['codice_fiscale'] ?? null, $data['telefono'] ?? null,
        $data['cellulare'] ?? null, intval($data['sede_principale_id'] ?? 1),
        intval($data['ore_settimanali_contratto'] ?? 18)
    ];
    
    if ($existing && $sovrascrivi) {
        Database::query("UPDATE docenti SET cognome=?, nome=?, codice_fiscale=?, telefono=?, cellulare=?, sede_principale_id=?, ore_settimanali_contratto=? WHERE id=?", 
                       array_merge($params, [$existing['id']]));
    } else {
        Database::query("INSERT INTO docenti (cognome, nome, email, codice_fiscale, telefono, cellulare, sede_principale_id, ore_settimanali_contratto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $params);
    }
    
    return 1;
}

function processExport($tipo, $filtro_stato, $formato) {
    switch ($tipo) {
        case 'docenti':
            $data = exportDocenti($filtro_stato);
            $filename = "docenti_" . date('Y-m-d');
            break;
        case 'classi':
            $data = exportClassi($filtro_stato);
            $filename = "classi_" . date('Y-m-d');
            break;
        default:
            return "Tipo di esportazione non supportato";
    }
    
    switch ($formato) {
        case 'csv':
            exportCSV($data, $filename);
            break;
        case 'json':
            exportJSON($data, $filename);
            break;
        default:
            return "Formato non supportato";
    }
    
    logActivity('export', $tipo, 0, "Esportazione $tipo in formato $formato");
    return "Esportazione completata. Download iniziato.";
}

function exportDocenti($filtro_stato) {
    $where = "";
    $params = [];
    
    if ($filtro_stato === 'attivo') {
        $where = "WHERE stato = 'attivo'";
    } elseif ($filtro_stato === 'inattivo') {
        $where = "WHERE stato != 'attivo'";
    }
    
    return Database::queryAll("SELECT * FROM docenti $where ORDER BY cognome, nome", $params);
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Intestazioni
        fputcsv($output, array_keys($data[0]));
        
        // Dati
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportJSON($data, $filename) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function exportClassi($filtro_stato) {
    $where = "";
    $params = [];
    
    if ($filtro_stato === 'attivo') {
        $where = "WHERE stato = 'attivo'";
    } elseif ($filtro_stato === 'inattivo') {
        $where = "WHERE stato != 'attivo'";
    }
    
    return Database::queryAll("SELECT * FROM classi $where ORDER BY nome", $params);
}

function importClasse($data, $sovrascrivi) {
    // Validazione
    if (empty($data['nome'])) {
        throw new Exception("Nome classe obbligatorio");
    }
    
    // Verifica esistenza per nome
    $existing = Database::queryOne("SELECT id FROM classi WHERE nome = ?", [$data['nome']]);
    
    if ($existing && !$sovrascrivi) {
        throw new Exception("Classe con nome {$data['nome']} già esistente");
    }
    
    $params = [
        $data['nome'],
        intval($data['anno_scolastico_id'] ?? 1),
        intval($data['percorso_formativo_id'] ?? 1),
        intval($data['anno_corso'] ?? 1),
        intval($data['sede_id'] ?? 1),
        intval($data['numero_studenti'] ?? 0),
        intval($data['aula_preferenziale_id'] ?? null)
    ];
    
    if ($existing && $sovrascrivi) {
        Database::query("UPDATE classi SET anno_scolastico_id=?, percorso_formativo_id=?, anno_corso=?, sede_id=?, numero_studenti=?, aula_preferenziale_id=? WHERE id=?", 
                       array_merge(array_slice($params, 1), [$existing['id']]));
    } else {
        Database::query("INSERT INTO classi (nome, anno_scolastico_id, percorso_formativo_id, anno_corso, sede_id, numero_studenti, aula_preferenziale_id) VALUES (?, ?, ?, ?, ?, ?, ?)", $params);
    }
    
    return 1;
}

function importMateria($data, $sovrascrivi) {
    // Validazione
    if (empty($data['nome']) || empty($data['codice'])) {
        throw new Exception("Nome e codice materia obbligatori");
    }
    
    // Verifica esistenza per codice
    $existing = Database::queryOne("SELECT id FROM materie WHERE codice = ?", [$data['codice']]);
    
    if ($existing && !$sovrascrivi) {
        throw new Exception("Materia con codice {$data['codice']} già esistente");
    }
    
    $params = [
        $data['nome'],
        $data['codice'],
        $data['tipo'] ?? 'culturale',
        intval($data['percorso_formativo_id'] ?? 1),
        intval($data['anno_corso'] ?? 1),
        intval($data['ore_settimanali'] ?? 0),
        isset($data['richiede_laboratorio']) ? (intval($data['richiede_laboratorio']) ? 1 : 0) : 0
    ];
    
    if ($existing && $sovrascrivi) {
        Database::query("UPDATE materie SET nome=?, tipo=?, percorso_formativo_id=?, anno_corso=?, ore_settimanali=?, richiede_laboratorio=? WHERE id=?", 
                       array_merge(array_slice($params, 0, 1), array_slice($params, 2), [$existing['id']]));
    } else {
        Database::query("INSERT INTO materie (nome, codice, tipo, percorso_formativo_id, anno_corso, ore_settimanali, richiede_laboratorio) VALUES (?, ?, ?, ?, ?, ?, ?)", $params);
    }
    
    return 1;
}
?>

<?php include '../../includes/footer.php'; ?>