<?php

declare(strict_types=1);

namespace BBO\Faucet\Controller;

use BBO\Faucet\Bitcoin\Batcher;
use BBO\Faucet\Bitcoin\RPCClient;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final readonly class FormProcessing implements RequestHandlerInterface
{
    private Twig $twig;
    private RPCClient $rpc;
    private float $minBtc;
    private float $maxBtc;
    private ?Batcher $batcher;
    private ?string $mempoolUrl;

    public function __construct(Twig $twig, RPCClient $rpc, float $minBtc, float $maxBtc, ?Batcher $batcher, ?string $mempoolUrl)
    {
        $this->twig = $twig;
        $this->rpc = $rpc;
        $this->minBtc = $minBtc;
        $this->maxBtc = $maxBtc;
        $this->batcher = $batcher;
        $this->mempoolUrl = $mempoolUrl;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $form = $request->getParsedBody();
        if (!\is_array($form) || empty($form['address']) || !$this->rpc->validateAddress($address = trim($form['address']))) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Invalid address']]);
        }

        if (empty($form['amount']) || !is_numeric($form['amount']) || ($amount = (float) $form['amount']) < $this->minBtc || $amount > $this->maxBtc) {
            return $this->twig->render(new Response(), 'form.html.twig', ['notification' => ['class' => 'is-danger', 'message' => 'Invalid amount']]);
        }

        $message = 'Your transaction will be sent in a few minutes.';
        if ($this->batcher instanceof Batcher) {
            $this->batcher->batch($address, $amount);
        } else {
            $txId = $this->rpc->send($address, $amount);
            $message = null === $this->mempoolUrl ?
                "Transaction sent: $txId" :
                "Transaction sent: <a href=\"{$this->mempoolUrl}/tx/{$txId}\" target='_blank'>$txId</a>";
        }

        return $this->twig->render(new Response(headers: ['X-Success' => '1']), 'form.html.twig', ['notification' => ['class' => 'is-success', 'message' => $message]]);
    }
}
