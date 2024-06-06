<?php

declare(strict_types=1);

use BBO\Faucet\Bitcoin\Batcher;
use BBO\Faucet\DI\Faucet;
use UMA\DIC\Container;

if (!extension_loaded('redis')) {
    http_response_code(500);
    exit('redis extension not enabled');
}

require __DIR__.'/vendor/autoload.php';

$container = new Container();
$container->register(new Faucet());

echo $container->get(Batcher::class)->send().\PHP_EOL;
