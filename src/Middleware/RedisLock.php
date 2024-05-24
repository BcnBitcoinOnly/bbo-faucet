<?php

declare(strict_types=1);

namespace BBO\Faucet\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Per-IP exclusive locking implemented with a single Redis instance.
 *
 * @see https://redis.io/docs/latest/develop/use/patterns/distributed-locks/#correct-implementation-with-a-single-instance
 */
final readonly class RedisLock implements MiddlewareInterface
{
    private const int LOCK_TIMEOUT = 15;
    private const int LOCK_WAIT_TIME = 100000;
    private const int LOCK_MAX_RETRIES = 10;

    private const string LOCK_RELEASE_SCRIPT_HASH = '647c65a442733a1aa440f99908d249d13b4d6c4a';
    private const string LOCK_RELEASE_SCRIPT = <<<LUA
if redis.call("get",KEYS[1]) == ARGV[1] then
    return redis.call("del",KEYS[1])
else
    return 0
end
LUA;

    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $request->getHeaderLine('X-Forwarded-For');
        $lockValue = base64_encode(random_bytes(24));

        $retries = 0;
        while (false === $this->redis->set("ip:{$ip}_lock", $lockValue, ['nx', 'ex' => self::LOCK_TIMEOUT]) && $retries < self::LOCK_MAX_RETRIES) {
            usleep(self::LOCK_WAIT_TIME);
            ++$retries;
        }

        if (self::LOCK_MAX_RETRIES === $retries) {
            return new Response(429, body: 'Too Many Requests');
        }

        $response = $handler->handle($request);

        if (false === $this->evalUnlockingScript($ip, $lockValue)) {
            $this->loadUnlockingScript();
            $this->evalUnlockingScript($ip, $lockValue);
        }

        return $response;
    }

    private function evalUnlockingScript(string $ip, string $lockValue): bool
    {
        return (bool) $this->redis->evalSha(self::LOCK_RELEASE_SCRIPT_HASH, ["ip:{$ip}_lock", $lockValue], 1);
    }

    private function loadUnlockingScript(): void
    {
        $this->redis->script('load', self::LOCK_RELEASE_SCRIPT);
    }
}
