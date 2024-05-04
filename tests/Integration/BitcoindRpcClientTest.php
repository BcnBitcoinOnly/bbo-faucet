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
        $this->sut = new BitcoindRpcClient($_ENV['RPC_URL'], $_ENV['RPC_USER'], $_ENV['RPC_PASS']);
    }

    public function testGetBlockCount(): void
    {
        self::assertSame(101, $this->sut->getBlockCount());
    }

    public function testGetBalance(): void
    {
        self::assertSame(50.0, $this->sut->getBalance());
    }
}
