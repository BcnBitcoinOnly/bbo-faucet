<?php

declare(strict_types=1);

namespace BBO\Faucet\Middleware;

use BBO\Faucet\SessionData;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RedisSession implements MiddlewareInterface
{
    public const string USER_SESSION_ATTR = 'user_session';
    public const string GLOBAL_SESSION_ATTR = 'global_session';

    private \Redis $redis;
    private int $userTTL;
    private int $globalTTL;

    public function __construct(\Redis $redis, int $userTTL, int $globalTTL)
    {
        $this->redis = $redis;
        $this->userTTL = $userTTL;
        $this->globalTTL = $globalTTL;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $request->getHeaderLine('X-Forwarded-For');

        $userJson = $this->redis->get("session:$ip");
        $userSession = false === $userJson ? new SessionData(0.0, null) : SessionData::fromJson(json_decode($userJson));

        $globalJson = $this->redis->get('session:global');
        $globalSession = false === $globalJson ? new SessionData(0.0, null) : SessionData::fromJson(json_decode($globalJson));

        $response = $handler->handle(
            $request
                ->withAttribute(self::USER_SESSION_ATTR, $userSession)
                ->withAttribute(self::GLOBAL_SESSION_ATTR, $globalSession)
        );

        $this->redis->set("session:$ip", json_encode($userSession), false === $userJson ? ['ex' => $this->userTTL] : ['keepttl']);
        $this->redis->set('session:global', json_encode($globalSession), false === $globalJson ? ['ex' => $this->globalTTL] : ['keepttl']);

        return $response;
    }
}
