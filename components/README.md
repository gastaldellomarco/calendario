# Components Module

## Descrizione

Contiene i componenti UI server-side riutilizzabili (widgets e snippet HTML/PHP) per l'applicazione. Questi file sono inclusi nelle pagine secondo necessità.

## File Inclusi

- `calendario_docente_widget.php` - Widget mini calendario per docente
- `calendario_docente_mensile.php` - View mensile
- `calendario_docente_settimanale.php` - View settimanale
- `calendario_docente_widget.php` - Widget per docente
- `lezione_card.php` - Card per singola lezione
- `notification_widget.php` - Widget per notifiche

## Dipendenze

- Include accesso al DB via `$db` (variabile globale)
- Alcuni componenti usano le classi manager del modulo `includes/`

## TODO

- [ ] Convertire alcuni output in funzioni con parametri fortemente tipizzati
- [ ] Aggiungere template unit tests per visualizzazione con dati fittizi
