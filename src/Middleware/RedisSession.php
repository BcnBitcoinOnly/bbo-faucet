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
    public const string SESSION_ATTR = 'session_data';

    private \Redis $redis;
    private int $cooldownTime;

    public function __construct(\Redis $redis, int $cooldownTime)
    {
        $this->redis = $redis;
        $this->cooldownTime = $cooldownTime;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $request->getHeaderLine('X-Forwarded-For');

        $json = $this->redis->get("ip:$ip");
        $session = false === $json ? new SessionData(0.0, null) : SessionData::fromJson(json_decode($json));

        $response = $handler->handle($request->withAttribute(self::SESSION_ATTR, $session));

        $this->redis->set("ip:$ip", json_encode($session), false === $json ? ['ex' => $this->cooldownTime] : ['keepttl']);

        return $response;
    }
}
