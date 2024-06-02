<?php

declare(strict_types=1);

namespace BBO\Faucet\Controller;

use BBO\Faucet\Bitcoin\RPCClient;
use BBO\Faucet\Middleware\RedisSession;
use BBO\Faucet\SessionData;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final readonly class FormProcessing implements RequestHandlerInterface
{
    private Twig $twig;
    private RPCClient $rpc;
    private float $cooldownMaxBtc;
    private ?string $mempoolUrl;

    public function __construct(Twig $twig, RPCClient $rpc, float $cooldownMaxBtc, ?string $mempoolUrl)
    {
        $this->twig = $twig;
        $this->rpc = $rpc;
        $this->cooldownMaxBtc = $cooldownMaxBtc;
        $this->mempoolUrl = $mempoolUrl;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $form = $request->getParsedBody();
        if (!\is_array($form) || empty($form['address']) || !$this->rpc->validateAddress($form['address'])) {
            return $this->twig->render(new Response(400), 'index.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Invalid address']]);
        }

        if (empty($form['amount']) || !is_numeric($form['amount'])) {
            return $this->twig->render(new Response(400), 'index.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Invalid amount']]);
        }

        /** @var SessionData $sessionData */
        $sessionData = $request->getAttribute(RedisSession::SESSION_ATTR);
        $amount = (float) $form['amount'];
        if ($sessionData->btc + $amount > $this->cooldownMaxBtc) {
            return $this->twig->render(new Response(429), 'index.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Too much collected, GFY']]);
        }

        $txId = $this->rpc->send($form['address'], $amount);

        $sessionData->btc += $amount;

        $message = null === $this->mempoolUrl ?
            "Transaction sent: $txId" :
            "Transaction sent: <a href=\"{$this->mempoolUrl}/tx/{$txId}\">$txId</a>";

        return $this->twig->render(new Response(200), 'index.html.twig', ['notification' => ['class' => 'is-success', 'message' => $message]]);
    }
}
