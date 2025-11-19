# Includes Module

## Descrizione

Cartella che contiene classi e helper PHP riutilizzabili (manager di dominio, log, notifiche CRUD helpers).

## File Inclusi

- `NotificheManager.php` - Logica per la creazione e l'invio di notifiche in-app e cron
- `SostituzioniManager.php` - Gestione delle assenze e sostituzioni docenti
- `ReportsGenerator.php` - Generazione di PDF per vari report
- `BackupManager.php` - Funzionalità per backup del database
- `StageNotifier.php` - Logica per notificare scadenze e monitorare stage
- `SystemChecker.php` - Utilities e verifiche di sistema
- `Logger.php` - Logging centralizzato
- `functions.php` - Helpers e utilità generiche

## Dipendenze

- PDO per accesso DB
- tcpdf (per la generazione PDF)
- Cron jobs per esecuzione periodica

## Note

Le classi manager dovrebbero essere usate attraverso API o pagine server-side e possono essere incluse con `require_once`.

## TODO

- [ ] Aggiungere unit tests per manager chiave come SostituzioniManager
- [ ] Completa documentazione delle eccezioni e codici di errore
