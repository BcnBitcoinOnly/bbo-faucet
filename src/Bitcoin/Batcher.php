<?php

declare(strict_types=1);

namespace BBO\Faucet\Bitcoin;

final readonly class Batcher
{
    private const string REDIS_KEY = 'pending_payments';

    private \Redis $redis;
    private RPCClient $rpc;

    private const string GET_LIST_SCRIPT = <<<LUA
local batch = redis.call("lrange",KEYS[1],0,-1)
redis.call("del",KEYS[1])
return batch
LUA;

    public function __construct(\Redis $redis, RPCClient $rpc)
    {
        $this->redis = $redis;
        $this->rpc = $rpc;
    }

    public function batch(string $address, float $amount): void
    {
        $this->redis->lPush(self::REDIS_KEY, json_encode(['address' => $address, 'amount' => $amount]));
    }

    public function send(): string
    {
        $items = $this->redis->eval(self::GET_LIST_SCRIPT, [self::REDIS_KEY], 1);

        $payments = [];
        foreach ($items as $item) {
            $item = json_decode($item);

            if (isset($payments[$item->address])) {
                $payments[$item->address] += $item->amount;
            } else {
                $payments[$item->address] = $item->amount;
            }
        }

        return empty($payments) ?
            'No pending payments' :
            $this->rpc->batchSend($payments);
    }
}
