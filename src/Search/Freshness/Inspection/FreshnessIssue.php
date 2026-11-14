<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;

/** @internal */
interface FreshnessIssue
{
    /** @return class-string<Model> */
    public function model(): string;

    public function message(): string;
}
