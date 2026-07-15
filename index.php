<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use App\Controllers\BrandController;
use App\Controllers\ExportController;
use App\Controllers\LavorazioneController;
use App\Core\Router;

session_start();

$router = new Router();

$lavorazioneController = new LavorazioneController();
$brandController = new BrandController();
$exportController = new ExportController();

$router->get('/', static function () {
    header('Location: /lavorazioni');
    exit;
});

$router->get('/lavorazioni', static fn () => $lavorazioneController->elenco());
$router->get('/lavorazioni/nuova', static fn () => $lavorazioneController->nuovaStep1());
$router->post('/lavorazioni/anteprima', static fn () => $lavorazioneController->anteprima());
$router->post('/lavorazioni/salva', static fn () => $lavorazioneController->salva());
$router->get('/lavorazioni/salvata', static fn () => $lavorazioneController->salvata());
$router->post('/lavorazioni/annulla', static fn () => $lavorazioneController->annulla());
$router->get('/lavorazioni/{id}/excel', static fn (string $id) => $exportController->excel((int) $id));
$router->get('/lavorazioni/{id}/csv', static fn (string $id) => $exportController->csvOriginale((int) $id));
$router->post('/lavorazioni/{id}/elimina', static fn (string $id) => $lavorazioneController->elimina((int) $id));
// deve stare DOPO le route statiche /lavorazioni/nuova e /lavorazioni/salvata,
// altrimenti il router le catturerebbe come se fossero un {id}
$router->get('/lavorazioni/{id}', static fn (string $id) => $lavorazioneController->dettaglio((int) $id));

$router->get('/brand', static fn () => $brandController->index());
$router->post('/brand', static fn () => $brandController->store());
$router->post('/brand/{id}/toggle', static fn (string $id) => $brandController->toggle((int) $id));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
