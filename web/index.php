<?php

declare(strict_types=1);

use BBO\Faucet\DI\Faucet;
use UMA\DIC\Container;

define('__ROOT__', dirname(__DIR__));

if (!file_exists(__ROOT__.'/settings.ini')) {
    http_response_code(500);
    exit('settings.ini missing');
}

$settings = parse_ini_file(__ROOT__.'/settings.ini', scanner_mode: \INI_SCANNER_TYPED);

if (!extension_loaded('redis')) {
    http_response_code(500);
    exit('redis extension not enabled');
}

require __ROOT__.'/vendor/autoload.php';

$container = new Container();
$container->register(new Faucet());

$container->get(Faucet::class)->run();
