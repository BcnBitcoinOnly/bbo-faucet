<?php

declare(strict_types=1);

namespace BBO\Faucet\Bitcoin;

final readonly class RPCClient
{
    /**
     * Before feeding unsanitized user input to bitcoind's RPC as a
     * bitcoin address at least validate that it is an alphanumeric string.
     *
     * bitcoind RPC has its own address validation logic and error reporting.
     */
    private const string ADDRESS_LOW_FI_REGEX = '/^[0-9a-zA-Z]+$/';

    private string $endpoint;
    private string $authString;

    public function __construct(string $endpoint, string $user, string $password, ?string $walletName)
    {
        if (null !== $walletName) {
            $endpoint .= '/wallet/'.$walletName;
        }

        $this->endpoint = $endpoint;
        $this->authString = base64_encode("$user:$password");
    }

    public function createWallet(string $name): void
    {
        $this->doRequest('createwallet', ['wallet_name' => $name, 'load_on_startup' => true]);
    }

    public function generate(int $blocks): void
    {
        $address = $this->doRequest('getnewaddress', [])->result;
        $this->doRequest('generatetoaddress', ['nblocks' => $blocks, 'address' => $address]);
    }

    public function getBlockCount(): int
    {
        return $this->doRequest('getblockcount', [])->result;
    }

    public function getBalance(): float
    {
        return $this->doRequest('getbalance', [])->result;
    }

    public function validateAddress(string $address): bool
    {
        if (!preg_match(self::ADDRESS_LOW_FI_REGEX, $address)) {
            return false;
        }

        return $this->doRequest('validateaddress', [$address])->result->isvalid;
    }

    public function send(string $address, float $amount): string
    {
        // TODO throw exception on error
        // KO: {"result":null,"error":{"code":-3,"message":"Invalid amount"},"id":"curltest"}
        // KO: {"result":null,"error":{"code":-5,"message":"Invalid Bitcoin address: mgua351KhLdJdvxueGUrxvTTX8fJNGT4zg"},"id":"curltest"}
        // KO: {"result":null,"error":{"code":-4,"message":"Insufficient funds"},"id":"curltest"}

        // OK: {"result":{"txid":"a0855d0645e122879e2a97b9192a5fe5d95f35991c358615931b502e6925fd06","complete":true},"error":null,"id":"curltest"}
        return $this->batchSend([$address => $amount]);
    }

    public function batchSend(array $payments): string
    {
        return $this->doRequest('send', ['outputs' => $payments, 'fee_rate' => 0])->result->txid;
    }

    private function doRequest(string $method, array $params): \stdClass
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    "Authorization: Basic {$this->authString}",
                    'Content-Type: application/json',
                ],
                'content' => json_encode(['jsonrpc' => '1.0', 'id' => 'faucet', 'method' => $method, 'params' => $params]),
                'ignore_errors' => true,
            ],
        ]);

        if (false === $response = file_get_contents($this->endpoint, context: $context)) {
            throw new \RuntimeException("{$this->endpoint} is down or not reachable");
        }

        if ('' === $response) {
            throw new \RuntimeException('Invalid credentials supplied');
        }

        return json_decode($response);
    }
}
