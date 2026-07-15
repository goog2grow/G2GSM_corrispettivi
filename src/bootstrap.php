<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Rome');

$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
