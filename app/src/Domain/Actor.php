<?php

declare(strict_types=1);

namespace Dispatch\Domain;

final readonly class Actor
{
    private function __construct(public string $name)
    {
    }

    public static function owner(): self
    {
        return new self('owner');
    }

    public static function cli(): self
    {
        return new self('cli');
    }

    public static function deploy(): self
    {
        return new self('deploy');
    }

    public static function mcp(string $tokenName): self
    {
        return new self('mcp:' . $tokenName);
    }

    public static function oauth(string $clientName): self
    {
        return new self('oauth:' . $clientName);
    }

    public function isOwner(): bool
    {
        return $this->name === 'owner';
    }
}
