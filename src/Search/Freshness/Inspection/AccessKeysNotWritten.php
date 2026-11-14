<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class AccessKeysNotWritten implements FreshnessIssue
{
    /** @param  class-string<Model>  $model */
    public function __construct(private string $model) {}

    #[\Override]
    public function model(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'The [%s] model overrides makeSearchableUsing() and its body never calls withAccessKeys(), so its documents carry no access keys and no controlled search finds them. Return $this->withAccessKeys($models) from it.',
            $this->model,
        );
    }
}
