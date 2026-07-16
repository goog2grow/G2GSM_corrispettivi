# CLAUDE.md

Guida di riferimento per lavorare su questo progetto. Leggi anche `README.md`
(setup, deploy) e `.github/DEPLOY-WITH-ACTIONS.md` (pacchetto di deploy).

## Cos'è

Webapp interna, monoutente, per la gestione dei corrispettivi fiscali di più
brand e-commerce Shopify. Da un CSV mensile per brand calcola una pivot
giorno × aliquota IVA e genera un export Excel a due fogli, con versionamento
delle revisioni e storico completo. Nessun sistema di login utente reale in
questa fase: protezione solo via Basic Auth a livello server.

**Stato**: tutti gli 8 step di sviluppo pianificati sono completi e il
progetto è **deployato e funzionante** su SiteGround
(`gestione.good2grow.io`, sottodominio dedicato).

## Stack e vincoli

- PHP 8.1+ vanilla, nessun framework — micro-router/MVC scritto a mano
  (`src/Core/Router.php`, `src/Core/View.php`)
- MySQL/MariaDB via PDO, **database dedicato ed esclusivo** a questo
  progetto — nessuna tabella/query deve mai riferirsi ad altri
  schemi/progetti (vincolo esplicito dell'utente, non negoziabile)
- PhpSpreadsheet per l'export Excel, `vlucas/phpdotenv` per `.env`
- Bootstrap 5 via CDN lato frontend, niente build step JS
- Hosting: SiteGround condiviso, deploy via upload manuale (nessun SSH
  garantito lato utente)

## Struttura cartelle

```
index.php              front controller (tutte le richieste passano da qui)
.htaccess               rewrite + Basic Auth + deny file sensibili
.htpasswd.example        template credenziali (mai un .htpasswd reale committato)
schema.sql               schema iniziale DB (crp_*), eseguito UNA sola volta
migrations/               script SQL incrementali futuri (mai riscrivere schema.sql)
src/
  Config/Database.php     PDO singleton
  Core/                   Router, View (render + layout)
  Controllers/            BrandController, LavorazioneController, ExportController
  Models/                 Brand, Lavorazione, OrdineRaw, Log — solo query, no business logic
  Services/               parsing CSV, pivot, naming/versioning, storage file, export Excel
views/                    template PHP (namespace globale, no <?php namespace)
assets/                   css/js statici
storage/uploads/          CSV originali (mai serviti direttamente, protetti da .htaccess)
scripts/cli/              check_db.php, test_pivot.php, genera_htpasswd.php
.github/workflows/        build-deploy-package.yml (zip con vendor/, no credenziali)
```

## Schema DB (prefisso `crp_`, mai altri schemi)

- **crp_brand**: `id, nome (unique), attivo, created_at`. CRUD semplice via
  `/brand` (aggiungi/disattiva, mai delete fisico).
- **crp_lavorazioni**: una riga = un caricamento CSV per brand+mese+anno.
  Colonne chiave: `brand_id, mese, anno, nome_lavorazione, numero_versione,
  is_attiva, nota, file_originale_path, totale_incassato, totale_iva,
  numero_ordini, numero_resi, deleted_at`. `totale_*`/`numero_*` sono
  **cache di riepilogo** calcolate al salvataggio (va bene che siano
  denormalizzate) — la pivot dettagliata NON è mai cacheable, va sempre
  ricalcolata dai dati grezzi.
- **crp_ordini_raw**: righe grezze normalizzate dal CSV, struttura
  volutamente a riga singola (predispone un futuro inserimento manuale senza
  refactoring). Colonna `is_manuale` presente ma non ancora usata da UI.
- **crp_log**: audit trail (`creazione`, `revisione`, `eliminazione`),
  scritto da `SalvataggioLavorazioneService` e da
  `LavorazioneController::elimina()`, mostrato in pagina di dettaglio.

## Decisioni di business logic (non ovvie, non reinventare)

- **Naming lavorazione**: `Corrispettivi Shopify {MeseItaliano} {Anno}
  {Brand}`. Prima versione di una combinazione brand+mese+anno = nome
  pulito. Versioni successive = suffisso `_rev_{datetime_upload}`
  (`NamingService`). Il numero di versione **conta tutti i caricamenti
  storici della combinazione, incluse le versioni soft-deleted**
  (`Lavorazione::contaVersioniEsistenti`), così la numerazione riflette la
  vera storia di caricamento.
- **Anti-sovrascrittura**: mai UPDATE distruttivo sui dati di una
  lavorazione precedente. La versione attiva precedente viene solo
  disattivata (`is_attiva = 0`); ogni caricamento crea sempre nuove righe
  `crp_lavorazioni` + `crp_ordini_raw`. File fisico sempre con nome univoco
  (timestamp di upload sempre incluso, anche per v1) — vedi `FileStorage`.
- **Soft-delete senza promozione automatica**: `Lavorazione::softDelete()`
  marca `deleted_at` + `is_attiva = 0`. Se la lavorazione eliminata era
  quella attiva, **nessuna versione precedente viene promossa**: il
  periodo resta senza lavorazione attiva finché non arriva un nuovo
  caricamento. Comportamento intenzionale, non un bug.
- **Pivot on-the-fly**: `PivotCalculator` ricalcola sempre dai dati grezzi
  (mai pre-aggregato a DB), sia in fase di anteprima upload sia in
  dettaglio/export. Righe = tutti i giorni del mese (anche a zero),
  colonne = aliquote rilevate **dinamicamente** (mai hardcodate). Calcola
  due famiglie di totali distinte:
  - totali "pivot" = solo righe che ricadono nel mese/anno selezionato;
  - totali "senza split" = **tutte** le righe del file, indipendentemente
    da giorno/aliquota — usati come riepilogo sintetico e come controllo
    di coerenza quando ci sono righe con `paidDate` fuori periodo.
- **Reso**: isolato in `ResoRules::isReso()` — assunzione attuale (nessun
  reso nei file di esempio originali): `totPaid < 0`. Se il formato reale
  cambia, modificare **solo** questo metodo.
- **`OrdineRawMapper`**: converte le righe DB (`crp_ordini_raw`, colonne
  `paid_date`/`tot_paid`/...) nella shape CSV (`paidDate`/`totPaid`/...)
  usata da `PivotCalculator`. Serve perché la pivot va ricalcolata anche
  da dati già salvati (dettaglio, export), non solo durante l'upload. Nota:
  la colonna `brand` non è salvata per riga in `crp_ordini_raw` (è
  costante sulla lavorazione) — nell'export Excel viene ricostruita dal
  brand della lavorazione.
- **CSV atteso**: 14 colonne esatte, ordine fisso (vedi header in
  `CsvParser::EXPECTED_HEADERS`). Header non conforme = errore bloccante.
  Righe malformate vengono scartate singolarmente con messaggio, senza
  bloccare l'intero file. Delimitatore rilevato automaticamente (`,` o
  `;`).

## Basic Auth — gotcha reale scoperto in deploy (importante)

**Usare sempre hash APR1-MD5 (`$apr1$...`), mai bcrypt (`$2y$...`), in
`.htpasswd`.** Su questo progetto, un hash bcrypt tecnicamente valido ha
causato `500 Internal Server Error` su tutto il sito su SiteGround, perché
non tutte le build di Apache/APR-util supportano bcrypt in `AuthUserFile`
(dipende da come APR-util è stata compilata). APR1-MD5 è il formato
storico, supportato universalmente. `scripts/cli/genera_htpasswd.php`
implementa APR1-MD5 in puro PHP (verificato byte per byte contro
`htpasswd -nbm` e `openssl passwd -apr1`), perché né `password_hash()`
(solo bcrypt/argon2) né `crypt()` su glibc (registra solo l'id `$1$`, non
`$apr1$`) lo supportano nativamente.

Altri gotcha di deploy reali incontrati (documentati anche in README):

- File Manager di SiteGround nasconde i dotfile di default e a volte, in
  fase di rinomina manuale, **perde il punto iniziale** (es. `.env` →
  `env`, `.htpasswd` → `htpasswd`): se l'app si comporta come se `.env`
  non esistesse (credenziali DB vuote, `APP_DEBUG` senza effetto), è
  quasi sempre questo.
- Il path mostrato da File Manager (es. `/public_html/`) è spesso
  **relativo**, non il path assoluto reale richiesto da `AuthUserFile`.
  Se serve, un piccolo script con `echo __DIR__;` caricato temporaneamente
  risolve il dubbio in un secondo (va cancellato subito dopo l'uso).
  Su SiteGround il path reale è tipicamente
  `/home/<account>/www/<sottodominio>/public_html/`.
- Il nome database su SiteGround include spesso un prefisso legato
  all'account, diverso dal nome digitato in fase di creazione; l'utente
  MySQL va **esplicitamente associato al database** in "Manage Users" —
  errore MySQL 1044 "Access denied ... to database" quasi sempre significa
  questo, non una password sbagliata.
- SiteGround crea automaticamente un file `php_errorlog` (senza punto)
  nella document root quando ci sono errori PHP: `.htaccess` lo blocca
  esplicitamente (`^php_errorlog$`), perché la regola generica
  `\.(env|sql|md|log)$` richiede un punto letterale e non lo copre.
- Il deploy va fatto su un **sottodominio dedicato**, non una sottocartella
  di un dominio con già un altro sito: l'app usa path assoluti (`/lavorazioni`,
  `/brand`, redirect) senza base path configurabile.

## Deploy

- Pacchetto via GitHub Actions (`.github/workflows/build-deploy-package.yml`):
  ad ogni push su branch `claude/**`, builda `vendor/` con
  `composer install --no-dev` e produce uno zip scaricabile come artifact
  (nessuna credenziale coinvolta, nessun secret). **Non è incrementale**:
  contiene sempre tutti i file. `.env`/`.htpasswd` non sono mai inclusi
  (vanno creati a mano sul server); `.htaccess` invece **è incluso** — se
  il server ha un `AuthUserFile` con path specifico del proprio ambiente,
  va ri-verificato/ri-editato dopo ogni estrazione completa del pacchetto.
- `composer.lock` deve restare risolvibile per PHP 8.1 (il minimo
  dichiarato in `composer.json`): se rigenerato in locale con una PHP più
  recente, verificare che non blocchi versioni di pacchetti (es.
  `maennchen/zipstream-php`) che richiedono un PHP più recente di quello
  dichiarato. Vedi commit "Fix composer.lock" nella history per il
  procedimento (`composer config platform.php 8.1.0`, `composer update`,
  poi rimuovere l'override).

## Metodo di lavoro seguito finora

Ogni step è stato sviluppato e **validato end-to-end prima di passare al
successivo** (mai solo `php -l`): server PHP integrato + MariaDB locale
per i flussi applicativi, un vero Apache locale (con `mod_rewrite` +
`mod_authn_file`) per validare `.htaccess`/Basic Auth, lettura del file
`.xlsx` generato via PhpSpreadsheet per verificare l'export cella per
cella. Ambienti di test sempre ripuliti (DB/utenti/file temporanei) dopo
la verifica, mai lasciati residui nel repo o committati per errore
(`.env`, `.htpasswd` reali sono in `.gitignore`).

## Sviluppi futuri previsti (non implementati, schema già predisposto)

- Sistema di utenze reali con login, in sostituzione della sola Basic Auth
- Inserimento manuale di un ordine "saltato" da Shopify (form diretto su
  `crp_ordini_raw`, già a riga singola con colonna `is_manuale` pronta)
- Trasformazione manuale di una riga in "reso" (basta estendere
  `ResoRules::isReso()`, isolata apposta per questo)
