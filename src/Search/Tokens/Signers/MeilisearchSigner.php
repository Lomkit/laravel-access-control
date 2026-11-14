<?php

namespace Lomkit\Access\Search\Tokens\Signers;

use DateTimeImmutable;
use Laravel\Scout\Engines\MeilisearchEngine;
use Lomkit\Access\Exceptions\InvalidSearchTokenKeyException;
use Lomkit\Access\Exceptions\MissingSearchClientException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Search\Tokens\Syntax\FilterSyntax;
use Lomkit\Access\Search\Tokens\Syntax\MeilisearchSyntax;
use Meilisearch\Client;
use stdClass;

/** @internal */
final readonly class MeilisearchSigner implements SearchTokenSigner
{
    private const string PACKAGE = 'meilisearch/meilisearch-php';

    public function __construct(private MeilisearchEngine $meilisearch) {}

    #[\Override]
    public function engine(): SearchTokenEngine
    {
        return SearchTokenEngine::Meilisearch;
    }

    #[\Override]
    public function syntax(): FilterSyntax
    {
        return new MeilisearchSyntax;
    }

    /** @throws MissingSearchClientException When the Meilisearch client is not installed. */
    #[\Override]
    public function sign(SearchTokenKey $key, string $index, ?string $filter, DateTimeImmutable $expiresAt): string
    {
        if (! class_exists(Client::class)) {
            throw MissingSearchClientException::for($this->engine(), self::PACKAGE);
        }

        $token = $this->meilisearch->__call('generateTenantToken', [
            $key->uid() ?? throw InvalidSearchTokenKeyException::blank($this->engine(), 'uid'),
            [$index => $filter === null ? new stdClass : ['filter' => $filter]],
            ['apiKey' => $key->key(), 'expiresAt' => $expiresAt],
        ]);

        return is_string($token) ? $token : throw MissingSearchClientException::for($this->engine(), self::PACKAGE);
    }
}
