<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\Lavorazione;
use App\Models\Log;
use App\Models\OrdineRaw;
use Throwable;

/**
 * Orchestrazione del salvataggio definitivo di una lavorazione: e' il
 * punto piu' delicato dell'applicazione, perche' decide se il
 * caricamento e' una prima versione (v1, nome pulito) o una revisione
 * di un periodo brand+mese+anno gia' esistente (nome con suffisso
 * "_rev_{datetime}", versione precedente marcata non attiva).
 *
 * Regole applicate (mai derogabili):
 *  - nessun UPDATE distruttivo sui dati di una lavorazione precedente:
 *    la precedente attiva viene solo marcata is_attiva = 0, i suoi dati
 *    (nome, file, righe grezze, totali) restano invariati e mai cancellati;
 *  - ogni caricamento crea sempre nuove righe (nuova lavorazione + nuovo
 *    set di righe grezze collegate), mai un riuso di righe esistenti;
 *  - il numero di versione conta TUTTI i caricamenti gia' avvenuti per
 *    quella combinazione brand+mese+anno (anche quelli soft-deleted),
 *    cosi' la numerazione riflette sempre la storia reale;
 *  - il file fisico viene sempre archiviato con un nome univoco
 *    (timestamp di upload incluso), anche per la prima versione.
 */
final class SalvataggioLavorazioneService
{
    private Lavorazione $lavorazioneModel;
    private OrdineRaw $ordineRawModel;
    private Log $logModel;
    private NamingService $namingService;
    private FileStorage $fileStorage;

    public function __construct()
    {
        $this->lavorazioneModel = new Lavorazione();
        $this->ordineRawModel = new OrdineRaw();
        $this->logModel = new Log();
        $this->namingService = new NamingService();
        $this->fileStorage = new FileStorage();
    }

    /**
     * @param array{
     *     tmp_path: string,
     *     mese: int,
     *     anno: int,
     *     negozio: string,
     *     brand_id: int,
     *     brand_nome: string,
     *     nota: string
     * } $pending
     */
    public function salva(array $pending, CsvParseResult $result): SalvataggioEsito
    {
        $brandId = $pending['brand_id'];
        $mese = $pending['mese'];
        $anno = $pending['anno'];

        // 1) Determina la versione: conta TUTTI i caricamenti storici
        //    (attivi, storici o soft-deleted) per questa combinazione.
        $numeroVersioniEsistenti = $this->lavorazioneModel->contaVersioniEsistenti($brandId, $mese, $anno);
        $numeroVersione = $numeroVersioniEsistenti + 1;
        $timestampUpload = date('YmdHis');

        // 2) Nome: pulito se e' la prima versione, con suffisso _rev_ altrimenti.
        $nomeLavorazione = $this->namingService->generaNome(
            $pending['brand_nome'],
            $mese,
            $anno,
            $numeroVersione,
            $timestampUpload
        );

        // 3) La versione attiva corrente (se esiste) verra' disattivata,
        //    MAI cancellata: resta a DB come storica.
        $precedenteAttiva = $this->lavorazioneModel->trovaAttiva($brandId, $mese, $anno);

        // 4) Il file va archiviato PRIMA della transazione DB: se fallisce
        //    (permessi, disco pieno) non deve esserci nessuna scrittura a DB.
        $filePathRelativo = $this->fileStorage->archivia(
            $pending['tmp_path'],
            $pending['brand_nome'],
            $anno,
            $mese,
            $nomeLavorazione,
            $timestampUpload
        );

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            if ($precedenteAttiva !== null) {
                $this->lavorazioneModel->disattiva((int) $precedenteAttiva['id']);
            }

            $lavorazioneId = $this->lavorazioneModel->crea([
                'brand_id' => $brandId,
                'mese' => $mese,
                'anno' => $anno,
                'negozio' => $pending['negozio'],
                'nome_lavorazione' => $nomeLavorazione,
                'numero_versione' => $numeroVersione,
                'is_attiva' => 1,
                'nota' => $pending['nota'] !== '' ? $pending['nota'] : null,
                'file_originale_path' => $filePathRelativo,
                'totale_incassato' => $result->totaleIncassato,
                'totale_iva' => $result->totaleIva,
                'numero_ordini' => $result->totaleRighe,
                'numero_resi' => $result->totaleResi,
            ]);

            // Nuovo set di righe grezze SEMPRE: nessun riuso/aggiornamento
            // di righe di lavorazioni precedenti.
            $this->ordineRawModel->inserisciMassivo($lavorazioneId, $result->rows);

            $tipoOperazione = $numeroVersione <= 1 ? 'creazione' : 'revisione';
            $descrizione = $numeroVersione <= 1
                ? sprintf('Creata lavorazione "%s"', $nomeLavorazione)
                : sprintf(
                    'Creata revisione v%d "%s"%s',
                    $numeroVersione,
                    $nomeLavorazione,
                    $precedenteAttiva !== null
                        ? sprintf(' (sostituisce come attiva "%s")', $precedenteAttiva['nome_lavorazione'])
                        : ''
                );

            $this->logModel->registra($lavorazioneId, $tipoOperazione, $descrizione, $result->totaleRighe, $result->totaleResi);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return new SalvataggioEsito(
            lavorazioneId: $lavorazioneId,
            nomeLavorazione: $nomeLavorazione,
            numeroVersione: $numeroVersione,
            precedenteAttiva: $precedenteAttiva,
        );
    }
}
