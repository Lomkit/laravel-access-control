<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Laravel\Scout\Builder;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Profiles\EngineOperators;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\PlainLiterals;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

class MemoryEngineProfile implements EngineProfile
{
    use PlainLiterals;

    public function __construct(protected readonly MemoryEngine $engine) {}

    #[\Override]
    public function name(): string
    {
        return $this->engine::class;
    }

    #[\Override]
    public function operators(Field $attribute): array
    {
        return EngineOperators::for($attribute);
    }

    #[\Override]
    public function sortKeys(): int
    {
        return 0;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return [];
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return true;
    }

    #[\Override]
    public function maxPageSize(): ?int
    {
        return null;
    }

    #[\Override]
    public function window(): ?int
    {
        return null;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void {}

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return EnginePage::empty();
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        return null;
    }
}
