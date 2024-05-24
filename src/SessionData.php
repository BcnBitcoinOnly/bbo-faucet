<?php

declare(strict_types=1);

namespace BBO\Faucet;

final class SessionData implements \JsonSerializable
{
    public float $btc;

    public function __construct(float $btc)
    {
        $this->btc = $btc;
    }

    public function jsonSerialize(): array
    {
        return [
            'btc' => $this->btc,
        ];
    }

    public static function fromJson(\stdClass $session): self
    {
        return new self($session->btc);
    }
}
