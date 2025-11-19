<?php
require_once '../includes/auth_check.php';

$pdo = getPDOConnection();
// Ottieni connessione PDO globalmente
$pdo = getPDOConnection();

// Verifica permessi
if (!in_array($_SESSION['ruolo'], ['preside', 'vice_preside', 'segreteria', 'amministratore'])) {
    http_response_code(403);
    echo 'Accesso negato';
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=docenti_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Forza BOM UTF-8 per compatibilità con Excel/Windows
echo "\xEF\xBB\xBF";

// Intestazione CSV
fputcsv($output, [
    'Cognome',
    'Nome', 
    'Email',
    'Codice Fiscale',
    'Telefono',
    'Cellulare',
    'Sede Principale',
    'Specializzazione',
    'Ore Settimanali Contratto',
    'Max Ore/Giorno',
    'Max Ore/Settimana',
    'Permette Buchi Orario',
    'Stato',
    'Note'
]);

// Query docenti
// Costruzione query con PDO
$sql = "SELECT d.*, s.nome as sede_nome 
        FROM docenti d 
        LEFT JOIN sedi s ON d.sede_principale_id = s.id 
        WHERE 1=1";

$params = [];
if (!empty($_GET['search'])) {
    $search_term = '%' . sanitizeInput($_GET['search']) . '%';
    $sql .= " AND (d.cognome LIKE :search OR d.nome LIKE :search OR d.email LIKE :search)";
    $params[':search'] = $search_term;
}

if (!empty($_GET['sede'])) {
    $sql .= " AND d.sede_principale_id = :sede";
    $params[':sede'] = (int)$_GET['sede'];
}

if (!empty($_GET['stato'])) {
    $sql .= " AND d.stato = :stato";
    $params[':stato'] = sanitizeInput($_GET['stato']);
}

$sql .= " ORDER BY d.cognome, d.nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

while ($docente = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $docente['cognome'],
        $docente['nome'],
        $docente['email'],
        $docente['codice_fiscale'],
        $docente['telefono'],
        $docente['cellulare'],
        $docente['sede_nome'],
        $docente['specializzazione'],
        $docente['ore_settimanali_contratto'],
        $docente['max_ore_giorno'],
        $docente['max_ore_settimana'],
        $docente['permette_buchi_orario'] ? 'Sì' : 'No',
        $docente['stato'],
        $docente['note']
    ]);
}

fclose($output);
exit;