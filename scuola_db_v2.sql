-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 10, 2025 at 11:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scuola_calendario`
--

-- --------------------------------------------------------

--
-- Table structure for table `anni_scolastici`
--

CREATE TABLE `anni_scolastici` (
  `id` int(11) NOT NULL,
  `anno` varchar(9) NOT NULL COMMENT 'Es: 2024-2025',
  `data_inizio` date NOT NULL,
  `data_fine` date NOT NULL,
  `attivo` tinyint(1) DEFAULT 0,
  `settimane_lezione` int(3) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aule`
--

CREATE TABLE `aule` (
  `id` int(11) NOT NULL,
  `sede_id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `codice` varchar(20) NOT NULL,
  `tipo` enum('normale','laboratorio','palestra','aula_magna','altro') DEFAULT 'normale',
  `capienza` int(3) DEFAULT 25,
  `attrezzature` text DEFAULT NULL COMMENT 'JSON con lista attrezzature disponibili',
  `piano` varchar(10) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `attiva` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendario_lezioni`
--

CREATE TABLE `calendario_lezioni` (
  `id` int(11) NOT NULL,
  `data_lezione` date NOT NULL,
  `slot_id` int(11) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `aula_id` int(11) DEFAULT NULL,
  `sede_id` int(11) NOT NULL,
  `assegnazione_id` int(11) DEFAULT NULL COMMENT 'FK a classi_materie_docenti',
  `ore_effettive` decimal(3,2) DEFAULT 1.00 COMMENT 'Ore effettive svolte',
  `stato` enum('pianificata','confermata','svolta','cancellata','sostituita') DEFAULT 'pianificata',
  `modalita` enum('presenza','online','mista') DEFAULT 'presenza',
  `argomento` varchar(200) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `cancellata_motivo` varchar(200) DEFAULT NULL,
  `creata_automaticamente` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `versione` int(5) DEFAULT 1 COMMENT 'Numero versione per controllo concorrenza',
  `modificato_da` int(11) DEFAULT NULL COMMENT 'Utente che ha effettuato ultima modifica',
  `modificato_manualmente` tinyint(1) DEFAULT 0 COMMENT '1=modifica manuale, 0=automatica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classi`
--

CREATE TABLE `classi` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `anno_scolastico_id` int(11) NOT NULL,
  `percorso_formativo_id` int(11) NOT NULL,
  `anno_corso` int(1) NOT NULL COMMENT '1,2,3,4',
  `sede_id` int(11) NOT NULL,
  `numero_studenti` int(3) DEFAULT 20,
  `ore_settimanali_previste` int(3) DEFAULT 33,
  `aula_preferenziale_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `stato` enum('attiva','inattiva','completata','sospesa') DEFAULT 'attiva',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classi_materie_docenti`
--

CREATE TABLE `classi_materie_docenti` (
  `id` int(11) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `ore_settimanali` int(2) NOT NULL,
  `ore_annuali_previste` int(4) NOT NULL,
  `ore_effettuate` int(4) DEFAULT 0,
  `ore_rimanenti` int(4) DEFAULT NULL,
  `priorita` int(1) DEFAULT 2 COMMENT '1=alta, 2=media, 3=bassa',
  `note` text DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configurazioni`
--

CREATE TABLE `configurazioni` (
  `id` int(11) NOT NULL,
  `chiave` varchar(100) NOT NULL,
  `valore` text DEFAULT NULL,
  `tipo` enum('string','integer','boolean','json') DEFAULT 'string',
  `descrizione` text DEFAULT NULL,
  `categoria` varchar(50) DEFAULT 'generale',
  `modificabile` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `configurazioni`
--

INSERT INTO `configurazioni` (`id`, `chiave`, `valore`, `tipo`, `descrizione`, `categoria`, `modificabile`, `created_at`, `updated_at`) VALUES
(1, 'anno_scolastico_corrente', '', 'string', 'Anno scolastico attualmente attivo', 'sistema', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:02'),
(2, 'ore_slot_standard', '60', 'integer', 'Durata standard di uno slot in minuti', 'orari', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:02'),
(3, 'max_ore_consecutive', '5', 'integer', 'Massimo ore consecutive senza pausa', 'orari', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:02'),
(4, 'min_pausa_tra_lezioni', '0', 'integer', 'Minuti minimi di pausa tra slot lezione', 'orari', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:50'),
(5, 'giorni_anticipo_calendario', '30', 'integer', 'Giorni di anticipo per generazione automatica calendario', 'calendario', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:02'),
(6, 'abilita_sostituzioni_auto', 'false', 'boolean', 'Abilita suggerimento automatico sostituzioni', 'sostituzioni', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:02'),
(7, 'notifica_conflitti', 'true', 'boolean', 'Notifica automatica in caso di conflitti orario', 'notifiche', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:02'),
(8, 'genera_calendario_vacanze', 'true', 'boolean', 'Considera automaticamente i giorni di chiusura', 'calendario', 1, '2025-11-10 19:53:02', '2025-11-10 19:53:02'),
(9, 'notifica_conflitti_attiva', 'true', 'boolean', 'Abilita notifiche automatiche per conflitti', 'notifiche', 1, '2025-11-10 19:53:21', '2025-11-10 19:53:21'),
(10, 'snapshot_automatico_giorni', '7', 'integer', 'Giorni tra snapshot automatici', 'backup', 1, '2025-11-10 19:53:21', '2025-11-10 19:53:21'),
(11, 'max_conflitti_giorno', '10', 'integer', 'Numero massimo conflitti prima di blocco', 'conflitti', 1, '2025-11-10 19:53:21', '2025-11-10 19:53:21'),
(12, 'template_pubblici_abilitati', 'true', 'boolean', 'Abilita condivisione template pubblici', 'template', 1, '2025-11-10 19:53:21', '2025-11-10 19:53:21'),
(13, 'versione_database', '2.0', 'string', 'Versione corrente database', 'sistema', 1, '2025-11-10 19:53:21', '2025-11-10 19:53:21');

-- --------------------------------------------------------

--
-- Table structure for table `conflitti_orario`
--

CREATE TABLE `conflitti_orario` (
  `id` int(11) NOT NULL,
  `tipo` enum('doppia_assegnazione_docente','doppia_aula','vincolo_docente','vincolo_classe','superamento_ore','aula_non_adeguata','sede_multipla') NOT NULL DEFAULT 'doppia_assegnazione_docente',
  `gravita` enum('warning','error','critico') NOT NULL DEFAULT 'error',
  `titolo` varchar(200) NOT NULL COMMENT 'Titolo descrittivo conflitto',
  `descrizione` text NOT NULL COMMENT 'Descrizione dettagliata conflitto',
  `dati_conflitto` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON con dati specifici del conflitto' CHECK (json_valid(`dati_conflitto`)),
  `docente_id` int(11) DEFAULT NULL COMMENT 'Docente coinvolto (se applicabile)',
  `classe_id` int(11) DEFAULT NULL COMMENT 'Classe coinvolta (se applicabile)',
  `aula_id` int(11) DEFAULT NULL COMMENT 'Aula coinvolta (se applicabile)',
  `lezione_id` int(11) DEFAULT NULL COMMENT 'Lezione coinvolta (se applicabile)',
  `data_conflitto` date NOT NULL COMMENT 'Data in cui si verifica il conflitto',
  `slot_id` int(11) DEFAULT NULL COMMENT 'Slot orario conflitto',
  `risolto` tinyint(1) DEFAULT 0 COMMENT '1=conflitto risolto',
  `risolto_da` int(11) DEFAULT NULL COMMENT 'Utente che ha risolto il conflitto',
  `data_risoluzione` datetime DEFAULT NULL COMMENT 'Data/ora risoluzione',
  `metodo_risoluzione` enum('manual','automatico','ignorato') DEFAULT NULL COMMENT 'Metodo utilizzato per risolvere',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabella tracciamento conflitti orario';

-- --------------------------------------------------------

--
-- Table structure for table `docenti`
--

CREATE TABLE `docenti` (
  `id` int(11) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `codice_fiscale` varchar(16) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cellulare` varchar(20) DEFAULT NULL,
  `sede_principale_id` int(11) NOT NULL,
  `specializzazione` varchar(200) DEFAULT NULL,
  `ore_settimanali_contratto` int(3) DEFAULT 18,
  `max_ore_giorno` int(2) DEFAULT 6,
  `max_ore_settimana` int(3) DEFAULT 18,
  `max_giorni_settimana` int(1) DEFAULT 6,
  `permette_buchi_orario` tinyint(1) DEFAULT 1 COMMENT 'Può avere ore libere tra lezioni',
  `note` text DEFAULT NULL,
  `stato` enum('attivo','inattivo','sospeso') DEFAULT 'attivo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `docenti`
--

INSERT INTO `docenti` (`id`, `cognome`, `nome`, `codice_fiscale`, `email`, `telefono`, `cellulare`, `sede_principale_id`, `specializzazione`, `ore_settimanali_contratto`, `max_ore_giorno`, `max_ore_settimana`, `max_giorni_settimana`, `permette_buchi_orario`, `note`, `stato`, `created_at`, `updated_at`) VALUES
(1, 'Tosin', 'Davis', 'codicefiscale123', 'davis.tosin@gmail.com', '', '123456789123', 1, '', 18, 6, 18, 6, 1, '', 'attivo', '2025-11-10 21:51:18', '2025-11-10 21:51:18');

-- --------------------------------------------------------

--
-- Table structure for table `docenti_materie`
--

CREATE TABLE `docenti_materie` (
  `id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `preferenza` int(1) DEFAULT 3 COMMENT '1=alta, 2=media, 3=bassa',
  `abilitato` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `giorni_chiusura`
--

CREATE TABLE `giorni_chiusura` (
  `id` int(11) NOT NULL,
  `data_chiusura` date NOT NULL,
  `descrizione` varchar(200) NOT NULL,
  `tipo` enum('festivo','vacanza','chiusura','ponte') DEFAULT 'festivo',
  `ripete_annualmente` tinyint(1) DEFAULT 0 COMMENT '1=si ripete ogni anno (es: Natale)',
  `sede_id` int(11) DEFAULT NULL COMMENT 'NULL=tutte le sedi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_attivita`
--

CREATE TABLE `log_attivita` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `azione` varchar(100) NOT NULL,
  `descrizione` text NOT NULL,
  `tabella` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `utente` varchar(100) DEFAULT NULL,
  `dati_prima` text DEFAULT NULL COMMENT 'JSON con stato precedente',
  `dati_dopo` text DEFAULT NULL COMMENT 'JSON con stato successivo',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log_attivita`
--

INSERT INTO `log_attivita` (`id`, `tipo`, `azione`, `descrizione`, `tabella`, `record_id`, `utente`, `dati_prima`, `dati_dopo`, `ip_address`, `created_at`) VALUES
(1, 'creazione', 'creazione', 'Creato docente: Tosin Davis', 'docenti', 1, 'admin', NULL, NULL, '::1', '2025-11-10 21:51:18');

-- --------------------------------------------------------

--
-- Table structure for table `materie`
--

CREATE TABLE `materie` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `codice` varchar(20) NOT NULL,
  `tipo` enum('culturale','professionale','laboratoriale','stage','sostegno') DEFAULT 'culturale',
  `percorso_formativo_id` int(11) NOT NULL,
  `anno_corso` int(1) NOT NULL COMMENT '1,2,3,4',
  `ore_settimanali` int(2) DEFAULT 2,
  `ore_annuali` int(4) DEFAULT 66,
  `distribuzione` enum('settimanale','sparsa','casuale') DEFAULT 'settimanale' COMMENT 'Modalità di distribuzione delle ore (settimanale/ sparsa/ casuale)',
  `peso` int(2) DEFAULT 1 COMMENT 'Per priorità scheduling',
  `richiede_laboratorio` tinyint(1) DEFAULT 0,
  `richiede_attrezzature` text DEFAULT NULL COMMENT 'JSON lista attrezzature necessarie',
  `descrizione` text DEFAULT NULL,
  `attiva` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifiche`
--

CREATE TABLE `notifiche` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL COMMENT 'Destinatario notifica',
  `tipo` enum('info','avviso','allerta','sistema','conflitto') NOT NULL DEFAULT 'info',
  `priorita` enum('bassa','media','alta','urgente') NOT NULL DEFAULT 'media',
  `titolo` varchar(200) NOT NULL COMMENT 'Titolo breve notifica',
  `messaggio` text NOT NULL COMMENT 'Messaggio esteso',
  `letta` tinyint(1) DEFAULT 0 COMMENT '1=notifica letta',
  `riferimento_tabella` varchar(100) DEFAULT NULL COMMENT 'Nome tabella riferimento (es: calendario_lezioni)',
  `riferimento_id` int(11) DEFAULT NULL COMMENT 'ID record tabella riferimento',
  `azione_url` varchar(500) DEFAULT NULL COMMENT 'URL per azione diretta',
  `data_scadenza` datetime DEFAULT NULL COMMENT 'Data scadenza notifica',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabella gestione notifiche in-app';

-- --------------------------------------------------------

--
-- Table structure for table `orari_slot`
--

CREATE TABLE `orari_slot` (
  `id` int(11) NOT NULL,
  `numero_slot` int(2) NOT NULL COMMENT 'Numero progressivo slot (1,2,3...)',
  `ora_inizio` time NOT NULL,
  `ora_fine` time NOT NULL,
  `tipo` enum('lezione','intervallo','pausa_pranzo') DEFAULT 'lezione',
  `durata_minuti` int(3) NOT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `percorsi_formativi`
--

CREATE TABLE `percorsi_formativi` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `codice` varchar(20) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `sede_id` int(11) NOT NULL,
  `durata_anni` int(2) DEFAULT 3,
  `ore_annuali_base` int(5) DEFAULT 990,
  `ore_stage_totali` int(5) DEFAULT 0,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sedi`
--

CREATE TABLE `sedi` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `codice` varchar(10) NOT NULL,
  `indirizzo` varchar(200) DEFAULT NULL,
  `citta` varchar(100) NOT NULL,
  `cap` varchar(10) DEFAULT NULL,
  `provincia` varchar(2) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `attiva` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sedi`
--

INSERT INTO `sedi` (`id`, `nome`, `codice`, `indirizzo`, `citta`, `cap`, `provincia`, `telefono`, `email`, `attiva`, `created_at`, `updated_at`) VALUES
(1, 'Rosa', 'rosa', ' Via Schallstadt, 55', 'Rosa', '36027', 'VI', '0424 85573', 'info@irigemscuole.it', 1, '2025-11-10 21:50:35', '2025-11-10 21:50:35');

-- --------------------------------------------------------

--
-- Table structure for table `snapshot_calendario`
--

CREATE TABLE `snapshot_calendario` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL COMMENT 'Nome identificativo snapshot',
  `descrizione` text DEFAULT NULL COMMENT 'Descrizione contesto snapshot',
  `tipo` enum('manuale','automatico','pre_modifica','backup_settimanale','rollback') NOT NULL DEFAULT 'manuale',
  `dati_calendario` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Backup completo calendario in formato JSON' CHECK (json_valid(`dati_calendario`)),
  `periodo_inizio` date DEFAULT NULL COMMENT 'Data inizio periodo snapshot',
  `periodo_fine` date DEFAULT NULL COMMENT 'Data fine periodo snapshot',
  `sede_id` int(11) DEFAULT NULL COMMENT 'Sede specifica (NULL=tutte le sedi)',
  `creato_da` int(11) DEFAULT NULL COMMENT 'Utente che ha creato snapshot (NULL=sistema)',
  `dimensione_mb` decimal(8,2) DEFAULT NULL COMMENT 'Dimensione approssimativa dati',
  `versione_sistema` varchar(20) DEFAULT NULL COMMENT 'Versione software al momento snapshot',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabella backup calendario';

-- --------------------------------------------------------

--
-- Table structure for table `sostituzioni`
--

CREATE TABLE `sostituzioni` (
  `id` int(11) NOT NULL,
  `lezione_id` int(11) NOT NULL,
  `docente_originale_id` int(11) NOT NULL,
  `docente_sostituto_id` int(11) NOT NULL,
  `motivo` enum('malattia','permesso','formazione','altro') DEFAULT 'altro',
  `note` text DEFAULT NULL,
  `confermata` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stage_periodi`
--

CREATE TABLE `stage_periodi` (
  `id` int(11) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `data_inizio` date NOT NULL,
  `data_fine` date NOT NULL,
  `ore_totali_previste` int(4) DEFAULT 0,
  `ore_effettuate` int(4) DEFAULT 0,
  `descrizione` varchar(200) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `stato` enum('pianificato','in_corso','completato','cancellato') DEFAULT 'pianificato',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stage_tutor`
--

CREATE TABLE `stage_tutor` (
  `id` int(11) NOT NULL,
  `stage_periodo_id` int(11) NOT NULL,
  `docente_id` int(11) DEFAULT NULL COMMENT 'Tutor scolastico',
  `nome_tutor_aziendale` varchar(100) DEFAULT NULL,
  `azienda` varchar(150) DEFAULT NULL,
  `telefono_azienda` varchar(20) DEFAULT NULL,
  `email_azienda` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `template_orari`
--

CREATE TABLE `template_orari` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL COMMENT 'Nome identificativo template',
  `descrizione` text DEFAULT NULL COMMENT 'Descrizione utilizzo template',
  `configurazione` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Configurazione completa template in formato JSON' CHECK (json_valid(`configurazione`)),
  `tipo_template` enum('settimanale','giornaliero','classe','docente','sede') NOT NULL DEFAULT 'settimanale',
  `sede_id` int(11) DEFAULT NULL COMMENT 'Sede di riferimento (se specifica)',
  `percorso_formativo_id` int(11) DEFAULT NULL COMMENT 'Percorso formativo (se specifico)',
  `anno_corso` int(1) DEFAULT NULL COMMENT 'Anno corso (se specifico)',
  `pubblico` tinyint(1) DEFAULT 0 COMMENT '1=template disponibile per tutti gli utenti',
  `creato_da` int(11) NOT NULL COMMENT 'Utente creatore template',
  `volte_utilizzato` int(5) DEFAULT 0 COMMENT 'Contatore utilizzi template',
  `ultimo_utilizzo` datetime DEFAULT NULL COMMENT 'Data ultimo utilizzo',
  `attivo` tinyint(1) DEFAULT 1 COMMENT '1=template attivo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabella template orari ricorrenti';

-- --------------------------------------------------------

--
-- Table structure for table `utenti`
--

CREATE TABLE `utenti` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL COMMENT 'Nome utente per login',
  `email` varchar(100) NOT NULL COMMENT 'Email istituzionale',
  `password_hash` varchar(255) NOT NULL COMMENT 'Hash password bcrypt',
  `ruolo` enum('preside','vice_preside','segreteria','docente','amministratore') NOT NULL DEFAULT 'docente',
  `docente_id` int(11) DEFAULT NULL COMMENT 'Collegamento opzionale a docente per utenti docenti',
  `nome_visualizzato` varchar(100) DEFAULT NULL COMMENT 'Nome da visualizzare in interfaccia',
  `ultimo_accesso` datetime DEFAULT NULL COMMENT 'Data/ora ultimo accesso riuscito',
  `attivo` tinyint(1) DEFAULT 1 COMMENT '1=utente attivo, 0=disabilitato',
  `reset_token` varchar(100) DEFAULT NULL COMMENT 'Token per reset password',
  `reset_token_scadenza` datetime DEFAULT NULL COMMENT 'Scadenza token reset',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabella gestione utenti sistema';

--
-- Dumping data for table `utenti`
--

INSERT INTO `utenti` (`id`, `username`, `email`, `password_hash`, `ruolo`, `docente_id`, `nome_visualizzato`, `ultimo_accesso`, `attivo`, `reset_token`, `reset_token_scadenza`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@scuola.it', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'amministratore', NULL, 'Amministratore Sistema', '2025-11-10 21:45:51', 1, NULL, NULL, '2025-11-10 19:53:21', '2025-11-10 20:45:51');

-- --------------------------------------------------------

--
-- Table structure for table `vincoli_classi`
--

CREATE TABLE `vincoli_classi` (
  `id` int(11) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `tipo` enum('no_lezioni','preferenza','max_ore_giorno') DEFAULT 'preferenza',
  `giorno_settimana` int(1) DEFAULT NULL COMMENT '1=Lunedì, 7=Domenica',
  `ora_inizio` time DEFAULT NULL,
  `ora_fine` time DEFAULT NULL,
  `valore` int(3) DEFAULT NULL COMMENT 'Per max_ore_giorno',
  `motivo` varchar(200) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vincoli_docenti`
--

CREATE TABLE `vincoli_docenti` (
  `id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `tipo` enum('indisponibilita','preferenza','doppia_sede') DEFAULT 'indisponibilita',
  `giorno_settimana` int(1) NOT NULL COMMENT '1=Lunedì, 7=Domenica',
  `ora_inizio` time DEFAULT NULL,
  `ora_fine` time DEFAULT NULL,
  `sede_id` int(11) DEFAULT NULL COMMENT 'Per vincoli doppia sede',
  `motivo` varchar(200) DEFAULT NULL,
  `data_inizio` date DEFAULT NULL COMMENT 'Se vincolo temporaneo',
  `data_fine` date DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anni_scolastici`
--
ALTER TABLE `anni_scolastici`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_anno` (`anno`),
  ADD KEY `idx_attivo` (`attivo`),
  ADD KEY `idx_date` (`data_inizio`,`data_fine`);

--
-- Indexes for table `aule`
--
ALTER TABLE `aule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sede_codice` (`sede_id`,`codice`),
  ADD KEY `fk_aula_sede` (`sede_id`),
  ADD KEY `idx_tipo_attiva` (`tipo`,`attiva`);

--
-- Indexes for table `calendario_lezioni`
--
ALTER TABLE `calendario_lezioni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_data_slot_classe` (`data_lezione`,`slot_id`,`classe_id`),
  ADD KEY `fk_cal_slot` (`slot_id`),
  ADD KEY `fk_cal_classe` (`classe_id`),
  ADD KEY `fk_cal_materia` (`materia_id`),
  ADD KEY `fk_cal_docente` (`docente_id`),
  ADD KEY `fk_cal_aula` (`aula_id`),
  ADD KEY `fk_cal_sede` (`sede_id`),
  ADD KEY `fk_cal_assegnazione` (`assegnazione_id`),
  ADD KEY `idx_data_sede` (`data_lezione`,`sede_id`),
  ADD KEY `idx_stato` (`stato`),
  ADD KEY `idx_docente_data` (`docente_id`,`data_lezione`),
  ADD KEY `fk_cal_modificato_da` (`modificato_da`),
  ADD KEY `idx_versione` (`versione`),
  ADD KEY `idx_modificato_manualmente` (`modificato_manualmente`);

--
-- Indexes for table `classi`
--
ALTER TABLE `classi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nome_anno` (`nome`,`anno_scolastico_id`),
  ADD KEY `fk_classe_anno` (`anno_scolastico_id`),
  ADD KEY `fk_classe_percorso` (`percorso_formativo_id`),
  ADD KEY `fk_classe_sede` (`sede_id`),
  ADD KEY `fk_classe_aula` (`aula_preferenziale_id`),
  ADD KEY `idx_stato` (`stato`),
  ADD KEY `idx_anno_corso` (`anno_corso`);

--
-- Indexes for table `classi_materie_docenti`
--
ALTER TABLE `classi_materie_docenti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_classe_materia_docente` (`classe_id`,`materia_id`,`docente_id`),
  ADD KEY `fk_cmd_classe` (`classe_id`),
  ADD KEY `fk_cmd_materia` (`materia_id`),
  ADD KEY `fk_cmd_docente` (`docente_id`),
  ADD KEY `idx_attivo` (`attivo`);

--
-- Indexes for table `configurazioni`
--
ALTER TABLE `configurazioni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_chiave` (`chiave`),
  ADD KEY `idx_categoria` (`categoria`);

--
-- Indexes for table `conflitti_orario`
--
ALTER TABLE `conflitti_orario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_conflitto_docente` (`docente_id`),
  ADD KEY `fk_conflitto_classe` (`classe_id`),
  ADD KEY `fk_conflitto_aula` (`aula_id`),
  ADD KEY `fk_conflitto_lezione` (`lezione_id`),
  ADD KEY `fk_conflitto_slot` (`slot_id`),
  ADD KEY `fk_conflitto_risolto_da` (`risolto_da`),
  ADD KEY `idx_tipo_gravita` (`tipo`,`gravita`),
  ADD KEY `idx_risolto_data` (`risolto`,`data_conflitto`),
  ADD KEY `idx_data_conflitto` (`data_conflitto`);

--
-- Indexes for table `docenti`
--
ALTER TABLE `docenti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD UNIQUE KEY `uk_codice_fiscale` (`codice_fiscale`),
  ADD KEY `fk_docente_sede` (`sede_principale_id`),
  ADD KEY `idx_stato` (`stato`),
  ADD KEY `idx_cognome_nome` (`cognome`,`nome`);

--
-- Indexes for table `docenti_materie`
--
ALTER TABLE `docenti_materie`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_docente_materia` (`docente_id`,`materia_id`),
  ADD KEY `fk_dm_docente` (`docente_id`),
  ADD KEY `fk_dm_materia` (`materia_id`);

--
-- Indexes for table `giorni_chiusura`
--
ALTER TABLE `giorni_chiusura`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chiusura_sede` (`sede_id`),
  ADD KEY `idx_data` (`data_chiusura`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indexes for table `log_attivita`
--
ALTER TABLE `log_attivita`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo_azione` (`tipo`,`azione`),
  ADD KEY `idx_tabella_record` (`tabella`,`record_id`),
  ADD KEY `idx_utente` (`utente`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `materie`
--
ALTER TABLE `materie`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codice_percorso_anno` (`codice`,`percorso_formativo_id`,`anno_corso`),
  ADD KEY `fk_materia_percorso` (`percorso_formativo_id`),
  ADD KEY `idx_tipo_anno` (`tipo`,`anno_corso`),
  ADD KEY `idx_attiva` (`attiva`);

--
-- Indexes for table `notifiche`
--
ALTER TABLE `notifiche`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifica_utente` (`utente_id`),
  ADD KEY `idx_utente_letta` (`utente_id`,`letta`),
  ADD KEY `idx_tipo_priorita` (`tipo`,`priorita`),
  ADD KEY `idx_riferimento` (`riferimento_tabella`,`riferimento_id`),
  ADD KEY `idx_scadenza` (`data_scadenza`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `orari_slot`
--
ALTER TABLE `orari_slot`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_numero_slot` (`numero_slot`),
  ADD KEY `idx_tipo_attivo` (`tipo`,`attivo`);

--
-- Indexes for table `percorsi_formativi`
--
ALTER TABLE `percorsi_formativi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codice` (`codice`),
  ADD KEY `fk_percorso_sede` (`sede_id`),
  ADD KEY `idx_attivo` (`attivo`);

--
-- Indexes for table `sedi`
--
ALTER TABLE `sedi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codice` (`codice`),
  ADD KEY `idx_attiva` (`attiva`);

--
-- Indexes for table `snapshot_calendario`
--
ALTER TABLE `snapshot_calendario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_snapshot_sede` (`sede_id`),
  ADD KEY `fk_snapshot_creato_da` (`creato_da`),
  ADD KEY `idx_tipo_data` (`tipo`,`created_at`),
  ADD KEY `idx_periodo` (`periodo_inizio`,`periodo_fine`);

--
-- Indexes for table `sostituzioni`
--
ALTER TABLE `sostituzioni`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sost_lezione` (`lezione_id`),
  ADD KEY `fk_sost_originale` (`docente_originale_id`),
  ADD KEY `fk_sost_sostituto` (`docente_sostituto_id`);

--
-- Indexes for table `stage_periodi`
--
ALTER TABLE `stage_periodi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stage_classe` (`classe_id`),
  ADD KEY `idx_date` (`data_inizio`,`data_fine`),
  ADD KEY `idx_stato` (`stato`);

--
-- Indexes for table `stage_tutor`
--
ALTER TABLE `stage_tutor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tutor_stage` (`stage_periodo_id`),
  ADD KEY `fk_tutor_docente` (`docente_id`);

--
-- Indexes for table `template_orari`
--
ALTER TABLE `template_orari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_template_sede` (`sede_id`),
  ADD KEY `fk_template_percorso` (`percorso_formativo_id`),
  ADD KEY `fk_template_creato_da` (`creato_da`),
  ADD KEY `idx_tipo_pubblico` (`tipo_template`,`pubblico`),
  ADD KEY `idx_attivo` (`attivo`);

--
-- Indexes for table `utenti`
--
ALTER TABLE `utenti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD UNIQUE KEY `uk_docente_id` (`docente_id`),
  ADD KEY `fk_utente_docente` (`docente_id`),
  ADD KEY `idx_ruolo_attivo` (`ruolo`,`attivo`),
  ADD KEY `idx_ultimo_accesso` (`ultimo_accesso`);

--
-- Indexes for table `vincoli_classi`
--
ALTER TABLE `vincoli_classi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vincolo_classe` (`classe_id`),
  ADD KEY `idx_tipo_attivo` (`tipo`,`attivo`);

--
-- Indexes for table `vincoli_docenti`
--
ALTER TABLE `vincoli_docenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vincolo_docente` (`docente_id`),
  ADD KEY `fk_vincolo_sede` (`sede_id`),
  ADD KEY `idx_giorno_attivo` (`giorno_settimana`,`attivo`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anni_scolastici`
--
ALTER TABLE `anni_scolastici`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aule`
--
ALTER TABLE `aule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendario_lezioni`
--
ALTER TABLE `calendario_lezioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classi`
--
ALTER TABLE `classi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classi_materie_docenti`
--
ALTER TABLE `classi_materie_docenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configurazioni`
--
ALTER TABLE `configurazioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `conflitti_orario`
--
ALTER TABLE `conflitti_orario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `docenti`
--
ALTER TABLE `docenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `docenti_materie`
--
ALTER TABLE `docenti_materie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `giorni_chiusura`
--
ALTER TABLE `giorni_chiusura`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_attivita`
--
ALTER TABLE `log_attivita`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `materie`
--
ALTER TABLE `materie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifiche`
--
ALTER TABLE `notifiche`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orari_slot`
--
ALTER TABLE `orari_slot`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `percorsi_formativi`
--
ALTER TABLE `percorsi_formativi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sedi`
--
ALTER TABLE `sedi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `snapshot_calendario`
--
ALTER TABLE `snapshot_calendario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sostituzioni`
--
ALTER TABLE `sostituzioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stage_periodi`
--
ALTER TABLE `stage_periodi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stage_tutor`
--
ALTER TABLE `stage_tutor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `template_orari`
--
ALTER TABLE `template_orari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `utenti`
--
ALTER TABLE `utenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vincoli_classi`
--
ALTER TABLE `vincoli_classi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vincoli_docenti`
--
ALTER TABLE `vincoli_docenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aule`
--
ALTER TABLE `aule`
  ADD CONSTRAINT `fk_aula_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `calendario_lezioni`
--
ALTER TABLE `calendario_lezioni`
  ADD CONSTRAINT `fk_cal_assegnazione` FOREIGN KEY (`assegnazione_id`) REFERENCES `classi_materie_docenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cal_aula` FOREIGN KEY (`aula_id`) REFERENCES `aule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cal_classe` FOREIGN KEY (`classe_id`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cal_docente` FOREIGN KEY (`docente_id`) REFERENCES `docenti` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cal_materia` FOREIGN KEY (`materia_id`) REFERENCES `materie` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cal_modificato_da` FOREIGN KEY (`modificato_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cal_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cal_slot` FOREIGN KEY (`slot_id`) REFERENCES `orari_slot` (`id`);

--
-- Constraints for table `classi`
--
ALTER TABLE `classi`
  ADD CONSTRAINT `fk_classe_anno` FOREIGN KEY (`anno_scolastico_id`) REFERENCES `anni_scolastici` (`id`),
  ADD CONSTRAINT `fk_classe_aula` FOREIGN KEY (`aula_preferenziale_id`) REFERENCES `aule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_classe_percorso` FOREIGN KEY (`percorso_formativo_id`) REFERENCES `percorsi_formativi` (`id`),
  ADD CONSTRAINT `fk_classe_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`);

--
-- Constraints for table `classi_materie_docenti`
--
ALTER TABLE `classi_materie_docenti`
  ADD CONSTRAINT `fk_cmd_classe` FOREIGN KEY (`classe_id`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cmd_docente` FOREIGN KEY (`docente_id`) REFERENCES `docenti` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cmd_materia` FOREIGN KEY (`materia_id`) REFERENCES `materie` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conflitti_orario`
--
ALTER TABLE `conflitti_orario`
  ADD CONSTRAINT `fk_conflitto_aula` FOREIGN KEY (`aula_id`) REFERENCES `aule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_classe` FOREIGN KEY (`classe_id`) REFERENCES `classi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_docente` FOREIGN KEY (`docente_id`) REFERENCES `docenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_lezione` FOREIGN KEY (`lezione_id`) REFERENCES `calendario_lezioni` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_risolto_da` FOREIGN KEY (`risolto_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_slot` FOREIGN KEY (`slot_id`) REFERENCES `orari_slot` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `docenti`
--
ALTER TABLE `docenti`
  ADD CONSTRAINT `fk_docente_sede` FOREIGN KEY (`sede_principale_id`) REFERENCES `sedi` (`id`);

--
-- Constraints for table `docenti_materie`
--
ALTER TABLE `docenti_materie`
  ADD CONSTRAINT `fk_dm_docente` FOREIGN KEY (`docente_id`) REFERENCES `docenti` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dm_materia` FOREIGN KEY (`materia_id`) REFERENCES `materie` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `giorni_chiusura`
--
ALTER TABLE `giorni_chiusura`
  ADD CONSTRAINT `fk_chiusura_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `materie`
--
ALTER TABLE `materie`
  ADD CONSTRAINT `fk_materia_percorso` FOREIGN KEY (`percorso_formativo_id`) REFERENCES `percorsi_formativi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifiche`
--
ALTER TABLE `notifiche`
  ADD CONSTRAINT `fk_notifica_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `percorsi_formativi`
--
ALTER TABLE `percorsi_formativi`
  ADD CONSTRAINT `fk_percorso_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`);

--
-- Constraints for table `snapshot_calendario`
--
ALTER TABLE `snapshot_calendario`
  ADD CONSTRAINT `fk_snapshot_creato_da` FOREIGN KEY (`creato_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_snapshot_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sostituzioni`
--
ALTER TABLE `sostituzioni`
  ADD CONSTRAINT `fk_sost_lezione` FOREIGN KEY (`lezione_id`) REFERENCES `calendario_lezioni` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sost_originale` FOREIGN KEY (`docente_originale_id`) REFERENCES `docenti` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sost_sostituto` FOREIGN KEY (`docente_sostituto_id`) REFERENCES `docenti` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stage_periodi`
--
ALTER TABLE `stage_periodi`
  ADD CONSTRAINT `fk_stage_classe` FOREIGN KEY (`classe_id`) REFERENCES `classi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stage_tutor`
--
ALTER TABLE `stage_tutor`
  ADD CONSTRAINT `fk_tutor_docente` FOREIGN KEY (`docente_id`) REFERENCES `docenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tutor_stage` FOREIGN KEY (`stage_periodo_id`) REFERENCES `stage_periodi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `template_orari`
--
ALTER TABLE `template_orari`
  ADD CONSTRAINT `fk_template_creato_da` FOREIGN KEY (`creato_da`) REFERENCES `utenti` (`id`),
  ADD CONSTRAINT `fk_template_percorso` FOREIGN KEY (`percorso_formativo_id`) REFERENCES `percorsi_formativi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_template_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `utenti`
--
ALTER TABLE `utenti`
  ADD CONSTRAINT `fk_utente_docente` FOREIGN KEY (`docente_id`) REFERENCES `docenti` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vincoli_classi`
--
ALTER TABLE `vincoli_classi`
  ADD CONSTRAINT `fk_vincolo_classe` FOREIGN KEY (`classe_id`) REFERENCES `classi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vincoli_docenti`
--
ALTER TABLE `vincoli_docenti`
  ADD CONSTRAINT `fk_vincolo_docente` FOREIGN KEY (`docente_id`) REFERENCES `docenti` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vincolo_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
