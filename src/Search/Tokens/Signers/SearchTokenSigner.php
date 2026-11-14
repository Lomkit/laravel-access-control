<?php

namespace Lomkit\Access\Search\Tokens\Signers;

use DateTimeImmutable;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Search\Tokens\Syntax\FilterSyntax;

/** @internal */
interface SearchTokenSigner
{
    public function engine(): SearchTokenEngine;

    public function syntax(): FilterSyntax;

    public function sign(SearchTokenKey $key, string $index, ?string $filter, DateTimeImmutable $expiresAt): string;
}
