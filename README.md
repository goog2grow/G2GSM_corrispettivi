# Gestione Corrispettivi Shopify

Webapp interna, monoutente, per la gestione dei corrispettivi fiscali di
piu' brand e-commerce Shopify. A partire da un export CSV mensile per
brand, calcola una pivot giorno x aliquota IVA e genera un file Excel a
due fogli (pivot + dati grezzi), con versionamento delle revisioni e
storico completo.

Stack: PHP 8.x vanilla (nessun framework, micro-router/MVC scritto a
mano), MySQL/PDO, [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/)
per l'export Excel, Bootstrap 5 (via CDN) lato frontend.

## Stato del progetto

- [x] Step 1 - Schema DB, connessione, struttura cartelle
- [x] Step 2 - Flow di caricamento (upload + parsing/validazione CSV)
- [x] Step 3 - Logica di calcolo pivot
- [x] Step 4 - Salvataggio lavorazione con naming/versioning
- [x] Step 5 - Export Excel a due fogli
- [x] Step 6 - Pagine elenco / dettaglio / eliminazione
- [x] Step 7 - Log operazioni
- [x] Step 8 - Basic Auth + README completo + istruzioni deploy

## Struttura cartelle

```
index.php          front controller (tutte le richieste passano da qui)
.htaccess          rewrite + Basic Auth (vedi sezione dedicata sotto)
.htpasswd.example  template per le credenziali Basic Auth
schema.sql         schema iniziale del DB dedicato (tabelle crp_*)
migrations/        script SQL incrementali successivi a schema.sql
src/
  Config/          connessione DB (PDO)
  Core/            micro-framework: Router, View
  Controllers/      controller applicativi
  Models/          accesso ai dati (crp_brand, crp_lavorazioni, crp_ordini_raw, crp_log)
  Services/        logica di dominio: parsing/validazione CSV, calcolo
                    pivot, naming/versioning, archiviazione file, export Excel
views/             template PHP lato server
assets/            css/js statici
storage/uploads/   CSV originali caricati (mai serviti direttamente, protetti da .htaccess)
scripts/cli/       script da riga di comando (verifica DB, test pivot, generazione .htpasswd)
```

## Requisiti

- PHP >= 8.1 con estensioni `pdo_mysql`, `mbstring` (incluse di default
  nella maggior parte degli hosting, incluso SiteGround)
- MySQL/MariaDB (database dedicato a questo progetto)
- Composer (solo in fase di build/deploy, non serve sul server se si
  carica gia' la cartella `vendor/` generata)
- Apache con `mod_rewrite` e `mod_authn_file`/`mod_auth_basic` abilitati
  (standard su SiteGround)

## Setup locale

1. `composer install`
2. Copiare `.env.example` in `.env` e compilare le credenziali del
   database dedicato a questo progetto (host, nome db, utente,
   password). Il database e' fisicamente separato da qualsiasi altro
   progetto: non condivide istanza ne' schema con altri database, e il
   codice non fa mai riferimento a tabelle/schemi esterni a questo.
3. Creare un database MySQL vuoto ed eseguire **una sola volta**
   `schema.sql`.
4. Verificare la connessione:
   ```
   php scripts/cli/check_db.php
   ```
5. Servire l'app (es. `php -S localhost:8000 index.php` per sviluppo
   locale rapido - nota: il server integrato di PHP ignora `.htaccess`,
   quindi Basic Auth e regole di deny non sono attive in questa
   modalita'; per testarle serve un vero Apache).

### Testare la logica di pivot da riga di comando

Utile per validare/rivalidare la logica di aggregazione senza passare
dalla UI:

```
php scripts/cli/test_pivot.php path/al/file.csv <mese> <anno>
```

## Database

Il progetto usa un database MySQL **dedicato ed esclusivo**: nessuna
tabella di altri progetti esiste o deve mai essere referenziata in
questo schema. Tutte le tabelle applicative hanno prefisso `crp_`
(`crp_brand`, `crp_lavorazioni`, `crp_ordini_raw`, `crp_log`).

Eventuali modifiche future allo schema vanno fatte con script di
migrazione incrementali in `migrations/` (es. `001_descrizione.sql`),
mai riscrivendo `schema.sql` su un database che contiene gia' dati.

## Basic Auth

In questa versione non esiste un sistema di login utente vero: l'intera
applicazione e' protetta da **Basic Auth via `.htaccess`**, a livello
di server web. Va configurata prima di esporre il sito.

File coinvolti:

- **`.htaccess`** (gia' presente nel repository): contiene sia le
  regole di rewrite (necessarie per far funzionare l'app) sia il
  blocco di Basic Auth, con un placeholder da sostituire:
  ```apache
  AuthType Basic
  AuthName "Area riservata - Corrispettivi Shopify"
  AuthUserFile /SOSTITUISCI/CON/PATH/ASSOLUTO/.htpasswd
  Require valid-user
  ```
- **`.htpasswd.example`**: template del file delle credenziali. **Non
  viene mai committato un `.htpasswd` reale** (conterrebbe l'hash della
  password): va creato manualmente sul server, a partire da questo
  esempio.

⚠️ **Attenzione all'ordine delle operazioni**: se `AuthUserFile` punta
a un file che non esiste, Apache risponde `500 Internal Server Error`
su *tutto* il sito. Segui questo ordine:

1. Genera l'hash della password (vedi sotto) e crea `.htpasswd` sul
   server con una riga `username:hash`.
2. Modifica `AuthUserFile` in `.htaccess` con il path **assoluto** del
   file appena creato (il path esatto si trova in Site Tools > Site >
   File Manager, aprendo le proprieta' del file; sulla document root di
   un sottodominio e' quasi sempre nella forma
   `/home/<utente_hosting>/www/<tuodominio>/public_html/<sottodominio>/.htpasswd`
   su SiteGround).
3. Solo a questo punto il sito sara' raggiungibile (con richiesta di
   credenziali).

### Generare l'hash della password

Due opzioni, a seconda che l'hosting offra o meno accesso SSH:

**Con accesso SSH** (SiteGround lo offre sui piani GrowBig e superiori,
attivabile in Site Tools > Devs > SSH Keys Manager):
```
htpasswd -c .htpasswd admin
```
(omettere `-c` se il file esiste gia' e si vuole aggiungere un altro
utente; il flag `-c` **crea/sovrascrive** il file, quindi va usato solo
la prima volta). Il comando `htpasswd` genera di default un hash in
formato APR1-MD5 (`$apr1$...`), che e' quello da usare (vedi sotto sul
perche').

**Senza accesso SSH** (via PHP, funziona su qualunque hosting): lo
script incluso genera una riga pronta da incollare in `.htpasswd`,
senza bisogno del comando `htpasswd`:
```
php scripts/cli/genera_htpasswd.php admin "LaTuaPasswordSicura"
```
L'output (`admin:$apr1$...`) va copiato interamente dentro `.htpasswd`
sul server.

**Perche' APR1-MD5 e non bcrypt**: Apache 2.4.4+ supporta in teoria
anche hash bcrypt (`$2y$...`) in `AuthUserFile`, ma solo se APR-util e'
stata compilata con supporto crypto - non garantito su ogni build di
Apache degli hosting condivisi. Su un'installazione reale e' stato
osservato che un hash bcrypt in `.htpasswd` causa un `500 Internal
Server Error` su tutto il sito non appena la Basic Auth viene attivata,
pur con path/permessi/contenuto tutti corretti. APR1-MD5 e' il formato
storico e universalmente supportato da qualsiasi Apache: lo script
incluso lo implementa in puro PHP (verificato byte per byte contro
l'output di `htpasswd` e `openssl passwd -apr1`) proprio per questo.

Non usare mai generatori di hash online per una password reale: la
password transiterebbe in chiaro verso un servizio terzo.

## Deploy su SiteGround (hosting condiviso)

### 0. Dove caricare i file: sottodominio, non sottocartella

L'app assume di stare **alla radice** del sito che la serve: link,
redirect e `.htaccess` usano tutti path assoluti tipo `/lavorazioni`,
`/brand` (senza un prefisso configurabile). Questo significa che:

- **va bene**: dominio principale (`public_html/`) oppure un
  **sottodominio dedicato** (es. `corrispettivi.tuodominio.it`), che ha
  una document root tutta sua;
- **non va bene cosi' com'e'**: una sottocartella di un dominio che
  ospita gia' un altro sito (es. `tuodominio.it/corrispettivi/`) - i
  link interni punterebbero comunque alla radice del dominio principale
  e romperebbero la navigazione. Per quel caso servirebbe prima
  aggiungere un base path configurabile al codice.

**Scelta consigliata: crea un sottodominio dedicato.**

1. **Site Tools > Site > Subdomains**: crea un sottodominio (es.
   `corrispettivi`). SiteGround genera una document root dedicata (il
   path esatto viene mostrato a schermo, tipicamente
   `public_html/corrispettivi/` sotto la cartella del dominio
   principale) - annotalo, serve al punto 5 per `AuthUserFile`.
2. **Site Tools > Security > SSL Manager**: installa il certificato
   Let's Encrypt gratuito sul sottodominio *prima* di esporre il sito.
   La Basic Auth manda le credenziali in chiaro se il sito non e' in
   HTTPS.
3. Tutti i file dell'applicazione (punto 3 sotto) vanno caricati
   **dentro la document root del sottodominio**, non in `public_html/`
   del dominio principale.

### 1. Creare il database dedicato

In Site Tools:

1. **Site Tools > Site > MySQL** (a volte sotto "Database Manager" a
   seconda del piano).
2. Sezione "Create New Database": crea un nuovo database (es.
   `corrispettivi`) - SiteGround anteporra' automaticamente un prefisso
   tipo `nomeaccount_corrispettivi`.
3. Sezione "Create New User": crea un utente MySQL dedicato con una
   password robusta.
4. Sezione "Manage Users": assegna all'utente **tutti i privilegi** sul
   database appena creato (non su altri database).
5. Annota host (di solito `localhost` su SiteGround), nome DB completo
   (col prefisso), utente e password: serviranno per `.env`.

Questo database e' e deve restare **esclusivo di questa applicazione**:
non riutilizzare un database gia' usato da altri progetti.

### 2. Importare lo schema

Da Site Tools > Site > MySQL > phpMyAdmin (accesso rapido al database
appena creato):

1. Apri phpMyAdmin sul database creato al punto 1.
2. Tab "Importa" (Import) > seleziona il file `schema.sql` di questo
   repository > Esegui.
3. Verifica che siano comparse le 4 tabelle `crp_brand`,
   `crp_lavorazioni`, `crp_ordini_raw`, `crp_log`.

In alternativa, se disponibile l'accesso SSH:
```
mysql -u <utente> -p <nome_database> < schema.sql
```

### 3. Caricare i file dell'applicazione

**Opzione automatica (consigliata)**: il repository include un GitHub
Actions workflow (`.github/workflows/build-deploy-package.yml`) che ad
ogni push su un branch `claude/**` genera automaticamente uno zip con
`vendor/` gia' installato via `composer install --no-dev`, scaricabile
dalla tab **Actions** del repository. Non salva ne' richiede nessuna
credenziale. Dettagli in `.github/DEPLOY-WITH-ACTIONS.md`. Poi vai
direttamente al punto 2 sotto con quello zip gia' pronto.

**Opzione manuale**:

1. In locale, esegui `composer install --no-dev --optimize-autoloader`
   per generare la cartella `vendor/` (non e' versionata su Git).
2. Carica l'intero contenuto del repository (incluso `vendor/`, esclusi
   `.git/`, `.env`, eventuale `.htpasswd`) nella document root del
   **sottodominio** creato al punto 0 (non in `public_html/` del
   dominio principale), tramite:
   - **File Manager** di Site Tools (upload di uno zip + estrazione), oppure
   - **SFTP** (credenziali in Site Tools > Site > SFTP Accounts) con un
     client come FileZilla, oppure
   - **Git** se il piano lo supporta (Site Tools > Devs > Git).
3. Assicurati che `storage/uploads/` sia scrivibile dal processo PHP
   (permessi tipici `755`/`775`; SiteGround gestisce di norma
   correttamente i permessi in upload via Site Tools/SFTP).

### 4. Configurare `.env`

1. Sul server, copia `.env.example` in `.env` (via File Manager o SFTP;
   `.env` non viene mai committato su Git).
2. Compila con le credenziali create al punto 1:
   ```
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=nomeaccount_corrispettivi
   DB_USER=nomeaccount_utentedb
   DB_PASS=la_password_scelta
   APP_DEBUG=false
   APP_TIMEZONE=Europe/Rome
   ```
   `APP_DEBUG` **deve** restare `false` in produzione (altrimenti gli
   errori PHP, potenzialmente con dettagli sensibili, verrebbero
   mostrati a video).

### 5. Configurare Basic Auth

Segui la sezione "Basic Auth" sopra: crea `.htpasswd` sul server e
aggiorna il path assoluto in `AuthUserFile` dentro `.htaccess`.

### 6. Verifica finale

1. Visita il sottodominio (es. `https://corrispettivi.tuodominio.it`):
   deve comparire il prompt di autenticazione del browser (Basic Auth)
   prima di qualsiasi altra cosa.
2. Dopo il login, la home reindirizza a `/lavorazioni` (elenco, vuoto
   al primo avvio).
3. Da **Gestione brand** (`/brand`), crea almeno un brand.
4. Da **Nuova lavorazione**, prova un caricamento CSV di test per
   confermare che upload, salvataggio, export Excel e cartella
   `storage/uploads/` funzionino correttamente sull'hosting.
5. Prova ad aprire direttamente nel browser un URL come
   `https://corrispettivi.tuodominio.it/.env` o
   `https://corrispettivi.tuodominio.it/schema.sql`: deve risultare
   `403 Forbidden` (bloccato da `.htaccess`).

## Note di sicurezza

- Nessuna query dell'applicazione fa mai riferimento a database/schemi
  diversi da quello dedicato indicato in `.env`.
- I CSV originali sono salvati in `storage/uploads/`, cartella esclusa
  dall'accesso diretto via URL da `.htaccess`: sono scaricabili solo
  tramite l'azione autenticata dell'app (`/lavorazioni/{id}/csv`).
- Le eliminazioni sono sempre soft-delete (`deleted_at`): non esiste,
  ne' e' previsto, un `DELETE` fisico da interfaccia.

## Sviluppi futuri (non implementati, ma non ostacolati dallo schema)

Lo schema e l'architettura sono stati pensati per non richiedere
refactoring pesanti quando in futuro si vorranno aggiungere:

- un sistema di utenze reali (username/password/email) con login,
  in sostituzione della sola Basic Auth;
- inserimento manuale di un singolo ordine "saltato" da Shopify
  (la tabella `crp_ordini_raw` e' gia' una struttura a riga singola,
  con una colonna `is_manuale` predisposta e non ancora usata da UI);
- trasformazione manuale di una riga corrispettivo esistente in un
  "reso" (la logica di rilevamento resi e' isolata in un unico punto,
  `App\Services\ResoRules::isReso()`, proprio per essere facile da
  estendere/correggere in futuro).
