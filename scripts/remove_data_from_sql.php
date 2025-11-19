<?php
/**
 * Script per rimuovere tutti i dati (INSERT) da un file SQL
 * Mantiene solo la struttura (CREATE TABLE, ALTER TABLE, ecc.)
 */

$input_file = 'c:/Users/marco/Downloads/scuola_calendario (6).sql';

if (!file_exists($input_file)) {
    die("File non trovato: $input_file\n");
}

echo "Lettura file...\n";
$content = file_get_contents($input_file);
$lines = explode("\n", $content);

echo "Filtraggio righe (totale: " . count($lines) . ")...\n";
$filtered_lines = [];
$in_insert_block = false;
$skip_empty_after_insert = false;

foreach ($lines as $line_num => $line) {
    $trimmed = trim($line);
    $original_line = $line;
    
    // Salta commenti "Dumping data"
    if (preg_match('/^-- Dumping data/', $trimmed)) {
        $in_insert_block = false;
        $skip_empty_after_insert = true;
        continue;
    }
    
    // Salta righe INSERT INTO
    if (preg_match('/^INSERT INTO/i', $trimmed)) {
        $in_insert_block = true;
        $skip_empty_after_insert = true;
        continue;
    }
    
    // Se siamo in un blocco INSERT, salta righe che contengono solo valori
    if ($in_insert_block) {
        // Se la riga inizia con parentesi e numeri (valori), salta
        if (preg_match('/^\([0-9]/', $trimmed)) {
            continue;
        }
        
        // Se la riga contiene solo parentesi e punto e virgola (fine INSERT), salta
        if (preg_match('/^\);?$/', $trimmed)) {
            $in_insert_block = false;
            $skip_empty_after_insert = true;
            continue;
        }
        
        // Se la riga contiene solo una virgola, salta
        if (preg_match('/^,$/', $trimmed)) {
            continue;
        }
        
        // Se la riga contiene solo VALUES, salta
        if (preg_match('/^VALUES$/i', $trimmed)) {
            continue;
        }
        
        // Se troviamo una riga che non è parte dell'INSERT, abbiamo finito il blocco
        if (!preg_match('/^[\(,\);]/', $trimmed) && $trimmed !== '') {
            $in_insert_block = false;
        } else {
            // Se ancora sembra parte dell'INSERT, salta
            if (preg_match('/^[\(,\);]/', $trimmed)) {
                continue;
            }
        }
    }
    
    // Salta righe vuote dopo INSERT (ma non tutte le righe vuote)
    if ($skip_empty_after_insert && $trimmed === '') {
        $skip_empty_after_insert = false;
        continue;
    }
    
    $skip_empty_after_insert = false;
    
    // Aggiungi la riga al risultato
    $filtered_lines[] = $original_line;
}

echo "Scrittura file...\n";
file_put_contents($input_file, implode("\n", $filtered_lines));

echo "✅ File aggiornato: $input_file\n";
echo "Righe originali: " . count($lines) . "\n";
echo "Righe filtrate: " . count($filtered_lines) . "\n";
echo "Righe rimosse: " . (count($lines) - count($filtered_lines)) . "\n";
?>
