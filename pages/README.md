# Pages Module

## Descrizione

Contiene le pagine del frontend (interfaccia utente) dell'applicazione. Le pagine sono principalmente PHP che includono template e componenti e consumano API per operazioni CRUD.

## File Inclusi (esempi)

- `docenti.php` - Lista docenti e interazioni CRUD
- `docente_form.php` - Form per creare/modificare docente
- `docente_edit.php` - Pagina di dettaglio/annuale docente
- `calendario.php` - Visualizzazione del calendario (settimana/mese)
- `conflitti.php` - Visualizza conflitti di orario
- `sostituzioni.php` - Gestione sostituzioni

## Dipendenze

- Asset JS : `assets/js` (es. calendario_modifica.js)
- API: `api/*` per interazioni CRUD
- Includes: `includes/*` per manager e helper

## TODO

- [ ] Standardizzare template con componenti riutilizzabili
- [ ] Migliorare accessibilità e navigazione tra pagine
