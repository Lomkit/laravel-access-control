<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Lomkit\Access\Search\SearchKeys;

/** @internal */
interface KeyRelationIssue extends FreshnessIssue
{
    public function keys(): string;

    public function declaredKeys(): SearchKeys;
}
