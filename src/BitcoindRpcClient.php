<?php

declare(strict_types=1);

namespace BBO\Faucet;

final readonly class BitcoindRpcClient
{
    private string $endpoint;
    private string $authString;

    public function __construct(string $endpoint, string $user, string $password)
    {
        $this->endpoint = $endpoint;
        $this->authString = base64_encode("$user:$password");
    }

    public function getBlockCount(): int
    {
        return $this->doRequest($this->newContext('getblockcount', []))->result;
    }

    public function getBalance(): float
    {
        return $this->doRequest($this->newContext('getbalance', []))->result;
    }

    /**
     * @param resource $context
     */
    private function doRequest($context): \stdClass
    {
        if (false === $response = file_get_contents($this->endpoint, context: $context)) {
            throw new \RuntimeException('fug');
        }

        return json_decode($response);
    }

    /**
     * @return resource
     */
    private function newContext(string $method, array $params)
    {
        return stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Basic {$this->authString}\r\nContent-Type: application/json\r\n",
                'content' => json_encode(['jsonrpc' => '1.0', 'id' => 'faucet', 'method' => $method, 'params' => $params]),
            ],
        ]);
    }
}
