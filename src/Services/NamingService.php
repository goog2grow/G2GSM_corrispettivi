<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Genera il nome di una lavorazione secondo la convenzione del progetto:
 *
 *   "Corrispettivi Shopify {MeseItaliano} {Anno} {Brand}"
 *
 * La prima lavorazione di una combinazione mese+anno+brand (versione 1)
 * mantiene il nome "pulito", senza suffisso: il numero di versione resta
 * un dettaglio interno (colonna numero_versione a DB), non compare nel
 * nome. Dalla seconda lavorazione in poi (revisione) viene aggiunto il
 * suffisso "_rev_{datetime_upload}".
 */
final class NamingService
{
    public function generaNome(string $brandNome, int $mese, int $anno, int $numeroVersione, string $timestampUpload): string
    {
        $nomeBase = sprintf(
            'Corrispettivi Shopify %s %d %s',
            MesiItaliani::nome($mese),
            $anno,
            $brandNome
        );

        if ($numeroVersione <= 1) {
            return $nomeBase;
        }

        return $nomeBase . '_rev_' . $timestampUpload;
    }
}
