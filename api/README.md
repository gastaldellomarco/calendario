# API Module

## Descrizione

Modulo che espone gli endpoint REST-like dell'applicazione (PHP API).
Ogni file sotto `api/` gestisce una risorsa specifica (docenti, classi, calendari, ecc.)

## File Inclusi

- `admin_api.php` - Endpoint amministrativi
- `aule_api.php` - CRUD per aule
- `calendario_api.php` - Operazioni sul calendario (get_lezioni, move_lezione, create_lezione, ecc.)
- `classi_api.php` - Gestione classi
- `conflitti_api.php` - Endpoints per conflitti orario
- `docenti_api.php` - Gestione docenti (list, get, create, update, delete)
- `materie_api.php` - Gestione materie
- `notifiche_api.php` - API per notifiche
- `orari_api.php` - Gestione orari e slot
- `reports_api.php` - Generazione report via API
- `sostituzioni_api.php` - Gestione sostituzioni e assenze
- `stage_api.php` - Gestione stage
- `vincoli_api.php` - Gestione vincoli docenti
- `export_docenti.php` - Export di docenti in CSV/Excel
- `segna_notifica_letta.php` - Endpoint per segnare notifiche come lette

## Dipendenze

- Database MySQL (PDO)
- PHP: Estensioni PDO, mbstring
- Sessioni PHP per autenticazione

## API Endpoints (Esempi)

### GET /api/docenti_api.php?action=list

Restituisce lista docenti
**Parametri:** page (int), limit (int), search (string)
**Response:** JSON array

### POST /api/docenti_api.php?action=create

Crea un nuovo docente
**Body (Form/JSON):** cognome, nome, email, sede_principale_id, ...
**Response:** JSON { success, id }

### GET /api/calendario_api.php?action=get_lezioni&data_inizio=YYYY-MM-DD&data_fine=YYYY-MM-DD

Restituisce lezioni per un intervallo
**Response:** JSON { success, data }

## Configurazione

Nessuna configurazione speciale richiesta. Gli endpoint utilizzano le connessioni/credential specificate in `config/database.php`.

## TODO

- [ ] Documentare meglio i parametri di tutte le API (es. action + payload)
- [ ] Aggiungere esempi per ogni endpoint
- [ ] Aggiungere test automatici per gli endpoint principali
