<?php

declare(strict_types=1);

namespace BBO\Faucet\Middleware;

use BBO\Faucet\SessionData;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final readonly class Limits implements MiddlewareInterface
{
    private Twig $twig;
    private ?float $maxUserBtc;
    private ?float $maxGlobalBtc;

    public function __construct(Twig $twig, ?float $maxUserBtc, ?float $maxGlobalBtc)
    {
        $this->twig = $twig;
        $this->maxUserBtc = $maxUserBtc;
        $this->maxGlobalBtc = $maxGlobalBtc;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $form = $request->getParsedBody();
        if (empty($form['amount']) || !is_numeric($form['amount']) || ($amount = (float) $form['amount']) < 0.0) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Invalid amount']]);
        }

        /** @var SessionData $globalSession */
        $globalSession = $request->getAttribute(RedisSession::GLOBAL_SESSION_ATTR);

        /** @var SessionData $userSession */
        $userSession = $request->getAttribute(RedisSession::USER_SESSION_ATTR);

        if ((null !== $this->maxGlobalBtc && $globalSession->btc + $amount > $this->maxGlobalBtc)
            || (null !== $this->maxUserBtc && $userSession->btc + $amount > $this->maxUserBtc)) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Too much collected. Try again later.']]);
        }

        $response = $handler->handle($request);

        if ($response->hasHeader('X-Success')) {
            $userSession->btc += $amount;
            $globalSession->btc += $amount;
        }

        return $response;
    }
}
