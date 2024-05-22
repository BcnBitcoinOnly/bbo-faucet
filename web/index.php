<?php

declare(strict_types=1);

use BBO\Faucet\BitcoindRpcClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;

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

$twig = Twig::create(__ROOT__.'/views', ['debug' => $settings['debug'], 'strict_variables' => true]);
$twig->getEnvironment()->addGlobal('faucet_name', $settings['faucet_name']);
$twig->getEnvironment()->addGlobal('faucet_max_btc', $settings['faucet_max_btc']);
$twig->getEnvironment()->addGlobal('faucet_min_btc', $settings['faucet_min_btc']);
$twig->getEnvironment()->addGlobal('use_password', $settings['use_password']);
$twig->getEnvironment()->addGlobal('use_captcha', $settings['use_captcha']);

$app = AppFactory::create();
$app->addErrorMiddleware($settings['debug'], $settings['debug'], $settings['debug']);

$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($twig) {
    return $twig->render($response, 'index.html.twig');
});

$app->post('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $settings) {
    $redis = new Redis([
        'host' => $settings['redis_host'],
        'port' => $settings['redis_port'],
    ]);

    $ip = $request->getHeaderLine('X-Forwarded-For');
    $userData = $redis->get("ip:$ip");
    $setOpts = false === $userData ? ['ex' => $settings['cooldown_time']] : ['keepttl'];
    $userData = false === $userData ? ['btc' => 0] : json_decode($userData, true);

    $form = $request->getParsedBody();

    if (!is_array($form) || empty($form['address']) || empty($form['amount'])) {
        return $twig->render($response, 'index.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Invalid data']]);
    }

    $amount = (float) $form['amount'];
    if ($userData['btc'] + $amount > $settings['cooldown_max_btc']) {
        return $twig->render($response, 'index.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Too much collected']]);
    }

    $rpc = new BitcoindRpcClient($settings['bitcoind_rpc_url'], $settings['bitcoind_rpc_user'], $settings['bitcoind_rpc_pass'], $settings['wallet_name']);
    $txId = $rpc->send($form['address'], $amount);

    $userData['btc'] += $amount;

    $redis->set("ip:$ip", json_encode($userData), $setOpts);

    $message = null === $settings['mempool_url'] ?
        "Transaction sent: $txId" :
        "Transaction sent: <a href=\"{$settings['mempool_url']}/tx/{$txId}\">$txId</a>";

    return $twig->render($response, 'index.html.twig', ['notification' => ['class' => 'is-success', 'message' => $message]]);
});

$app->run();
