<?php

declare(strict_types=1);

namespace BBO\Faucet\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final readonly class UsageLimits implements MiddlewareInterface
{
    private Twig $twig;
    private \Redis $redis;
    private int $userTTL;
    private int $globalTTL;
    private ?float $maxUserBtc;
    private ?float $maxGlobalBtc;

    public function __construct(Twig $twig, \Redis $redis, int $userTTL, int $globalTTL, ?float $maxUserBtc, ?float $maxGlobalBtc)
    {
        $this->twig = $twig;
        $this->redis = $redis;
        $this->userTTL = $userTTL;
        $this->globalTTL = $globalTTL;
        $this->maxUserBtc = $maxUserBtc;
        $this->maxGlobalBtc = $maxGlobalBtc;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $form = $request->getParsedBody();
        if (empty($form['amount']) || !is_numeric($form['amount']) || ($amount = (float) $form['amount']) < 0.0) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Invalid amount']]);
        }

        $ip = $request->getHeaderLine('X-Forwarded-For');

        $user = $this->redis->get("limits:$ip");
        $user = false === $user ? 0.0 : (float) $user;

        $global = $this->redis->get('limits:global');
        $global = false === $global ? 0.0 : (float) $global;

        if ((null !== $this->maxGlobalBtc && $global + $amount > $this->maxGlobalBtc)
            || (null !== $this->maxUserBtc && $user + $amount > $this->maxUserBtc)) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Too much collected. Try again later.']]);
        }

        $response = $handler->handle($request);

        if ($response->hasHeader('X-Success') && null !== $this->maxUserBtc) {
            $this->redis->set("limits:$ip", $user + $amount, 0.0 === $user ? ['ex' => $this->userTTL] : ['keepttl']);
        }

        if ($response->hasHeader('X-Success') && null !== $this->maxGlobalBtc) {
            $this->redis->set('limits:global', $global + $amount, 0.0 === $global ? ['ex' => $this->globalTTL] : ['keepttl']);
        }

        return $response;
    }
}
