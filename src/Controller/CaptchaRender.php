<?php

declare(strict_types=1);

namespace BBO\Faucet\Controller;

use BBO\Faucet\Middleware\RedisSession;
use Gregwar\Captcha\CaptchaBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CaptchaRender implements RequestHandlerInterface
{
    private CaptchaBuilder $captcha;

    public function __construct(CaptchaBuilder $captcha)
    {
        $this->captcha = $captcha;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sessionData = $request->getAttribute(RedisSession::USER_SESSION_ATTR);

        $sessionData->captcha = $this->captcha->getPhrase();

        ob_start();
        $this->captcha->build()->output();
        $jpegData = ob_get_clean();

        return new Response(200, ['Content-Type' => 'image/jpeg'], $jpegData);
    }
}
