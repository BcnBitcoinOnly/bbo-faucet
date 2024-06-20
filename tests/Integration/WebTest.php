<?php

declare(strict_types=1);

namespace BBO\Faucet\Tests\Integration;

use BBO\Faucet\DI\Faucet;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Slim\App;
use UMA\DIC\Container;

final class WebTest extends TestCase
{
    private App $sut;

    protected function setUp(): void
    {
        $container = new Container();
        $container->register(new Faucet());

        $this->sut = $container->get(Faucet::class);
    }

    public function testLandingPage(): void
    {
        $res = $this->sut->handle(new ServerRequest('GET', '/'));

        self::assertSame(200, $res->getStatusCode());
        self::assertStringContainsString('<title>Testing Faucet</title>', (string) $res->getBody());
    }
}
