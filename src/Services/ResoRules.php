<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Isola la logica di rilevamento dei resi in un unico punto.
 *
 * Assunzione attuale (nei file di esempio forniti non erano presenti
 * resi): una riga e' considerata un reso quando l'importo incassato
 * (totPaid) e' negativo. Se in futuro il formato reale dei resi
 * risultasse diverso (es. flag dedicato, o solo totTax negativo senza
 * totPaid negativo), modificare esclusivamente questo metodo: tutto il
 * resto dell'applicazione dipende solo da questa funzione, mai da un
 * controllo diretto sul segno di totPaid.
 *
 * @param array<string, mixed> $row riga CSV normalizzata (chiavi = header originali)
 */
final class ResoRules
{
    public static function isReso(array $row): bool
    {
        $totPaid = $row['totPaid'] ?? null;

        return is_numeric($totPaid) && (float) $totPaid < 0.0;
    }
}
