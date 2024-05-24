<?php

declare(strict_types=1);

namespace BBO\Faucet\Controller;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final class LandingPage implements RequestHandlerInterface
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->twig->render(new Response(), 'index.html.twig');
    }
}
