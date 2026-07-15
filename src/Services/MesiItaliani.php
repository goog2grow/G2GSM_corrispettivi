<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class MesiItaliani
{
    private const NOMI = [
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
        5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
        9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
    ];

    /** @return array<int, string> */
    public static function tutti(): array
    {
        return self::NOMI;
    }

    public static function nome(int $mese): string
    {
        return self::NOMI[$mese] ?? throw new InvalidArgumentException("Mese non valido: {$mese}");
    }
}
