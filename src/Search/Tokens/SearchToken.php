<?php

namespace Lomkit\Access\Search\Tokens;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @implements Arrayable<string, string> */
final readonly class SearchToken implements Arrayable, JsonSerializable
{
    /** @internal */
    public function __construct(
        private string $token,
        private string $index,
        private DateTimeImmutable $expiresAt,
        private ?string $filter,
    ) {}

    public function token(): string
    {
        return $this->token;
    }

    public function index(): string
    {
        return $this->index;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function filter(): ?string
    {
        return $this->filter;
    }

    /** @return array{token: string, index: string, expires_at: string} */
    #[\Override]
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'index' => $this->index,
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
        ];
    }

    /** @return array{token: string, index: string, expires_at: string} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
