<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use App\Controllers\BrandController;
use App\Controllers\LavorazioneController;
use App\Core\Router;

session_start();

$router = new Router();

$lavorazioneController = new LavorazioneController();
$brandController = new BrandController();

$router->get('/', static function () {
    header('Location: /lavorazioni');
    exit;
});

$router->get('/lavorazioni', static fn () => $lavorazioneController->elenco());
$router->get('/lavorazioni/nuova', static fn () => $lavorazioneController->nuovaStep1());
$router->post('/lavorazioni/anteprima', static fn () => $lavorazioneController->anteprima());

$router->get('/brand', static fn () => $brandController->index());
$router->post('/brand', static fn () => $brandController->store());
$router->post('/brand/{id}/toggle', static fn (string $id) => $brandController->toggle((int) $id));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
