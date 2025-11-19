<?php
/**
 * Script per creare le tabelle mancanti per il sistema stage
 * Eseguire questo script una volta per creare stage_giorni e stage_documenti
 */

require_once '../config/config.php';
require_once '../config/database.php';

echo "Creazione tabelle stage...\n\n";

try {
    $pdo = Database::getConnection();
    
    // Tabella stage_giorni
    echo "Creazione tabella stage_giorni...\n";
    $sql_giorni = "
    CREATE TABLE IF NOT EXISTS `stage_giorni` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `stage_periodo_id` int(11) NOT NULL,
      `data` date NOT NULL,
      `ore_effettuate` decimal(4,2) DEFAULT 0.00 COMMENT 'Ore effettuate nel giorno',
      `presenza` tinyint(1) DEFAULT 1 COMMENT '1=presente, 0=assente',
      `note` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_stage_data` (`stage_periodo_id`, `data`),
      KEY `fk_giorno_stage` (`stage_periodo_id`),
      KEY `idx_data` (`data`),
      CONSTRAINT `fk_giorno_stage` FOREIGN KEY (`stage_periodo_id`) REFERENCES `stage_periodi` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro presenze giornaliere stage'
    ";
    
    $pdo->exec($sql_giorni);
    echo "✓ Tabella stage_giorni creata con successo\n\n";
    
    // Tabella stage_documenti
    echo "Creazione tabella stage_documenti...\n";
    $sql_documenti = "
    CREATE TABLE IF NOT EXISTS `stage_documenti` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `stage_periodo_id` int(11) NOT NULL,
      `tipo_documento` varchar(50) NOT NULL COMMENT 'Es: valutazione_finale, registro_presenze, convenzione, altro',
      `nome_file` varchar(255) NOT NULL COMMENT 'Nome file salvato sul server',
      `nome_originale` varchar(255) NOT NULL COMMENT 'Nome file originale caricato',
      `dimensione` int(11) DEFAULT NULL COMMENT 'Dimensione in bytes',
      `mime_type` varchar(100) DEFAULT NULL COMMENT 'Tipo MIME del file',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `fk_documento_stage` (`stage_periodo_id`),
      KEY `idx_tipo` (`tipo_documento`),
      CONSTRAINT `fk_documento_stage` FOREIGN KEY (`stage_periodo_id`) REFERENCES `stage_periodi` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documenti associati agli stage'
    ";
    
    $pdo->exec($sql_documenti);
    echo "✓ Tabella stage_documenti creata con successo\n\n";
    
    echo "✅ Tutte le tabelle sono state create con successo!\n";
    
} catch (PDOException $e) {
    echo "❌ Errore durante la creazione delle tabelle:\n";
    echo $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Errore:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>

