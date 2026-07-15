<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Archiviazione dei CSV originali su disco, secondo la struttura:
 *
 *   storage/uploads/{brand}/{anno}/{mese_02d}/{nome_lavorazione_sanitizzato}_{timestamp_upload}.csv
 *
 * Ogni file caricato genera SEMPRE un nome fisico univoco (il timestamp
 * di upload e' sempre incluso nel nome fisico, anche per la prima
 * versione di un periodo): nessun file viene mai sovrascritto.
 */
final class FileStorage
{
    private const BASE_DIR = __DIR__ . '/../../storage/uploads';

    /**
     * Sposta il file temporaneo nella destinazione definitiva e
     * restituisce il path relativo (rispetto alla root del progetto)
     * da salvare a DB.
     */
    public function archivia(
        string $tmpPath,
        string $brandNome,
        int $anno,
        int $mese,
        string $nomeLavorazione,
        string $timestampUpload
    ): string {
        $sottocartella = sprintf('%s/%04d/%02d', self::slug($brandNome), $anno, $mese);
        $dirAssoluta = self::BASE_DIR . '/' . $sottocartella;

        if (!is_dir($dirAssoluta) && !mkdir($dirAssoluta, 0775, true) && !is_dir($dirAssoluta)) {
            throw new RuntimeException("Impossibile creare la cartella di archiviazione: {$dirAssoluta}");
        }

        $nomeFile = self::slug($nomeLavorazione) . '_' . $timestampUpload . '.csv';
        $destAssoluta = $dirAssoluta . '/' . $nomeFile;

        if (!rename($tmpPath, $destAssoluta)) {
            throw new RuntimeException('Impossibile spostare il file caricato nella destinazione definitiva.');
        }

        return 'storage/uploads/' . $sottocartella . '/' . $nomeFile;
    }

    public static function slug(string $value): string
    {
        $value = strtolower($value);
        $translitterato = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($translitterato !== false) {
            $value = $translitterato;
        }
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
