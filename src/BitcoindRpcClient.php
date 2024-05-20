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

    public function send(string $address, float $amount): string
    {
        // TODO throw exception on error
        // KO: {"result":null,"error":{"code":-3,"message":"Invalid amount"},"id":"curltest"}
        // KO: {"result":null,"error":{"code":-5,"message":"Invalid Bitcoin address: mgua351KhLdJdvxueGUrxvTTX8fJNGT4zg"},"id":"curltest"}
        // KO: {"result":null,"error":{"code":-4,"message":"Insufficient funds"},"id":"curltest"}

        // OK: {"result":{"txid":"a0855d0645e122879e2a97b9192a5fe5d95f35991c358615931b502e6925fd06","complete":true},"error":null,"id":"curltest"}
        return $this->doRequest($this->newContext('send', [[$address => $amount], null, 'unset', 0]))->result->txid;
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
