<?php

declare(strict_types=1);

namespace App\Services;

final class SalvataggioEsito
{
    /**
     * @param array<string, mixed>|null $precedenteAttiva la lavorazione che era attiva
     *        per quel periodo prima di questo salvataggio, se esisteva
     */
    public function __construct(
        public readonly int $lavorazioneId,
        public readonly string $nomeLavorazione,
        public readonly int $numeroVersione,
        public readonly ?array $precedenteAttiva,
    ) {
    }
}
