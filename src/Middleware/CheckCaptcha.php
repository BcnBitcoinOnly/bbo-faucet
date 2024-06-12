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
    /**
     * Match an ASCII string of 5 bytes and (roughly) this charset:
     *  abcdefghijklmnpqrstuvwxyz123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ
     *
     * @see \Gregwar\Captcha\PhraseBuilder
     */
    private const string CAPTCHA_REGEX = '/^[1-9a-zA-Z]{5}$/';

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

        if (!\is_array($form)
            || empty($form['captcha'])
            || !preg_match(self::CAPTCHA_REGEX, $form['captcha'])
            || '1' !== $this->redis->get("captcha:{$form['captcha']}")
        ) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Incorrect Captcha']]);
        }

        $this->redis->del("captcha:{$form['captcha']}");

        return $handler->handle($request);
    }
}
