# Gestione Corrispettivi Shopify

Webapp interna, monoutente, per la gestione dei corrispettivi fiscali di
piu' brand e-commerce Shopify. Genera pivot mensili per aliquota IVA a
partire da export CSV e produce file Excel con dati aggregati e dati
grezzi.

Stack: PHP 8.x vanilla, MySQL (PDO), PhpSpreadsheet, Bootstrap
lato frontend. Nessun framework pesante.

> Documentazione di deploy (SiteGround), Basic Auth e istruzioni
> complete verranno aggiunte al termine dello sviluppo (ultimo step del
> piano di lavoro).

## Stato del progetto

- [x] Step 1 - Schema DB, connessione, struttura cartelle
- [ ] Step 2 - Flow di caricamento (upload + parsing/validazione CSV)
- [ ] Step 3 - Logica di calcolo pivot
- [ ] Step 4 - Salvataggio lavorazione con naming/versioning
- [ ] Step 5 - Export Excel a due fogli
- [ ] Step 6 - Pagine elenco / dettaglio / eliminazione
- [ ] Step 7 - Log operazioni
- [ ] Step 8 - Basic Auth + README completo + istruzioni deploy

## Setup locale (in corso di sviluppo)

1. `composer install`
2. Copiare `.env.example` in `.env` e compilare le credenziali del
   database dedicato a questo progetto (host, nome db, utente,
   password). Il database e' fisicamente separato da qualsiasi altro
   progetto: non condivide istanza ne' schema con altri database.
3. Creare un database MySQL vuoto ed eseguire `schema.sql` una sola
   volta.
4. Verificare la connessione:
   ```
   php scripts/cli/check_db.php
   ```

## Struttura cartelle

```
src/
  Config/      configurazione (connessione DB, ecc.)
  Core/        micro-framework (router, controller base, view)
  Controllers/ controller applicativi
  Models/      accesso ai dati (crp_brand, crp_lavorazioni, crp_ordini_raw, crp_log)
  Services/    logica di dominio (parsing CSV, calcolo pivot, naming/versioning, export Excel)
views/         template PHP lato server
assets/        css/js statici
storage/uploads/  CSV originali caricati (mai serviti direttamente)
scripts/cli/   script eseguibili da riga di comando
migrations/    script SQL incrementali successivi a schema.sql
schema.sql     schema iniziale del database dedicato (crp_*)
```

## Database

Il progetto usa un database MySQL **dedicato ed esclusivo**: nessuna
tabella di altri progetti esiste o deve mai essere referenziata in
questo schema. Tutte le tabelle applicative hanno prefisso `crp_`.
