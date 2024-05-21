<?php

declare(strict_types=1);

namespace BBO\Faucet\Tests\Integration;

use BBO\Faucet\BitcoindRpcClient;
use PHPUnit\Framework\TestCase;

final class BitcoindRpcClientTest extends TestCase
{
    private BitcoindRpcClient $sut;

    protected function setUp(): void
    {
        $this->sut = new BitcoindRpcClient($_ENV['RPC_URL'], $_ENV['RPC_USER'], $_ENV['RPC_PASS'], null);
    }

    public function testIntegrationScenario(): void
    {
        $this->sut->createWallet('faucet');
        $this->sut->generate(101);

        self::assertSame(101, $this->sut->getBlockCount());
        self::assertSame(50.0, $this->sut->getBalance());

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->sut->send('mwxHTZVYD44DZSoqCNXGzeS2LMB9smqFG6', 1.0));

        $this->sut->generate(1);

        self::assertSame(102, $this->sut->getBlockCount());
        self::assertSame(99.0, $this->sut->getBalance());
    }
}
