<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Brand;
use App\Models\Lavorazione;
use App\Models\OrdineRaw;
use App\Services\ExcelExporter;
use App\Services\OrdineRawMapper;
use App\Services\PivotCalculator;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExportController
{
    private Lavorazione $lavorazioneModel;
    private OrdineRaw $ordineRawModel;
    private Brand $brandModel;

    public function __construct()
    {
        $this->lavorazioneModel = new Lavorazione();
        $this->ordineRawModel = new OrdineRaw();
        $this->brandModel = new Brand();
    }

    public function excel(int $id): void
    {
        $lavorazione = $this->lavorazioneModel->find($id);
        if ($lavorazione === null) {
            http_response_code(404);
            echo 'Lavorazione non trovata.';
            return;
        }

        $brand = $this->brandModel->find((int) $lavorazione['brand_id']);
        $brandNome = $brand['nome'] ?? '';

        $righeRawDb = $this->ordineRawModel->trovaPerLavorazione($id);
        $righeCsvShape = array_map(
            static fn (array $row) => OrdineRawMapper::versoCsvShape($row),
            $righeRawDb
        );

        // Pivot ricalcolata on-the-fly dai dati grezzi salvati, non da
        // un valore pre-aggregato: coerente con la logica di step 3.
        $pivot = (new PivotCalculator())->calcola($righeCsvShape, (int) $lavorazione['mese'], (int) $lavorazione['anno']);

        $spreadsheet = (new ExcelExporter())->genera($lavorazione, $brandNome, $pivot, $righeRawDb);

        $nomeFile = $this->sanitizzaNomeFile((string) $lavorazione['nome_lavorazione']) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nomeFile . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Serve il CSV originale cosi' come caricato, per tracciabilita'.
     * Il path viene sempre risolto da DB (mai da input utente): nessun
     * rischio di path traversal.
     */
    public function csvOriginale(int $id): void
    {
        $lavorazione = $this->lavorazioneModel->find($id);
        if ($lavorazione === null) {
            http_response_code(404);
            echo 'Lavorazione non trovata.';
            return;
        }

        $percorsoAssoluto = dirname(__DIR__, 2) . '/' . $lavorazione['file_originale_path'];
        if (!is_file($percorsoAssoluto)) {
            http_response_code(404);
            echo 'File CSV originale non trovato su disco.';
            return;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($percorsoAssoluto) . '"');
        header('Content-Length: ' . (string) filesize($percorsoAssoluto));
        readfile($percorsoAssoluto);
    }

    private function sanitizzaNomeFile(string $nome): string
    {
        $nome = preg_replace('/[^A-Za-z0-9]+/', '_', $nome) ?? $nome;

        return trim($nome, '_');
    }
}
