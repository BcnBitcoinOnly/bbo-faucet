<?php

declare(strict_types=1);

namespace BBO\Faucet\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final readonly class CheckCaptcha implements MiddlewareInterface
{
    private Twig $twig;
    private \Redis $redis;

    public function __construct(Twig $twig, \Redis $redis)
    {
        $this->twig = $twig;
        $this->redis = $redis;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $form = $request->getParsedBody();

        if (!\is_array($form) || empty($form['captcha']) || !preg_match('/^[0-9a-zA-Z]+$/', $form['captcha']) || '1' !== $this->redis->get("captcha:{$form['captcha']}")) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Incorrect Captcha']]);
        }

        $this->redis->del("captcha:{$form['captcha']}");

        return $handler->handle($request);
    }
}
