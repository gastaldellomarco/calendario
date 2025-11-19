<?php
require_once '../config/database.php';
require_once '../algorithm/CalendarioGenerator.php';

echo "=== TEST GENERAZIONE CALENDARIO CON DATI PROVA ===\n\n";

try {
    $generator = new CalendarioGenerator($db);
    
    echo "1. Avvio generazione calendario...\n";
    $result = $generator->generaCalendario(1, [
        'strategia' => 'bilanciato',
        'max_tentativi' => 3,
        'considera_preferenze' => true
    ]);
    
    echo "2. Risultato generazione:\n";
    echo "   Successo: " . ($result['success'] ? 'SI' : 'NO') . "\n";
    echo "   Lezioni assegnate: " . $result['statistiche']['lezioni_assegnate'] . "\n";
    echo "   Conflitti: " . $result['statistiche']['conflitti'] . "\n";
    echo "   Tempo esecuzione: " . round($result['statistiche']['tempo_esecuzione'], 2) . "s\n";
    
    if (!$result['success']) {
        echo "   Errore: " . $result['error'] . "\n";
    }
    
    echo "\n3. Ultimi 5 log entries:\n";
    $ultimi_log = array_slice($result['log'], -5);
    foreach ($ultimi_log as $log) {
        echo "   " . $log . "\n";
    }
    
    echo "\n4. Verifica conflitti...\n";
    $conflitti = $db->query("
        SELECT COUNT(*) as total FROM conflitti_orario 
        WHERE risolto = 0
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "   Conflitti non risolti: " . $conflitti['total'] . "\n";
    
    echo "\n5. Statistiche lezioni generate:\n";
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_lezioni,
            COUNT(DISTINCT classe_id) as classi_coperte,
            COUNT(DISTINCT docente_id) as docenti_utilizzati
        FROM calendario_lezioni 
        WHERE creata_automaticamente = 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "   Lezioni totali: " . $stats['total_lezioni'] . "\n";
    echo "   Classi coperte: " . $stats['classi_coperte'] . "\n";
    echo "   Docenti utilizzati: " . $stats['docenti_utilizzati'] . "\n";
    
} catch (Exception $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}
?>