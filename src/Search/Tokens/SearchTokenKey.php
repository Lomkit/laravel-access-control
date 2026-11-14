<?php

namespace Lomkit\Access\Search\Tokens;

use Lomkit\Access\Exceptions\InvalidSearchTokenKeyException;

final readonly class SearchTokenKey
{
    private function __construct(
        private SearchTokenEngine $engine,
        #[\SensitiveParameter] private string $key,
        private ?string $uid,
    ) {
        if (trim($key) === '') {
            throw InvalidSearchTokenKeyException::blank($engine, 'key');
        }

        if ($uid !== null && trim($uid) === '') {
            throw InvalidSearchTokenKeyException::blank($engine, 'uid');
        }
    }

    /** @throws InvalidSearchTokenKeyException When the uid or the key is blank. */
    public static function meilisearch(string $uid, #[\SensitiveParameter] string $key): self
    {
        return new self(SearchTokenEngine::Meilisearch, $key, $uid);
    }

    /** @throws InvalidSearchTokenKeyException When the key is blank. */
    public static function typesense(#[\SensitiveParameter] string $key): self
    {
        return new self(SearchTokenEngine::Typesense, $key, null);
    }

    /** @throws InvalidSearchTokenKeyException When the key is blank. */
    public static function algolia(#[\SensitiveParameter] string $key): self
    {
        return new self(SearchTokenEngine::Algolia, $key, null);
    }

    /** @internal */
    public function engine(): SearchTokenEngine
    {
        return $this->engine;
    }

    /** @internal */
    public function key(): string
    {
        return $this->key;
    }

    /** @internal */
    public function uid(): ?string
    {
        return $this->uid;
    }

    /** @return array{engine: string, uid: string|null} */
    public function __debugInfo(): array
    {
        return ['engine' => $this->engine->label(), 'uid' => $this->uid];
    }
}
