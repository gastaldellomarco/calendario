<?php
require_once __DIR__ . "/../algorithm/CalendarioGenerator.php";

function debugCalcola($assign, $dati) {
    $reflect = new ReflectionClass('CalendarioGenerator');
    $gen = $reflect->newInstanceWithoutConstructor();
    $method = $reflect->getMethod('calcolaLezioniDaAssegnare');
    $method->setAccessible(true);
    return $method->invoke($gen, $assign, $dati);
}

$settimane = 33;

$testcases = [
    [
        'assegnazione' => ['id' => 101, 'ore_settimanali' => 2, 'ore_annuali_previste' => 66, 'materia_distribuzione' => 'settimanale'],
        'dati' => ['anno_scolastico' => ['settimane_lezione' => $settimane, 'id' => 1]]
    ],
    [
        'assegnazione' => ['id' => 102, 'ore_settimanali' => 2, 'ore_annuali_previste' => 60, 'materia_distribuzione' => 'sparsa'],
        'dati' => ['anno_scolastico' => ['settimane_lezione' => $settimane, 'id' => 1]]
    ],
    [
        'assegnazione' => ['id' => 103, 'ore_settimanali' => 2, 'ore_annuali_previste' => 60, 'materia_distribuzione' => 'casuale'],
        'dati' => ['anno_scolastico' => ['settimane_lezione' => $settimane, 'id' => 1]]
    ]
];

foreach ($testcases as $t) {
    $res = debugCalcola($t['assegnazione'], $t['dati']);
    $sum = array_sum($res);
    echo "Distribuzione: " . $t['assegnazione']['materia_distribuzione'] . " | Totale: {$sum}\n";
    foreach ($res as $week => $val) {
        if ($val > 0) echo "Week {$week}: {$val}\n";
    }
    echo "---\n";
}

?>
