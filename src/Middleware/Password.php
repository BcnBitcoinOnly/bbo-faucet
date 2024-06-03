<?php

declare(strict_types=1);

namespace BBO\Faucet\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final readonly class Password implements MiddlewareInterface
{
    private Twig $twig;
    private string $bcryptHash;

    public function __construct(Twig $twig, string $bcryptHash)
    {
        $this->twig = $twig;
        $this->bcryptHash = $bcryptHash;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $form = $request->getParsedBody();
        if (!\is_array($form) || empty($form['password']) || !password_verify($form['password'], $this->bcryptHash)) {
            return $this->twig->render(new Response(400), 'index.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Incorrect Password']]);
        }

        return $handler->handle($request);
    }
}
