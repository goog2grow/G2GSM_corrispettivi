<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Esito del calcolo pivot (giorno x aliquota) per una lavorazione.
 *
 * @param array<int, float> $aliquote aliquote rilevate nel periodo, ordinate crescenti
 * @param array<int, array<string, array{incassato: float, iva: float}>> $giorni
 *        giorno del mese (1..ultimoGiorno) => [aliquota (come stringa) => ['incassato' => ..., 'iva' => ...]]
 * @param array<int, array{incassato: float, iva: float}> $totaliPerGiorno
 *        giorno => totale di riga, sommando su tutte le aliquote
 * @param array<string, array{incassato: float, iva: float}> $totaliPerAliquota
 *        aliquota (come stringa) => totale di colonna, sommando su tutti i giorni
 */
final class PivotResult
{
    public function __construct(
        public readonly int $mese,
        public readonly int $anno,
        public readonly int $ultimoGiorno,
        public readonly array $aliquote,
        public readonly array $giorni,
        public readonly array $totaliPerGiorno,
        public readonly array $totaliPerAliquota,
        public readonly float $totaleGeneraleIncassato,
        public readonly float $totaleGeneraleIva,
        public readonly float $totaleSenzaSplitIncassato,
        public readonly float $totaleSenzaSplitIva,
        public readonly int $righeFuoriPeriodo,
        public readonly int $numeroOrdini,
        public readonly int $numeroResi,
    ) {
    }

    /** @return array{incassato: float, iva: float} */
    public function cella(int $giorno, float $aliquota): array
    {
        return $this->giorni[$giorno][(string) $aliquota] ?? ['incassato' => 0.0, 'iva' => 0.0];
    }
}
