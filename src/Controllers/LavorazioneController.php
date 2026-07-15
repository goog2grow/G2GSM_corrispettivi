<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Brand;
use App\Services\CsvParseResult;
use App\Services\CsvParser;
use App\Services\CsvValidationException;
use App\Services\MesiItaliani;

final class LavorazioneController
{
    private Brand $brandModel;

    public function __construct()
    {
        $this->brandModel = new Brand();
    }

    /**
     * Step 1 del wizard: form di caricamento.
     */
    public function nuovaStep1(): void
    {
        $errors = $_SESSION['upload_errors'] ?? [];
        $old = $_SESSION['upload_old'] ?? [];
        unset($_SESSION['upload_errors'], $_SESSION['upload_old']);

        View::renderWithLayout('lavorazioni/nuova_step1', [
            'title' => 'Nuova lavorazione',
            'brands' => $this->brandModel->allAttivi(),
            'mesi' => MesiItaliani::tutti(),
            'anni' => $this->anniDisponibili(),
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    /**
     * Step 2 del wizard: riceve il file, lo salva in una cartella
     * temporanea, lo valida/parsa e mostra un'anteprima. Nessun
     * salvataggio definitivo a DB in questo step (arriva con la logica
     * di naming/versioning).
     */
    public function anteprima(): void
    {
        $mese = (int) ($_POST['mese'] ?? 0);
        $anno = (int) ($_POST['anno'] ?? 0);
        $negozio = trim((string) ($_POST['negozio'] ?? 'Shopify')) ?: 'Shopify';
        $brandId = (int) ($_POST['brand_id'] ?? 0);
        $nota = trim((string) ($_POST['nota'] ?? ''));

        $errors = $this->validaFormBase($mese, $anno, $brandId);

        $fileErrore = $this->erroreUpload($_FILES['csv'] ?? null);
        if ($fileErrore !== null) {
            $errors[] = $fileErrore;
        }

        if (!empty($errors)) {
            $this->tornaAlloStep1ConErrori($errors, compact('mese', 'anno', 'negozio', 'brandId', 'nota'));
        }

        $tmpPath = $this->salvaFileTemporaneo($_FILES['csv']);
        if ($tmpPath === null) {
            $this->tornaAlloStep1ConErrori(
                ['Impossibile salvare temporaneamente il file caricato.'],
                compact('mese', 'anno', 'negozio', 'brandId', 'nota')
            );
        }

        $parser = new CsvParser();

        try {
            $result = $parser->parseFile($tmpPath);
        } catch (CsvValidationException $e) {
            @unlink($tmpPath);
            $this->tornaAlloStep1ConErrori([$e->getMessage()], compact('mese', 'anno', 'negozio', 'brandId', 'nota'));
            return;
        }

        $brand = $this->brandModel->find($brandId);

        $pending = [
            'tmp_path' => $tmpPath,
            'original_filename' => $_FILES['csv']['name'],
            'mese' => $mese,
            'anno' => $anno,
            'negozio' => $negozio,
            'brand_id' => $brandId,
            'brand_nome' => $brand['nome'] ?? '',
            'nota' => $nota,
        ];
        $_SESSION['pending_upload'] = $pending;

        View::renderWithLayout('lavorazioni/nuova_step2', [
            'title' => 'Verifica caricamento',
            'result' => $result,
            'pending' => $pending,
            'extraWarnings' => $this->calcolaAvvisiPeriodoEBrand($result, $mese, $anno, $brand['nome'] ?? ''),
        ]);
    }

    /**
     * Placeholder: la pagina elenco completa arriva allo step 6.
     */
    public function elenco(): void
    {
        View::renderWithLayout('lavorazioni/elenco_placeholder', [
            'title' => 'Elenco lavorazioni',
        ]);
    }

    private function validaFormBase(int $mese, int $anno, int $brandId): array
    {
        $errors = [];

        if ($mese < 1 || $mese > 12) {
            $errors[] = 'Seleziona un mese valido.';
        }
        if ($anno < 2000 || $anno > 2100) {
            $errors[] = 'Seleziona un anno valido.';
        }
        if ($brandId <= 0 || $this->brandModel->find($brandId) === null) {
            $errors[] = 'Seleziona un brand valido.';
        }

        return $errors;
    }

    private function erroreUpload(?array $file): ?string
    {
        if ($file === null || !isset($file['error'])) {
            return 'Devi selezionare un file CSV da caricare.';
        }

        return match ($file['error']) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Il file caricato supera la dimensione massima consentita.',
            UPLOAD_ERR_PARTIAL => 'Il caricamento del file e\' stato interrotto: riprova.',
            UPLOAD_ERR_NO_FILE => 'Devi selezionare un file CSV da caricare.',
            default => 'Errore durante il caricamento del file.',
        };
    }

    private function salvaFileTemporaneo(array $file): ?string
    {
        $tmpDir = dirname(__DIR__, 2) . '/storage/uploads/_tmp';
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            return null;
        }

        $tmpPath = $tmpDir . '/' . bin2hex(random_bytes(16)) . '.csv';

        if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $tmpPath)) {
            return null;
        }

        return $tmpPath;
    }

    /**
     * Confronta i dati letti dal CSV con quanto scelto nel form, per
     * segnalare (senza bloccare) possibili errori dell'utente: file
     * caricato nel periodo/brand sbagliato.
     */
    private function calcolaAvvisiPeriodoEBrand(CsvParseResult $result, int $mese, int $anno, string $brandSelezionato): array
    {
        $avvisi = [];

        $periodoAtteso = sprintf('%04d-%02d', $anno, $mese);
        $fuoriPeriodo = 0;
        foreach ($result->rows as $row) {
            if (substr((string) $row['paidDate'], 0, 7) !== $periodoAtteso) {
                $fuoriPeriodo++;
            }
        }
        if ($fuoriPeriodo > 0) {
            $avvisi[] = "{$fuoriPeriodo} riga/e hanno paidDate fuori dal periodo selezionato ({$periodoAtteso}).";
        }

        if (!empty($result->brandRilevati) && !in_array($brandSelezionato, $result->brandRilevati, true)) {
            $avvisi[] = 'Il brand indicato nel file (' . implode(', ', $result->brandRilevati)
                . ') non corrisponde al brand selezionato (' . $brandSelezionato . ').';
        }

        return $avvisi;
    }

    private function tornaAlloStep1ConErrori(array $errors, array $old): void
    {
        $_SESSION['upload_errors'] = $errors;
        $_SESSION['upload_old'] = $old;
        header('Location: /lavorazioni/nuova');
        exit;
    }

    private function anniDisponibili(): array
    {
        $annoCorrente = (int) date('Y');

        return range($annoCorrente + 1, $annoCorrente - 5);
    }
}
