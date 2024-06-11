<?php

declare(strict_types=1);

namespace BBO\Faucet\Controller;

use Gregwar\Captcha\CaptchaBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CaptchaImage implements RequestHandlerInterface
{
    private const int CAPTCHA_TTL = 900;

    private CaptchaBuilder $captcha;
    private \Redis $redis;

    public function __construct(CaptchaBuilder $captcha, \Redis $redis)
    {
        $this->captcha = $captcha;
        $this->redis = $redis;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->redis->set('captcha:'.$this->captcha->getPhrase(), 1, ['ex' => self::CAPTCHA_TTL]);

        ob_start();
        $this->captcha->build()->output();
        $jpegData = ob_get_clean();

        return new Response(200, ['Content-Type' => 'image/jpeg'], $jpegData);
    }
}
