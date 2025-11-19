<?php
// test_calendario_generator.php
require_once 'config/database.php';
require_once 'algorithm/CalendarioGenerator.php';

// Dati di test
$test_data = [
    'anno_scolastico_id' => 1,
    'opzioni' => [
        'strategia' => 'bilanciato',
        'max_tentativi' => 2,
        'considera_preferenze' => true
    ]
];

$generator = new CalendarioGenerator($db);
$result = $generator->generaCalendario(
    $test_data['anno_scolastico_id'], 
    $test_data['opzioni']
);

echo "Test Generazione Calendario:\n";
echo "Successo: " . ($result['success'] ? 'SI' : 'NO') . "\n";
echo "Lezioni assegnate: " . $result['statistiche']['lezioni_assegnate'] . "\n";
echo "Conflitti: " . $result['statistiche']['conflitti'] . "\n";
echo "Tempo: " . round($result['statistiche']['tempo_esecuzione'], 2) . "s\n";

if (!$result['success']) {
    echo "Errore: " . $result['error'] . "\n";
}
?>