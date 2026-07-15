<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;

/**
 * Legge e valida i CSV di corrispettivi Shopify nel formato fisso a 14
 * colonne. Non fa nessuna operazione di calcolo/pivot: si limita a
 * produrre righe normalizzate pronte per essere aggregate o salvate.
 */
final class CsvParser
{
    private const EXPECTED_HEADERS = [
        'id', 'numero_corrispettivo', 'paidDate', 'orderName', 'tax', 'totPaid', 'totTax',
        'data_ordine', 'data_creazione_corrispettivo', 'brand', 'channel', 'paymentmethod', 'note', 'stato',
    ];

    private const MAX_ROW_ERRORS_REPORTED = 50;

    public function parseFile(string $path): CsvParseResult
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new CsvValidationException('Impossibile leggere il file caricato.');
        }

        $delimiter = $this->rilevaDelimitatore($path);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new CsvValidationException('Impossibile aprire il file caricato.');
        }

        try {
            $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
            if ($header === false || $header === null) {
                throw new CsvValidationException('Il file e\' vuoto o non e\' un CSV valido.');
            }

            // trim + rimozione BOM UTF-8 eventuale sulla prima colonna
            $header = array_map(
                static fn ($h) => trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF"),
                $header
            );

            $this->validaHeader($header);

            $rows = [];
            $rowErrors = [];
            $warnings = [];
            $aliquoteTrovate = [];
            $brandTrovati = [];
            $channelTrovati = [];
            $totaleResi = 0;
            $totaleIncassato = 0.0;
            $totaleIva = 0.0;
            $paidDateMin = null;
            $paidDateMax = null;

            $numeroRiga = 1; // la riga 1 e' l'header

            while (($fields = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $numeroRiga++;

                if ($this->isRigaVuota($fields)) {
                    continue;
                }

                if (count($fields) !== count(self::EXPECTED_HEADERS)) {
                    $this->aggiungiRowError($rowErrors, sprintf(
                        'Riga %d: numero di colonne errato (attese %d, trovate %d) - riga scartata.',
                        $numeroRiga,
                        count(self::EXPECTED_HEADERS),
                        count($fields)
                    ));
                    continue;
                }

                $row = array_combine(self::EXPECTED_HEADERS, $fields);
                $row = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $row);

                $errore = $this->validaRiga($row, $numeroRiga);
                if ($errore !== null) {
                    $this->aggiungiRowError($rowErrors, $errore);
                    continue;
                }

                $row['tax'] = (float) $row['tax'];
                $row['totPaid'] = (float) $row['totPaid'];
                $row['totTax'] = (float) $row['totTax'];
                $row['id'] = ($row['id'] !== '' && is_numeric($row['id'])) ? (int) $row['id'] : null;
                $row['is_reso'] = ResoRules::isReso($row);

                $rows[] = $row;

                // Chiave stringa: un float come chiave di array verrebbe
                // troncato a intero da PHP (0.22 e 0.10 collasserebbero su 0).
                $aliquoteTrovate[(string) $row['tax']] = $row['tax'];
                if ($row['brand'] !== '') {
                    $brandTrovati[$row['brand']] = true;
                }
                if ($row['channel'] !== '') {
                    $channelTrovati[$row['channel']] = true;
                }
                if ($row['is_reso']) {
                    $totaleResi++;
                }
                $totaleIncassato += $row['totPaid'];
                $totaleIva += $row['totTax'];

                if ($paidDateMin === null || $row['paidDate'] < $paidDateMin) {
                    $paidDateMin = $row['paidDate'];
                }
                if ($paidDateMax === null || $row['paidDate'] > $paidDateMax) {
                    $paidDateMax = $row['paidDate'];
                }
            }
        } finally {
            fclose($handle);
        }

        if (empty($rows)) {
            throw new CsvValidationException('Il file non contiene righe valide da importare.');
        }

        if (count($rowErrors) >= self::MAX_ROW_ERRORS_REPORTED) {
            $warnings[] = sprintf(
                'Sono stati riportati solo i primi %d errori di riga (potrebbero essercene altri).',
                self::MAX_ROW_ERRORS_REPORTED
            );
        }

        $aliquote = array_values($aliquoteTrovate);
        sort($aliquote);

        return new CsvParseResult(
            rows: $rows,
            rowErrors: $rowErrors,
            warnings: $warnings,
            aliquote: $aliquote,
            brandRilevati: array_keys($brandTrovati),
            channelRilevati: array_keys($channelTrovati),
            totaleRighe: count($rows),
            totaleResi: $totaleResi,
            totaleIncassato: round($totaleIncassato, 2),
            totaleIva: round($totaleIva, 2),
            paidDateMin: $paidDateMin,
            paidDateMax: $paidDateMax,
        );
    }

    /**
     * Il formato non e' hardcodato su ",": se il file usa ";" (comune in
     * export in stile italiano) viene rilevato automaticamente guardando
     * la prima riga.
     */
    private function rilevaDelimitatore(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }
        $firstLine = fgets($handle) ?: '';
        fclose($handle);

        $countComma = substr_count($firstLine, ',');
        $countSemicolon = substr_count($firstLine, ';');

        return $countSemicolon > $countComma ? ';' : ',';
    }

    private function isRigaVuota(array $fields): bool
    {
        if (count($fields) === 1 && ($fields[0] === null || $fields[0] === '')) {
            return true;
        }

        return false;
    }

    private function aggiungiRowError(array &$rowErrors, string $messaggio): void
    {
        if (count($rowErrors) < self::MAX_ROW_ERRORS_REPORTED) {
            $rowErrors[] = $messaggio;
        }
    }

    private function validaHeader(array $header): void
    {
        if ($header !== self::EXPECTED_HEADERS) {
            throw new CsvValidationException(sprintf(
                "Formato del file non valido.\nColonne attese (in quest'ordine): %s\nColonne trovate: %s",
                implode(', ', self::EXPECTED_HEADERS),
                implode(', ', $header)
            ));
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return string|null messaggio di errore, oppure null se la riga e' valida
     */
    private function validaRiga(array $row, int $numeroRiga): ?string
    {
        if ($row['paidDate'] === '' || !$this->isDataValida((string) $row['paidDate'])) {
            return "Riga {$numeroRiga}: paidDate \"{$row['paidDate']}\" non e' una data valida (formato atteso AAAA-MM-GG) - riga scartata.";
        }

        foreach (['tax', 'totPaid', 'totTax'] as $campo) {
            if (!is_numeric($row[$campo])) {
                return "Riga {$numeroRiga}: il campo \"{$campo}\" (\"{$row[$campo]}\") non e' numerico - riga scartata.";
            }
        }

        if ($row['orderName'] === '') {
            return "Riga {$numeroRiga}: orderName mancante - riga scartata.";
        }

        return null;
    }

    private function isDataValida(string $value): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
