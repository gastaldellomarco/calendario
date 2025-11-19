# CHECKLIST MANUTENZIONE SISTEMA

## Giornaliera

- [ ] Verifica spazio disco
- [ ] Controllo log errori
- [ ] Backup automatico (se configurato)
- [ ] Verifica stato cron jobs

## Settimanale

- [ ] Pulizia log vecchi (>30 giorni)
- [ ] Ottimizzazione tabelle database
- [ ] Verifica aggiornamenti sicurezza
- [ ] Controllo integrità backup

## Mensile

- [ ] Revisione permessi utenti
- [ ] Audit log attività
- [ ] Test restore backup
- [ ] Aggiornamento statistiche

## Trimestrale

- [ ] Review configurazioni sicurezza
- [ ] Test procedure disaster recovery
- [ ] Aggiornamento documentazione
- [ ] Performance tuning database

## Indicatori Allarme

- ❌ Spazio disco < 10%
- ❌ Errori PHP/DB frequenti
- ❌ Backup falliti consecutivi
- ❌ Tentativi login sospetti
- ❌ Memory usage > 90%
