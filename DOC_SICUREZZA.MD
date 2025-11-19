# SICUREZZA PANNELLO AMMINISTRAZIONE

## Controlli di Accesso

1. **Autenticazione Obbligatoria**

   - Tutte le pagine /admin/\* richiedono login
   - Sessioni scadono dopo 1 ora di inattività

2. **Autorizzazione Ruoli**

   - Solo utenti con ruolo 'preside' o 'amministratore' possono accedere
   - Controllo ruolo in ogni pagina via `requireAuth('preside')`

3. **Protezione CSRF**
   - Tutti i form POST includono token CSRF
   - Validazione token su ogni azione

## Protezione Dati

1. **Backup Sicuri**

   - Backup compressi e crittografati (opzionale)
   - Download solo per utenti autorizzati
   - Verifica integrità file backup

2. **Log Completi**

   - Registrazione di tutte le operazioni admin
   - Tracciamento IP e orario
   - Diff dati prima/dopo modifiche

3. **Validazione Input**
   - Sanitizzazione di tutti gli input utente
   - Validazione tipo dati per configurazioni
   - Limitazione upload file

## Misure Preventive

1. **Rate Limiting**

   - Limite tentativi login (5/ora)
   - Limite operazioni backup (1/ora)
   - Limite richieste API

2. **Monitoraggio**

   - Alert per attività sospette
   - Notifiche errori critici
   - Log accessi falliti

3. **Hardening**
   - Directory backup non accessibili via web
   - File di configurazione protetti
   - Update automatici sicurezza
