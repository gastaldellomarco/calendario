# Algorithm Module

## Descrizione

Contiene la logica core di generazione dei calendari, rilevazione conflitti e ricerca dei sostituti.
Si tratta del cuore del sistema di schedulazione.

## File Inclusi

- `CalendarioGenerator.php` - Generatore del calendario (regole di assegnazione)
- `ConflittiDetector.php` - Rileva conflitti orari tra lezioni
- `SostitutoFinder.php` - Cerca docenti sostituti disponibili
- `StageCalendarioManager.php` - Gestisce i periodi di stage collegati alle classi
- `SuggerimentiRisoluzione.php` - Suggerimenti automatici per la risoluzione di conflitti
- `VincoliValidator.php` - Valida vincoli di docenti, aule, e altre risorse

## Dipendenze

- Accesso al DB (PDO): tabelle `calendario_lezioni`, `classi`, `docenti`, `orari_slot`
- Potrebbe essere utile un job scheduler per eseguire operazioni heavy (es: generazione batch)

## TODO

- [ ] Aggiungere test end-to-end per gli algoritmi principali
- [ ] Documentare la complessità algoritmica e i casi limite
