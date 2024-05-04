<?php

declare(strict_types=1);

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

ini_set('redis.session.locking_enabled', true);
ini_set('session.cookie_samesite', true);
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', $settings['redis_dsn']);
ini_set('session.use_strict_mode', true);

session_start();
session_regenerate_id(delete_old_session: true);

require __ROOT__.'/vendor/autoload.php';

$twig = Twig::create(__ROOT__.'/views', ['debug' => $settings['debug'], 'strict_variables' => true]);

$app = AppFactory::create();
$app->addErrorMiddleware($settings['debug'], $settings['debug'], $settings['debug']);

$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($twig) {
    return $twig->render($response, 'index.html.twig', ['faucetName' => 'BBO Faucet', 'use_pass' => false, 'use_captcha' => false]);
});

$app->run();
