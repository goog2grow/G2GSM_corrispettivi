-- =====================================================================
-- Gestione Corrispettivi Shopify - schema iniziale
-- =====================================================================
-- Da eseguire UNA SOLA VOLTA su un database MySQL vuoto e dedicato a
-- questo progetto (nessuna tabella di altri progetti deve mai esistere
-- in questo schema). Tutte le tabelle usano il prefisso "crp_".
--
-- Eventuali modifiche future allo schema vanno fatte con script di
-- migrazione incrementali in /migrations (es. 001_descrizione.sql),
-- MAI riscrivendo o rieseguendo questo file su un database che
-- contiene gia' dati.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
-- crp_brand: anagrafica brand e-commerce, gestita da interfaccia (CRUD)
-- ---------------------------------------------------------------------
CREATE TABLE crp_brand (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_brand_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- crp_lavorazioni: una lavorazione = un caricamento CSV per una
-- combinazione mese/anno/brand. Versionata: mai sovrascritta.
-- ---------------------------------------------------------------------
CREATE TABLE crp_lavorazioni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    mese TINYINT UNSIGNED NOT NULL,            -- 1-12
    anno SMALLINT UNSIGNED NOT NULL,
    negozio VARCHAR(50) NOT NULL DEFAULT 'Shopify',

    nome_lavorazione VARCHAR(255) NOT NULL,    -- nome completo, incluso eventuale suffisso _rev_
    numero_versione INT UNSIGNED NOT NULL DEFAULT 1,
    is_attiva TINYINT(1) NOT NULL DEFAULT 1,   -- 1 solo per l'ultima versione caricata del periodo

    nota TEXT NULL,
    file_originale_path VARCHAR(500) NOT NULL, -- path relativo del CSV originale salvato su disco

    totale_incassato DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    totale_iva DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    numero_ordini INT UNSIGNED NOT NULL DEFAULT 0,
    numero_resi INT UNSIGNED NOT NULL DEFAULT 0,

    deleted_at DATETIME NULL,                  -- soft delete, MAI DELETE fisico
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_lavorazioni_brand FOREIGN KEY (brand_id) REFERENCES crp_brand(id),

    KEY idx_lavorazioni_periodo (brand_id, anno, mese),
    KEY idx_lavorazioni_attiva (is_attiva),
    KEY idx_lavorazioni_deleted (deleted_at),
    KEY idx_lavorazioni_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- crp_ordini_raw: righe grezze del CSV, collegate a una lavorazione.
-- Struttura volutamente "piatta" a riga singola: in futuro permette di
-- aggiungere facilmente un form di inserimento manuale sulla stessa
-- tabella, senza refactoring.
-- ---------------------------------------------------------------------
CREATE TABLE crp_ordini_raw (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lavorazione_id INT UNSIGNED NOT NULL,

    id_ordine_originale INT NULL,
    numero_corrispettivo_originale VARCHAR(100) NULL,

    paid_date DATE NOT NULL,
    order_name VARCHAR(100) NOT NULL,
    tax DECIMAL(5,4) NOT NULL,                 -- es. 0.1000, 0.2200
    tot_paid DECIMAL(12,2) NOT NULL,
    tot_tax DECIMAL(12,2) NOT NULL,

    data_ordine DATE NULL,
    data_creazione_corrispettivo_originale DATETIME NULL,

    channel VARCHAR(50) NULL,
    payment_method VARCHAR(100) NULL,
    note_originale VARCHAR(255) NULL,
    stato_originale VARCHAR(50) NULL,

    is_reso TINYINT(1) NOT NULL DEFAULT 0,

    -- predisposizione per lo sviluppo futuro di inserimento manuale
    -- (non usata/non gestita da UI in questa versione)
    is_manuale TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ordini_raw_lavorazione FOREIGN KEY (lavorazione_id) REFERENCES crp_lavorazioni(id),

    KEY idx_ordini_raw_lavorazione (lavorazione_id),
    KEY idx_ordini_raw_lav_paid_date (lavorazione_id, paid_date),
    KEY idx_ordini_raw_tax (tax)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- crp_log: log leggibile di creazione/revisione/eliminazione lavorazioni
-- ---------------------------------------------------------------------
CREATE TABLE crp_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lavorazione_id INT UNSIGNED NULL,
    tipo_operazione VARCHAR(50) NOT NULL,      -- 'creazione', 'revisione', 'eliminazione', 'ricalcolo'
    descrizione VARCHAR(500) NOT NULL,
    numero_ordini INT UNSIGNED NULL,
    numero_resi INT UNSIGNED NULL,
    data_operazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_lavorazione FOREIGN KEY (lavorazione_id) REFERENCES crp_lavorazioni(id),

    KEY idx_log_lavorazione (lavorazione_id),
    KEY idx_log_data (data_operazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
