# Deploy con GitHub Actions

Il workflow `.github/workflows/build-deploy-package.yml` crea automaticamente
un pacchetto pronto per il deploy, con tutte le dipendenze gia' installate.
Non salva ne' usa nessuna credenziale: produce solo uno zip scaricabile.

## Come funziona

Ad ogni push su un branch `claude/**` (o avviandolo manualmente), GitHub
Actions:

1. installa PHP e Composer;
2. esegue `composer install --no-dev --optimize-autoloader` (scarica
   PhpSpreadsheet, phpdotenv, senza le dipendenze di sviluppo);
3. crea uno zip con tutto il progetto, `vendor/` incluso;
4. rende lo zip scaricabile come artifact del workflow.

Non include mai `.env`, `.htpasswd`, `.git/`: quei file vanno sempre
creati/gestiti direttamente sul server.

## Come usarlo

### 1. Avvia il workflow

Si avvia da solo a ogni push su un branch `claude/**`. Per avviarlo a mano:

1. Vai su GitHub → **Actions**.
2. Seleziona **"Build Deploy Package"**.
3. Clicca **"Run workflow"**, scegli il branch, conferma.

### 2. Scarica il pacchetto

A build completata (circa 2-3 minuti):

1. Apri l'esecuzione del workflow.
2. In fondo alla pagina, sezione **Artifacts**.
3. Scarica **corrispettivi-deploy-package**.

### 3. Carica su SiteGround

1. Estrai lo zip sul tuo computer.
2. Carica tutto il contenuto (incluso `vendor/`) nella document root del
   sottodominio dedicato (vedi README, sezione "Deploy su SiteGround").
3. Crea `.env` e `.htpasswd` direttamente sul server: **non sono nello
   zip** (vedi README per come generarli/compilarli).
4. Verifica che `.htaccess` sia stato caricato (e' un file nascosto,
   alcuni client FTP/File Manager non lo mostrano di default).

### Aggiornamenti successivi

Ripeti: push su `claude/**` → nuovo pacchetto → download → upload,
avendo cura di **non sovrascrivere** `.env`/`.htpasswd` gia' presenti
sul server e di backuppare `storage/uploads/` prima di un aggiornamento
massivo (contiene i CSV originali gia' caricati).
