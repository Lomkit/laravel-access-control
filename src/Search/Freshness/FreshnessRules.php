<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\UnsearchableThroughParentException;
use Lomkit\Access\Search\Parents\ChildrenReindex;
use Lomkit\Access\Search\Parents\ParentSearch;
use Lomkit\Access\Search\SearchKeys;

/** @internal */
final readonly class FreshnessRules
{
    /**
     * @param  list<ReindexWhen>  $declared
     * @param  array<string, list<ReindexRule>>  $dependencies
     * @param  array<string, list<ReindexRule>>  $watched
     */
    private function __construct(
        private array $declared,
        private array $dependencies,
        private array $watched,
    ) {}

    /**
     * @param  class-string  $control
     * @param  array<string, SearchKeys>  $keys
     * @param  list<ParentSearch>  $parents
     *
     * @throws UnsearchableThroughParentException When the model is searchable and a ThroughParent cannot be searched exactly.
     */
    public static function of(string $control, array $keys, array $parents, bool $indexed): self
    {
        $declared = array_filter(array_map(fn (SearchKeys $declared): array => $declared->reindexes()->all(), $keys));
        $dependencies = $declared;

        foreach ($parents as $parent) {
            if ($indexed) {
                $parent->ensureSearchable($control);
            }

            $dependencies = [...$dependencies, ...$parent->freshness()];
        }

        return new self(array_merge(...array_values($declared)), $dependencies, $indexed ? $dependencies : []);
    }

    /** @return list<ReindexWhen> */
    public function declared(): array
    {
        return $this->declared;
    }

    /** @return array<string, list<ReindexRule>> */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    /** @return list<class-string<Model>> */
    public function watchedModels(): array
    {
        $models = [];

        foreach ($this->watched as $rules) {
            array_push($models, ...array_map(fn (ReindexRule $rule): string => $rule->model(), $rules));
        }

        return array_values(array_unique($models));
    }

    /** @param  class-string<Model>  $parent */
    public function inheritsReindexOf(string $parent): bool
    {
        foreach ($this->watched as $rules) {
            foreach ($rules as $rule) {
                if ($rule instanceof ChildrenReindex && $rule->inheritsReindexOf($parent)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function for(string $name, string $watched): ?ReindexRule
    {
        foreach ($this->watched[$name] ?? [] as $rule) {
            if ($rule->model() === $watched) {
                return $rule;
            }
        }

        return null;
    }

    /** @return array<string, ReindexRule> */
    public function watching(Model $instance): array
    {
        $reindexes = [];

        foreach ($this->watched as $name => $rules) {
            foreach ($rules as $rule) {
                if ($rule->watches($instance)) {
                    $reindexes[$name] = $rule;
                }
            }
        }

        return $reindexes;
    }
}
