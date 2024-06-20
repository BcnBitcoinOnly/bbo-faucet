<?php

declare(strict_types=1);

namespace BBO\Faucet\Tests\Integration;

use BBO\Faucet\Bitcoin\RPCClient;
use PHPUnit\Framework\TestCase;

final class RPCClientTest extends TestCase
{
    private RPCClient $sut;

    protected function setUp(): void
    {
        $this->sut = new RPCClient(
            $_ENV['FAUCET_BITCOIN_RPC_ENDPOINT'],
            $_ENV['FAUCET_BITCOIN_RPC_USER'],
            $_ENV['FAUCET_BITCOIN_RPC_PASS'],
            0,
            null
        );
    }

    public function testValidateAddress(): void
    {
        self::assertFalse($this->sut->validateAddress('nonsense!'));
        self::assertTrue($this->sut->validateAddress('mwxHTZVYD44DZSoqCNXGzeS2LMB9smqFG6'));
        self::assertFalse($this->sut->validateAddress('nwxHTZVYD44DZSoqCNXGzeS2LMB9smqFG6'));
        self::assertFalse($this->sut->validateAddress('32ixEdVJWo3kmvJGMTZq5jAQVZZeuwnqzo'));
    }

    public function testIntegrationScenario(): void
    {
        $blocks = $this->sut->getBlockCount();
        $balance = $this->sut->getBalance();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->sut->send('mwxHTZVYD44DZSoqCNXGzeS2LMB9smqFG6', 1.0));

        $this->sut->generate(1);

        self::assertSame($blocks + 1, $this->sut->getBlockCount());
        self::assertSame($balance + 49.0, $this->sut->getBalance());
    }
}
