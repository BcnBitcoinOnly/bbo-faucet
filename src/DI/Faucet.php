<?php

declare(strict_types=1);

namespace BBO\Faucet\DI;

use BBO\Faucet\Bitcoin\RPCClient;
use BBO\Faucet\Controller;
use BBO\Faucet\Middleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use UMA\DIC\Container;
use UMA\DIC\ServiceProvider;

final class Faucet implements ServiceProvider
{
    public function provide(Container $c): void
    {
        $c->set('settings', static function (): array {
            return parse_ini_file(__ROOT__.'/settings.ini', scanner_mode: \INI_SCANNER_TYPED);
        });

        $c->set(Twig::class, static function (ContainerInterface $c): Twig {
            $settings = $c->get('settings');

            $twig = Twig::create(__ROOT__.'/views', ['debug' => $settings['debug'], 'strict_variables' => true]);

            $twig->getEnvironment()->addGlobal('faucet_name', $settings['faucet_name']);
            $twig->getEnvironment()->addGlobal('faucet_max_btc', $settings['faucet_max_btc']);
            $twig->getEnvironment()->addGlobal('faucet_min_btc', $settings['faucet_min_btc']);
            $twig->getEnvironment()->addGlobal('use_password', $settings['use_password']);
            $twig->getEnvironment()->addGlobal('use_captcha', $settings['use_captcha']);

            return $twig;
        });

        $c->set(\Redis::class, static function (ContainerInterface $c): \Redis {
            return new \Redis([
                'host' => $c->get('settings')['redis_host'],
                'port' => $c->get('settings')['redis_port'],
            ]);
        });

        $c->set(Middleware\RedisLock::class, static function (ContainerInterface $c): MiddlewareInterface {
            return new Middleware\RedisLock($c->get(\Redis::class));
        });

        $c->set(Middleware\RedisSession::class, static function (ContainerInterface $c): MiddlewareInterface {
            return new Middleware\RedisSession(
                $c->get(\Redis::class),
                $c->get('settings')['cooldown_time']
            );
        });

        $c->set(RPCClient::class, static function (ContainerInterface $c): RPCClient {
            $cookie = $c->get('settings')['bitcoind_rpc_cookie'];
            $user = $c->get('settings')['bitcoind_rpc_user'];
            $password = $c->get('settings')['bitcoind_rpc_pass'];

            if (null !== $cookie) {
                if (!is_file($cookie) || !is_readable($cookie)) {
                    exit('Unreadable bitcoind cookie file: '.$cookie);
                }

                [$user, $password] = explode(':', file_get_contents($cookie));
            }

            return new RPCClient(
                $c->get('settings')['bitcoind_rpc_url'],
                $user,
                $password,
                $c->get('settings')['wallet_name']
            );
        });

        $c->set(Controller\LandingPage::class, static function (ContainerInterface $c): RequestHandlerInterface {
            return new Controller\LandingPage($c->get(Twig::class));
        });

        $c->set(Controller\FormProcessing::class, static function (ContainerInterface $c): RequestHandlerInterface {
            return new Controller\FormProcessing(
                $c->get(Twig::class),
                $c->get(RPCClient::class),
                $c->get('settings')['cooldown_max_btc'],
                $c->get('settings')['mempool_url']
            );
        });

        $c->set(self::class, static function (ContainerInterface $c): App {
            $debug = $c->get('settings')['debug'];

            $app = AppFactory::create(container: $c);
            $app->addErrorMiddleware($debug, $debug, $debug);

            $app->add($c->get(Middleware\RedisSession::class));
            $app->add($c->get(Middleware\RedisLock::class));

            $app->get('/', $c->get(Controller\LandingPage::class));
            $app->post('/', $c->get(Controller\FormProcessing::class));

            return $app;
        });
    }
}