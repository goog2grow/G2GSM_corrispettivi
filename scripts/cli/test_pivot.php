<?php

declare(strict_types=1);

/**
 * Script di verifica della logica di pivot da riga di comando, senza
 * passare dalla UI/DB.
 *
 * Uso:
 *   php scripts/cli/test_pivot.php <path_csv> <mese> <anno>
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Services\CsvParser;
use App\Services\PivotCalculator;

$csvPath = $argv[1] ?? null;
$mese = isset($argv[2]) ? (int) $argv[2] : null;
$anno = isset($argv[3]) ? (int) $argv[3] : null;

if ($csvPath === null || $mese === null || $anno === null) {
    fwrite(STDERR, "Uso: php scripts/cli/test_pivot.php <path_csv> <mese> <anno>\n");
    exit(1);
}

try {
    $result = (new CsvParser())->parseFile($csvPath);
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore di parsing: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Righe valide: {$result->totaleRighe} (resi: {$result->totaleResi})" . PHP_EOL;
if (!empty($result->rowErrors)) {
    echo 'Righe scartate per errori di formato: ' . count($result->rowErrors) . PHP_EOL;
}
echo 'Aliquote rilevate nel file: ' . implode(', ', array_map(
    static fn ($a) => number_format($a * 100, 2) . '%',
    $result->aliquote
)) . PHP_EOL;
echo PHP_EOL;

$pivot = (new PivotCalculator())->calcola($result->rows, $mese, $anno);

$colWidth = 14;
$header = str_pad('Giorno', 8);
foreach ($pivot->aliquote as $aliquota) {
    $label = number_format($aliquota * 100, 0) . '%';
    $header .= str_pad("Inc {$label}", $colWidth) . str_pad("IVA {$label}", $colWidth);
}
$header .= str_pad('Tot incassato', 16) . str_pad('Tot IVA', 14);

echo $header . PHP_EOL;
echo str_repeat('-', strlen($header)) . PHP_EOL;

foreach ($pivot->giorni as $giorno => $celle) {
    $riga = str_pad((string) $giorno, 8);
    foreach ($pivot->aliquote as $aliquota) {
        $cella = $celle[(string) $aliquota];
        $riga .= str_pad(number_format($cella['incassato'], 2), $colWidth);
        $riga .= str_pad(number_format($cella['iva'], 2), $colWidth);
    }
    $totRiga = $pivot->totaliPerGiorno[$giorno];
    $riga .= str_pad(number_format($totRiga['incassato'], 2), 16);
    $riga .= str_pad(number_format($totRiga['iva'], 2), 14);
    echo $riga . PHP_EOL;
}

echo str_repeat('-', strlen($header)) . PHP_EOL;

$rigaTot = str_pad('TOTALE', 8);
foreach ($pivot->aliquote as $aliquota) {
    $tot = $pivot->totaliPerAliquota[(string) $aliquota];
    $rigaTot .= str_pad(number_format($tot['incassato'], 2), $colWidth);
    $rigaTot .= str_pad(number_format($tot['iva'], 2), $colWidth);
}
$rigaTot .= str_pad(number_format($pivot->totaleGeneraleIncassato, 2), 16);
$rigaTot .= str_pad(number_format($pivot->totaleGeneraleIva, 2), 14);
echo $rigaTot . PHP_EOL;

echo PHP_EOL;
echo "Numero ordini (righe): {$pivot->numeroOrdini} | Resi: {$pivot->numeroResi}" . PHP_EOL;
echo 'Totale generale pivot: incassato = ' . number_format($pivot->totaleGeneraleIncassato, 2)
    . ' | iva = ' . number_format($pivot->totaleGeneraleIva, 2) . PHP_EOL;
echo 'Totale senza split (intero file): incassato = ' . number_format($pivot->totaleSenzaSplitIncassato, 2)
    . ' | iva = ' . number_format($pivot->totaleSenzaSplitIva, 2) . PHP_EOL;

if ($pivot->righeFuoriPeriodo > 0) {
    echo "ATTENZIONE: {$pivot->righeFuoriPeriodo} riga/e con paidDate fuori dal periodo {$anno}-"
        . str_pad((string) $mese, 2, '0', STR_PAD_LEFT)
        . ' (escluse dalla pivot, incluse nel totale senza split).' . PHP_EOL;
}
