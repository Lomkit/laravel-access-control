<?php

namespace Lomkit\Access\Search\Tokens\Signers;

use DateTimeImmutable;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Exceptions\MissingSearchClientException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Search\Tokens\Syntax\FilterSyntax;
use Lomkit\Access\Search\Tokens\Syntax\TypesenseSyntax;
use Typesense\Client;

/** @internal */
final readonly class TypesenseSigner implements SearchTokenSigner
{
    private const string PACKAGE = 'typesense/typesense-php';

    public function __construct(private TypesenseEngine $typesense) {}

    #[\Override]
    public function engine(): SearchTokenEngine
    {
        return SearchTokenEngine::Typesense;
    }

    #[\Override]
    public function syntax(): FilterSyntax
    {
        return new TypesenseSyntax;
    }

    /** @throws MissingSearchClientException When the Typesense client is not installed. */
    #[\Override]
    public function sign(SearchTokenKey $key, string $index, ?string $filter, DateTimeImmutable $expiresAt): string
    {
        $keys = class_exists(Client::class) ? $this->typesense->__call('getKeys', []) : null;

        if (! is_object($keys) || ! method_exists($keys, 'generateScopedSearchKey')) {
            throw MissingSearchClientException::for($this->engine(), self::PACKAGE);
        }

        $parameters = $filter === null ? [] : ['filter_by' => $filter];
        $token = $keys->generateScopedSearchKey($key->key(), [...$parameters, 'expires_at' => $expiresAt->getTimestamp()]);

        return is_string($token) ? $token : throw MissingSearchClientException::for($this->engine(), self::PACKAGE);
    }
}
