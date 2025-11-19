<?php
// Migration utility: re-calculate `ore_annuali` and `peso` for existing materie records
// Usage: php migrate_materie_compute_annual_hours.php

require_once __DIR__ . '/../config/config.php';

try {
    $pdo = getPDOConnection();
} catch (Exception $e) {
    echo "ERRORE: impossibile connettersi al DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Ottieni settimane anno attivo
$settimane = 33;
try {
    $stmt = $pdo->prepare("SELECT settimane_lezione FROM anni_scolastici WHERE attivo = 1 LIMIT 1");
    $stmt->execute();
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res && isset($res['settimane_lezione'])) {
        $settimane = (int)$res['settimane_lezione'];
    }
} catch (Exception $e) {
    echo "Attenzione: impossibile determinare settimane anno; fallback a 33" . PHP_EOL;
}

$tipoToPeso = [
    'culturale' => 1,
    'professionale' => 2,
    'laboratoriale' => 3,
    'stage' => 2,
    'sostegno' => 1
];

$rows = $pdo->query("SELECT id, ore_settimanali, ore_annuali, tipo, peso FROM materie")->fetchAll(PDO::FETCH_ASSOC);

$updates = 0;
foreach ($rows as $r) {
    $id = $r['id'];
    $ore_sett = (int)$r['ore_settimanali'];
    $ore_annuali_new = (int)round($ore_sett * $settimane);
    $peso_new = $tipoToPeso[$r['tipo']] ?? 1;

    $update_sql = [];
    $params = [];

    if ((int)$r['ore_annuali'] !== $ore_annuali_new) {
        $update_sql[] = "ore_annuali = ?";
        $params[] = $ore_annuali_new;
    }

    if ((int)$r['peso'] !== $peso_new) {
        $update_sql[] = "peso = ?";
        $params[] = $peso_new;
    }

    if (!empty($update_sql)) {
        $params[] = $id;
        $sql = "UPDATE materie SET " . implode(', ', $update_sql) . " WHERE id = ?";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo "Updated materie id={$id}: ore_annuali={$ore_annuali_new}, peso={$peso_new}" . PHP_EOL;
            $updates++;
        } catch (Exception $e) {
            echo "Errore aggiornamento id={$id}: " . $e->getMessage() . PHP_EOL;
        }
    }
}

echo "DONE: $updates record(s) updated. Settimane usate: $settimane" . PHP_EOL;
