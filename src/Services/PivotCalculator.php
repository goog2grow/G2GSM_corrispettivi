<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Calcola la pivot giorno x aliquota a partire dai dati grezzi di una
 * lavorazione. Il calcolo e' sempre fatto "on the fly" sulle righe
 * passate in ingresso: nessun risultato viene mai pre-aggregato/salvato
 * a DB, cosi' che cambiare questa logica in futuro basti a rigenerare
 * la vista (nessuna migrazione di dati necessaria).
 *
 * @see App\Services\ResoRules per la definizione di "reso"
 */
final class PivotCalculator
{
    /**
     * @param array<int, array<string, mixed>> $rows righe grezze normalizzate
     *        (chiavi minime richieste: paidDate, tax, totPaid, totTax, is_reso)
     */
    public function calcola(array $rows, int $mese, int $anno): PivotResult
    {
        if ($mese < 1 || $mese > 12) {
            throw new InvalidArgumentException("Mese non valido: {$mese}");
        }

        $ultimoGiorno = (int) date('t', mktime(0, 0, 0, $mese, 1, $anno));
        $periodoAtteso = sprintf('%04d-%02d', $anno, $mese);

        // Le colonne aliquota della pivot sono derivate SOLO dalle righe
        // che ricadono effettivamente nel periodo selezionato: righe fuori
        // periodo non devono generare colonne che poi resterebbero vuote.
        $aliquoteTrovate = [];
        foreach ($rows as $row) {
            if (substr((string) $row['paidDate'], 0, 7) === $periodoAtteso) {
                $aliquoteTrovate[(string) $row['tax']] = (float) $row['tax'];
            }
        }
        $aliquote = array_values($aliquoteTrovate);
        sort($aliquote);

        $giorni = [];
        for ($giorno = 1; $giorno <= $ultimoGiorno; $giorno++) {
            $celle = [];
            foreach ($aliquote as $aliquota) {
                $celle[(string) $aliquota] = ['incassato' => 0.0, 'iva' => 0.0];
            }
            $giorni[$giorno] = $celle;
        }

        $righeFuoriPeriodo = 0;

        foreach ($rows as $row) {
            $paidDate = (string) $row['paidDate'];

            if (substr($paidDate, 0, 7) !== $periodoAtteso) {
                $righeFuoriPeriodo++;
                continue;
            }

            $giorno = (int) substr($paidDate, 8, 2);
            $aliquotaKey = (string) (float) $row['tax'];

            if (!isset($giorni[$giorno][$aliquotaKey])) {
                // difensivo: non dovrebbe accadere, le aliquote sono derivate
                // dalle stesse righe gia' filtrate per periodo
                $giorni[$giorno][$aliquotaKey] = ['incassato' => 0.0, 'iva' => 0.0];
            }

            $giorni[$giorno][$aliquotaKey]['incassato'] += (float) $row['totPaid'];
            $giorni[$giorno][$aliquotaKey]['iva'] += (float) $row['totTax'];
        }

        // Totali di riga (per giorno) e totale generale della pivot,
        // calcolati sui valori non ancora arrotondati per evitare di
        // sommare arrotondamenti parziali.
        $totaliPerGiorno = [];
        $totaleGeneraleIncassato = 0.0;
        $totaleGeneraleIva = 0.0;
        foreach ($giorni as $giorno => $celle) {
            $incassatoGiorno = 0.0;
            $ivaGiorno = 0.0;
            foreach ($celle as $cella) {
                $incassatoGiorno += $cella['incassato'];
                $ivaGiorno += $cella['iva'];
            }
            $totaliPerGiorno[$giorno] = [
                'incassato' => round($incassatoGiorno, 2),
                'iva' => round($ivaGiorno, 2),
            ];
            $totaleGeneraleIncassato += $incassatoGiorno;
            $totaleGeneraleIva += $ivaGiorno;
        }

        // Totali di colonna (per aliquota), sommando su tutti i giorni.
        $totaliPerAliquota = [];
        foreach ($aliquote as $aliquota) {
            $key = (string) $aliquota;
            $incassatoAliquota = 0.0;
            $ivaAliquota = 0.0;
            foreach ($giorni as $celle) {
                $incassatoAliquota += $celle[$key]['incassato'];
                $ivaAliquota += $celle[$key]['iva'];
            }
            $totaliPerAliquota[$key] = [
                'incassato' => round($incassatoAliquota, 2),
                'iva' => round($ivaAliquota, 2),
            ];
        }

        // Arrotonda le singole celle SOLO ora, per la visualizzazione
        // (i totali sopra sono gia' stati calcolati sui valori esatti).
        foreach ($giorni as &$celle) {
            foreach ($celle as &$cella) {
                $cella['incassato'] = round($cella['incassato'], 2);
                $cella['iva'] = round($cella['iva'], 2);
            }
        }
        unset($celle, $cella);

        // Totali "senza split": TUTTE le righe passate in ingresso,
        // indipendentemente da giorno e aliquota (anche quelle fuori dal
        // periodo selezionato). Servono come riepilogo sintetico e come
        // controllo di coerenza rispetto ai totali della pivot.
        $totaleSenzaSplitIncassato = 0.0;
        $totaleSenzaSplitIva = 0.0;
        $numeroOrdini = 0;
        $numeroResi = 0;
        foreach ($rows as $row) {
            $totaleSenzaSplitIncassato += (float) $row['totPaid'];
            $totaleSenzaSplitIva += (float) $row['totTax'];
            $numeroOrdini++;
            if (!empty($row['is_reso'])) {
                $numeroResi++;
            }
        }

        return new PivotResult(
            mese: $mese,
            anno: $anno,
            ultimoGiorno: $ultimoGiorno,
            aliquote: $aliquote,
            giorni: $giorni,
            totaliPerGiorno: $totaliPerGiorno,
            totaliPerAliquota: $totaliPerAliquota,
            totaleGeneraleIncassato: round($totaleGeneraleIncassato, 2),
            totaleGeneraleIva: round($totaleGeneraleIva, 2),
            totaleSenzaSplitIncassato: round($totaleSenzaSplitIncassato, 2),
            totaleSenzaSplitIva: round($totaleSenzaSplitIva, 2),
            righeFuoriPeriodo: $righeFuoriPeriodo,
            numeroOrdini: $numeroOrdini,
            numeroResi: $numeroResi,
        );
    }
}
