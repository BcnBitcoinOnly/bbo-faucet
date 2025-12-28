<?php

declare(strict_types=1);

namespace BBO\Faucet\DI;

final readonly class Settings
{
    public bool $debugMode;

    public string $redisHost;
    public int $redisPort;
    public string $redisPrefix;

    public string $bitcoinRpcEndpoint;
    public string $bitcoinRpcUser;
    public string $bitcoinRpcPass;
    public ?string $bitcoinRpcWallet;
    public float $feeRate;

    public string $faucetName;
    public ?string $mempoolUrl;
    public float $minOneTimeBtc;
    public float $maxOneTimeBtc;

    public int $userSessionTtl;
    public int $globalSessionTtl;
    public ?float $userSessionMaxBtc;
    public ?float $globalSessionMaxBtc;

    public ?string $passwordBcryptHash;
    public bool $useCaptcha;

    public bool $useTxBatching;

    public function __construct(array $values)
    {
        $this->debugMode = (bool) $values['FAUCET_DEBUG'];

        [$this->redisHost, $redisPort] = explode(':', $values['FAUCET_REDIS_ENDPOINT']);
        $this->redisPort = (int) $redisPort;
        $this->redisPrefix = $values['FAUCET_REDIS_PREFIX'];

        if (str_ends_with($rpcEndpoint = $values['FAUCET_BITCOIN_RPC_ENDPOINT'], ':8332')) {
            exit('Refusing to run on the standard mainnet RPC port (TCP 8332)');
        }

        $this->bitcoinRpcEndpoint = $rpcEndpoint;
        $this->bitcoinRpcWallet = $values['FAUCET_BITCOIN_RPC_WALLET'] ?? null;
        if ($cookie = $values['FAUCET_BITCOIN_RPC_COOKIE']) {
            if (!is_file($cookie) || !is_readable($cookie)) {
                exit('Unreadable bitcoind cookie file: '.$cookie);
            }

            [$this->bitcoinRpcUser, $this->bitcoinRpcPass] = explode(':', file_get_contents($cookie));
        } else {
            $this->bitcoinRpcUser = $values['FAUCET_BITCOIN_RPC_USER'];
            $this->bitcoinRpcPass = $values['FAUCET_BITCOIN_RPC_PASS'];
        }

        $this->feeRate = isset($values['FAUCET_FEE_RATE']) ? (float) $values['FAUCET_FEE_RATE'] : 1.0;
        $this->faucetName = $values['FAUCET_NAME'];
        $this->mempoolUrl = $values['FAUCET_MEMPOOL_URL'] ?? null;
        $this->minOneTimeBtc = (float) $values['FAUCET_MIN_ONE_TIME_BTC'];
        $this->maxOneTimeBtc = (float) $values['FAUCET_MAX_ONE_TIME_BTC'];

        $this->userSessionTtl = (int) $values['FAUCET_USER_SESSION_TTL'];
        $this->userSessionMaxBtc = '' === $values['FAUCET_USER_SESSION_MAX_BTC'] ? null : (float) $values['FAUCET_USER_SESSION_MAX_BTC'];
        $this->globalSessionTtl = (int) $values['FAUCET_GLOBAL_SESSION_TTL'];
        $this->globalSessionMaxBtc = '' === $values['FAUCET_GLOBAL_SESSION_MAX_BTC'] ? null : (float) $values['FAUCET_GLOBAL_SESSION_MAX_BTC'];

        $this->passwordBcryptHash = $values['FAUCET_PASSWORD_BCRYPT_HASH'] ?? null;
        $this->useCaptcha = (bool) $values['FAUCET_USE_CAPTCHA'];

        $this->useTxBatching = (bool) $values['FAUCET_USE_BATCHING'];
    }
}
