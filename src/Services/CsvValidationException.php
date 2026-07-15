<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Errore bloccante nella validazione del formato del CSV
 * (es. header mancante/errato, file vuoto, nessuna riga valida).
 */
final class CsvValidationException extends RuntimeException
{
}
