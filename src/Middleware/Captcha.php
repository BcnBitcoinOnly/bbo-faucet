<?php

declare(strict_types=1);

namespace BBO\Faucet\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final readonly class Captcha implements MiddlewareInterface
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $form = $request->getParsedBody();
        /** @var ?string $phrase */
        $phrase = $request->getAttribute(RedisSession::SESSION_ATTR)->captcha;

        if (null === $phrase || !\is_array($form) || empty($form['captcha']) || $form['captcha'] !== $phrase) {
            return $this->twig->render(new Response(400), 'index.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Incorrect Captcha']]);
        }

        return $handler->handle($request);
    }
}
