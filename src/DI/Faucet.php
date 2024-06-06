<?php

declare(strict_types=1);

namespace BBO\Faucet\DI;

use BBO\Faucet\Bitcoin\RPCClient;
use BBO\Faucet\Controller;
use BBO\Faucet\Middleware;
use Gregwar\Captcha\CaptchaBuilder;
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
        $c->set(Settings::class, static function (): Settings {
            return new Settings($_SERVER);
        });

        $c->set(Twig::class, static function (ContainerInterface $c): Twig {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);
            $twig = Twig::create(__ROOT__.'/views', ['debug' => $settings->debugMode, 'strict_variables' => true]);

            $twig->getEnvironment()->addGlobal('faucet_name', $settings->faucetName);
            $twig->getEnvironment()->addGlobal('faucet_min_btc', (string) $settings->minOneTimeBtc);
            $twig->getEnvironment()->addGlobal('faucet_max_btc', (string) $settings->maxOneTimeBtc);
            $twig->getEnvironment()->addGlobal('use_captcha', $settings->useCaptcha);
            $twig->getEnvironment()->addGlobal('use_password', null !== $settings->passwordBcryptHash);

            return $twig;
        });

        $c->set(\Redis::class, static function (ContainerInterface $c): \Redis {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            $redis = new \Redis([
                'host' => $settings->redisHost,
                'port' => $settings->redisPort,
            ]);

            $redis->setOption(\Redis::OPT_PREFIX, $settings->redisPrefix);

            return $redis;
        });

        $c->factory(CaptchaBuilder::class, static function (): CaptchaBuilder {
            return new CaptchaBuilder();
        });

        $c->set(Middleware\Captcha::class, static function (ContainerInterface $c): MiddlewareInterface {
            return new Middleware\Captcha(
                $c->get(Twig::class)
            );
        });

        $c->set(Middleware\Limits::class, static function (ContainerInterface $c): MiddlewareInterface {
            return new Middleware\Captcha(
                $c->get(Twig::class)
            );
        });

        $c->set(Middleware\Password::class, static function (ContainerInterface $c): MiddlewareInterface {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            return new Middleware\Password(
                $c->get(Twig::class),
                $settings->passwordBcryptHash
            );
        });

        $c->set(Middleware\RedisLock::class, static function (ContainerInterface $c): MiddlewareInterface {
            return new Middleware\RedisLock($c->get(\Redis::class));
        });

        $c->set(Middleware\RedisSession::class, static function (ContainerInterface $c): MiddlewareInterface {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            return new Middleware\RedisSession(
                $c->get(\Redis::class),
                $settings->userSessionTtl,
                $settings->globalSessionTtl,
            );
        });

        $c->set(RPCClient::class, static function (ContainerInterface $c): RPCClient {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            return new RPCClient(
                $settings->bitcoinRpcEndpoint,
                $settings->bitcoinRpcUser,
                $settings->bitcoinRpcPass,
                $settings->bitcoinRpcWallet
            );
        });

        $c->set(Controller\LandingPage::class, static function (ContainerInterface $c): RequestHandlerInterface {
            return new Controller\LandingPage($c->get(Twig::class));
        });

        $c->set(Controller\CaptchaRender::class, static function (ContainerInterface $c): RequestHandlerInterface {
            return new Controller\CaptchaRender($c->get(CaptchaBuilder::class));
        });

        $c->set(Controller\FormProcessing::class, static function (ContainerInterface $c): RequestHandlerInterface {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            return new Controller\FormProcessing(
                $c->get(Twig::class),
                $c->get(RPCClient::class),
                $settings->minOneTimeBtc,
                $settings->maxOneTimeBtc,
                $settings->mempoolUrl
            );
        });

        $c->set(self::class, static function (ContainerInterface $c): App {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            $app = AppFactory::create(container: $c);
            $app->addErrorMiddleware($settings->debugMode, $settings->debugMode, $settings->debugMode);

            $app->get('/', $c->get(Controller\LandingPage::class));
            $formRoute = $app->post('/', $c->get(Controller\FormProcessing::class));

            if ($settings->useCaptcha) {
                $formRoute->add(Middleware\Captcha::class);
                $app->get('/captcha', $c->get(Controller\CaptchaRender::class));
            }

            if (null !== $settings->passwordBcryptHash) {
                $formRoute->add(Middleware\Password::class);
            }

            $app->add($c->get(Middleware\RedisSession::class));
            $app->add($c->get(Middleware\RedisLock::class));

            return $app;
        });
    }
}
