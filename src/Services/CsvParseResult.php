<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Esito della lettura/validazione di un CSV di corrispettivi.
 *
 * @param array<int, array<string, mixed>> $rows righe valide, normalizzate
 * @param array<int, string> $rowErrors righe scartate per errori di formato
 * @param array<int, string> $warnings avvisi non bloccanti
 * @param array<int, float> $aliquote aliquote IVA distinte rilevate nel file, ordinate
 * @param array<int, string> $brandRilevati valori distinti della colonna "brand"
 * @param array<int, string> $channelRilevati valori distinti della colonna "channel"
 */
final class CsvParseResult
{
    public function __construct(
        public readonly array $rows,
        public readonly array $rowErrors,
        public readonly array $warnings,
        public readonly array $aliquote,
        public readonly array $brandRilevati,
        public readonly array $channelRilevati,
        public readonly int $totaleRighe,
        public readonly int $totaleResi,
        public readonly float $totaleIncassato,
        public readonly float $totaleIva,
        public readonly ?string $paidDateMin,
        public readonly ?string $paidDateMax,
    ) {
    }
}
