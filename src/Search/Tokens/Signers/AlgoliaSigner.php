<?php

namespace Lomkit\Access\Search\Tokens\Signers;

use DateTimeImmutable;
use Lomkit\Access\Search\AlgoliaRules;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Search\Tokens\Syntax\AlgoliaSyntax;
use Lomkit\Access\Search\Tokens\Syntax\FilterSyntax;

/** @internal */
final readonly class AlgoliaSigner implements SearchTokenSigner
{
    #[\Override]
    public function engine(): SearchTokenEngine
    {
        return SearchTokenEngine::Algolia;
    }

    #[\Override]
    public function syntax(): FilterSyntax
    {
        return new AlgoliaSyntax;
    }

    #[\Override]
    public function sign(SearchTokenKey $key, string $index, ?string $filter, DateTimeImmutable $expiresAt): string
    {
        $restrictions = ['restrictIndices' => $index, 'validUntil' => $expiresAt->getTimestamp()];

        if ($filter !== null) {
            $restrictions['filters'] = $filter;
            $restrictions[AlgoliaRules::PARAMETER] = 'false';
            $restrictions[AlgoliaRules::AB_TESTS] = 'false';
        }

        ksort($restrictions);
        $query = http_build_query($restrictions, encoding_type: PHP_QUERY_RFC3986);

        return base64_encode(hash_hmac('sha256', $query, $key->key()).$query);
    }
}
