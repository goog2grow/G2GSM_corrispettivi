<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Converte le righe salvate in crp_ordini_raw (shape colonne DB) nella
 * shape usata da PivotCalculator (le stesse chiavi prodotte da
 * CsvParser), cosi' la pivot puo' essere ricalcolata "on the fly"
 * anche a partire dai dati gia' salvati a DB, non solo durante il
 * caricamento (usato dal ricalcolo in pagina di dettaglio e
 * dall'export Excel).
 */
final class OrdineRawMapper
{
    /** @param array<string, mixed> $row riga da crp_ordini_raw */
    public static function versoCsvShape(array $row): array
    {
        return [
            'id' => $row['id_ordine_originale'],
            'numero_corrispettivo' => $row['numero_corrispettivo_originale'] ?? '',
            'paidDate' => $row['paid_date'],
            'orderName' => $row['order_name'],
            'tax' => (float) $row['tax'],
            'totPaid' => (float) $row['tot_paid'],
            'totTax' => (float) $row['tot_tax'],
            'data_ordine' => $row['data_ordine'] ?? '',
            'data_creazione_corrispettivo' => $row['data_creazione_corrispettivo_originale'] ?? '',
            // il brand non e' conservato per singola riga (e' costante
            // per l'intera lavorazione, vedi crp_lavorazioni.brand_id)
            'brand' => '',
            'channel' => $row['channel'] ?? '',
            'paymentmethod' => $row['payment_method'] ?? '',
            'note' => $row['note_originale'] ?? '',
            'stato' => $row['stato_originale'] ?? '',
            'is_reso' => (bool) $row['is_reso'],
        ];
    }
}
