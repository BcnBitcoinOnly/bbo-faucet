<?php

declare(strict_types=1);

namespace BBO\Faucet\DI;

use BBO\Faucet\Bitcoin\Batcher;
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
            return new Settings(array_merge($_SERVER, $_ENV));
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

        $c->set(Middleware\CheckCaptcha::class, static function (ContainerInterface $c): MiddlewareInterface {
            return new Middleware\CheckCaptcha(
                $c->get(Twig::class),
                $c->get(\Redis::class)
            );
        });

        $c->set(Middleware\UsageLimits::class, static function (ContainerInterface $c): MiddlewareInterface {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            return new Middleware\UsageLimits(
                $c->get(Twig::class),
                $c->get(\Redis::class),
                $settings->userSessionTtl,
                $settings->globalSessionTtl,
                $settings->userSessionMaxBtc,
                $settings->globalSessionMaxBtc
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

        $c->set(Batcher::class, static function (ContainerInterface $c): Batcher {
            return new Batcher(
                $c->get(\Redis::class),
                $c->get(RPCClient::class),
            );
        });

        $c->set(RPCClient::class, static function (ContainerInterface $c): RPCClient {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            return new RPCClient(
                $settings->bitcoinRpcEndpoint,
                $settings->bitcoinRpcUser,
                $settings->bitcoinRpcPass,
                $settings->feeRate,
                $settings->bitcoinRpcWallet
            );
        });

        $c->set(Controller\LandingPage::class, static function (ContainerInterface $c): RequestHandlerInterface {
            return new Controller\LandingPage($c->get(Twig::class));
        });

        $c->set(Controller\CaptchaImage::class, static function (ContainerInterface $c): RequestHandlerInterface {
            return new Controller\CaptchaImage(
                $c->get(CaptchaBuilder::class),
                $c->get(\Redis::class)
            );
        });

        $c->set(Controller\FormProcessing::class, static function (ContainerInterface $c): RequestHandlerInterface {
            /** @var Settings $settings */
            $settings = $c->get(Settings::class);

            return new Controller\FormProcessing(
                $c->get(Twig::class),
                $c->get(RPCClient::class),
                $settings->minOneTimeBtc,
                $settings->maxOneTimeBtc,
                $settings->useTxBatching ? $c->get(Batcher::class) : null,
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

            // If captcha is enabled register captcha image generation route and
            // activate captcha enforcing middleware on the form route
            if ($settings->useCaptcha) {
                $app->get('/captcha', $c->get(Controller\CaptchaImage::class));
                $formRoute->add(Middleware\CheckCaptcha::class);
            }

            // If password is enabled activate password checking middleware on
            // the form route.
            if (null !== $settings->passwordBcryptHash) {
                $formRoute->add(Middleware\Password::class);
            }

            // If user and global limits are both disabled there's no need to
            // activate the Redis spinlock nor the limit enforcement middleware.
            if (null !== $settings->userSessionMaxBtc || null !== $settings->globalSessionMaxBtc) {
                $formRoute->add($c->get(Middleware\UsageLimits::class));
                $formRoute->add($c->get(Middleware\RedisLock::class));
            }

            return $app;
        });
    }
}
