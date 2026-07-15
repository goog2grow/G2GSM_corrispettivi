<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Genera il file Excel (.xlsx) di una lavorazione: foglio "Pivot" con
 * la stessa tabella giorno x aliquota mostrata a video, e foglio
 * "Dati Grezzi" con le righe originali del CSV.
 */
final class ExcelExporter
{
    private const NUMBER_FORMAT = '#,##0.00';

    /**
     * @param array<string, mixed> $lavorazione riga di crp_lavorazioni
     * @param array<int, array<string, mixed>> $righeRawDb righe di crp_ordini_raw (shape colonne DB)
     */
    public function genera(array $lavorazione, string $brandNome, PivotResult $pivot, array $righeRawDb): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->scriviFoglioPivot($spreadsheet->getActiveSheet(), $lavorazione, $brandNome, $pivot);

        $foglioRaw = $spreadsheet->createSheet();
        $this->scriviFoglioDatiGrezzi($foglioRaw, $righeRawDb, $brandNome);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function scriviFoglioPivot(Worksheet $sheet, array $lavorazione, string $brandNome, PivotResult $pivot): void
    {
        $sheet->setTitle('Pivot');

        $riga = 1;
        $sheet->setCellValue("A{$riga}", $lavorazione['nome_lavorazione']);
        $sheet->getStyle("A{$riga}")->getFont()->setBold(true)->setSize(14);
        $riga++;

        $sheet->setCellValue("A{$riga}", 'Brand:');
        $sheet->setCellValue("B{$riga}", $brandNome);
        $riga++;

        $sheet->setCellValue("A{$riga}", 'Periodo:');
        $sheet->setCellValue("B{$riga}", MesiItaliani::nome($pivot->mese) . ' ' . $pivot->anno);
        $riga++;

        $sheet->setCellValue("A{$riga}", 'Versione:');
        $sheet->setCellValue("B{$riga}", 'v' . $lavorazione['numero_versione'] . ((int) $lavorazione['is_attiva'] === 1 ? ' (attiva)' : ' (storica)'));
        $riga++;

        if (!empty($lavorazione['nota'])) {
            $sheet->setCellValue("A{$riga}", 'Nota:');
            $sheet->setCellValue("B{$riga}", $lavorazione['nota']);
            $riga++;
        }
        $riga++;

        // Riepilogo sintetico "senza split" in un'area dedicata del foglio,
        // come richiesto: somma di tutti i totPaid/totTax del file,
        // indipendentemente da giorno e aliquota.
        $sheet->setCellValue("A{$riga}", 'Riepilogo sintetico (senza split)');
        $sheet->getStyle("A{$riga}")->getFont()->setBold(true)->setItalic(true);
        $riga++;

        $sheet->setCellValue("A{$riga}", 'Totale incassato:');
        $sheet->setCellValue("B{$riga}", $pivot->totaleSenzaSplitIncassato);
        $sheet->getStyle("B{$riga}")->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        $riga++;

        $sheet->setCellValue("A{$riga}", 'Totale IVA:');
        $sheet->setCellValue("B{$riga}", $pivot->totaleSenzaSplitIva);
        $sheet->getStyle("B{$riga}")->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        $riga += 2;

        $rigaHeaderAliquote = $riga;
        $rigaHeaderColonne = $riga + 1;

        $col = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rigaHeaderAliquote, 'Giorno');
        $sheet->mergeCells(
            Coordinate::stringFromColumnIndex($col) . $rigaHeaderAliquote
            . ':' . Coordinate::stringFromColumnIndex($col) . $rigaHeaderColonne
        );
        $col++;

        foreach ($pivot->aliquote as $aliquota) {
            $label = number_format($aliquota * 100, 2) . '%';
            $colLettera = Coordinate::stringFromColumnIndex($col);
            $colLetteraSucc = Coordinate::stringFromColumnIndex($col + 1);

            $sheet->setCellValue($colLettera . $rigaHeaderAliquote, $label);
            $sheet->mergeCells($colLettera . $rigaHeaderAliquote . ':' . $colLetteraSucc . $rigaHeaderAliquote);
            $sheet->setCellValue($colLettera . $rigaHeaderColonne, 'Incassato');
            $sheet->setCellValue($colLetteraSucc . $rigaHeaderColonne, 'IVA');
            $col += 2;
        }

        $colLettera = Coordinate::stringFromColumnIndex($col);
        $colLetteraSucc = Coordinate::stringFromColumnIndex($col + 1);
        $sheet->setCellValue($colLettera . $rigaHeaderAliquote, 'Totale giorno');
        $sheet->mergeCells($colLettera . $rigaHeaderAliquote . ':' . $colLetteraSucc . $rigaHeaderAliquote);
        $sheet->setCellValue($colLettera . $rigaHeaderColonne, 'Incassato');
        $sheet->setCellValue($colLetteraSucc . $rigaHeaderColonne, 'IVA');
        $ultimaColonna = $col + 1;
        $ultimaColonnaLettera = Coordinate::stringFromColumnIndex($ultimaColonna);

        $sheet->getStyle("A{$rigaHeaderAliquote}:{$ultimaColonnaLettera}{$rigaHeaderColonne}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rigaHeaderAliquote}:{$ultimaColonnaLettera}{$rigaHeaderColonne}")->getAlignment()->setHorizontal('center');

        $rigaDati = $rigaHeaderColonne + 1;
        foreach ($pivot->giorni as $giorno => $celle) {
            $col = 1;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rigaDati, $giorno);
            $col++;

            foreach ($pivot->aliquote as $aliquota) {
                $cella = $celle[(string) $aliquota];
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rigaDati, $cella['incassato']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $rigaDati, $cella['iva']);
                $col += 2;
            }

            $totRiga = $pivot->totaliPerGiorno[$giorno];
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rigaDati, $totRiga['incassato']);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $rigaDati, $totRiga['iva']);

            $rigaDati++;
        }

        $rigaTotaleMese = $rigaDati;
        $col = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rigaTotaleMese, 'TOTALE MESE');
        $col++;

        foreach ($pivot->aliquote as $aliquota) {
            $tot = $pivot->totaliPerAliquota[(string) $aliquota];
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rigaTotaleMese, $tot['incassato']);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $rigaTotaleMese, $tot['iva']);
            $col += 2;
        }

        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rigaTotaleMese, $pivot->totaleGeneraleIncassato);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $rigaTotaleMese, $pivot->totaleGeneraleIva);

        $sheet->getStyle("A{$rigaTotaleMese}:{$ultimaColonnaLettera}{$rigaTotaleMese}")->getFont()->setBold(true);

        $primaRigaDati = $rigaHeaderColonne + 1;
        $sheet->getStyle("B{$primaRigaDati}:{$ultimaColonnaLettera}{$rigaTotaleMese}")->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);

        foreach (range(1, $ultimaColonna) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }
    }

    /** @param array<int, array<string, mixed>> $righeRawDb */
    private function scriviFoglioDatiGrezzi(Worksheet $sheet, array $righeRawDb, string $brandNome): void
    {
        $sheet->setTitle('Dati Grezzi');

        $headers = [
            'id', 'numero_corrispettivo', 'paidDate', 'orderName', 'tax', 'totPaid', 'totTax',
            'data_ordine', 'data_creazione_corrispettivo', 'brand', 'channel', 'paymentmethod', 'note', 'stato',
        ];

        foreach ($headers as $i => $header) {
            $colLettera = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$colLettera}1", $header);
        }
        $ultimaColonna = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$ultimaColonna}1")->getFont()->setBold(true);

        $riga = 2;
        foreach ($righeRawDb as $row) {
            $sheet->setCellValue("A{$riga}", $row['id_ordine_originale']);
            $sheet->setCellValue("B{$riga}", $row['numero_corrispettivo_originale']);
            $sheet->setCellValue("C{$riga}", $row['paid_date']);
            $sheet->setCellValue("D{$riga}", $row['order_name']);
            $sheet->setCellValueExplicit("E{$riga}", (float) $row['tax'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("F{$riga}", (float) $row['tot_paid'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("G{$riga}", (float) $row['tot_tax'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValue("H{$riga}", $row['data_ordine']);
            $sheet->setCellValue("I{$riga}", $row['data_creazione_corrispettivo_originale']);
            $sheet->setCellValue("J{$riga}", $brandNome);
            $sheet->setCellValue("K{$riga}", $row['channel']);
            $sheet->setCellValue("L{$riga}", $row['payment_method']);
            $sheet->setCellValue("M{$riga}", $row['note_originale']);
            $sheet->setCellValue("N{$riga}", $row['stato_originale']);
            $riga++;
        }

        $sheet->getStyle("E2:G{$riga}")->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);

        foreach (range(1, count($headers)) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }
    }
}
