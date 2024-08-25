<?php

declare(strict_types=1);

use BBO\Faucet\Bitcoin\Batcher;
use BBO\Faucet\DI\Faucet;
use UMA\DIC\Container;

/*
 * Execute a batched payment with pending payouts
 */

if (!extension_loaded('redis')) {
    http_response_code(500);
    exit('redis extension not enabled, cannot continue');
}

require __DIR__.'/../vendor/autoload.php';

$container = new Container();
$container->register(new Faucet());

echo $container->get(Batcher::class)->send().\PHP_EOL;
