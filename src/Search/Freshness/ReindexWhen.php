<?php

namespace Lomkit\Access\Search\Freshness;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Access\Exceptions\InvalidReindexAttributesException;
use Lomkit\Access\Exceptions\InvalidReindexModelException;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;
use Lomkit\Access\Perimeters\ClosureShape;
use Lomkit\Access\Perimeters\ParameterType;
use Lomkit\Access\Perimeters\Perimeter;
use ReflectionFunction;

/** @internal */
final readonly class ReindexWhen implements ReindexRule
{
    /**
     * @param  class-string<Model>  $model
     * @param  non-empty-list<string>  $changed
     */
    private function __construct(
        private string $model,
        private array $changed,
        private Closure $rows,
    ) {}

    /**
     * @internal
     *
     * @param  list<string>  $changed
     *
     * @throws InvalidReindexModelException When the model is not an Eloquent model class.
     * @throws InvalidReindexAttributesException When changed names no attribute, or a blank one.
     * @throws InvalidReindexRowsException When the rows callback does not accept the model.
     */
    public static function declaredBy(Perimeter $perimeter, string $model, array $changed, Closure $rows): self
    {
        if (! is_subclass_of($model, Model::class)) {
            throw InvalidReindexModelException::for($perimeter, $model);
        }

        $attributes = array_values(array_unique($changed));

        if ($attributes === []) {
            throw InvalidReindexAttributesException::empty($perimeter, $model);
        }

        foreach ($attributes as $attribute) {
            if (trim($attribute) === '') {
                throw InvalidReindexAttributesException::blank($perimeter, $model);
            }
        }

        if (! ParameterType::at(new ReflectionFunction($rows), 0)->admits($model)) {
            throw InvalidReindexRowsException::parameter($perimeter, $model);
        }

        return new self($model, $attributes, $rows);
    }

    /**
     * @internal
     *
     * @return class-string<Model>
     */
    #[\Override]
    public function model(): string
    {
        return $this->model;
    }

    /**
     * @internal
     *
     * @return non-empty-list<string>
     */
    public function changed(): array
    {
        return $this->changed;
    }

    /** @internal */
    #[\Override]
    public function watches(Model $instance): bool
    {
        return $instance instanceof $this->model;
    }

    /** @internal */
    #[\Override]
    public function isTriggeredBy(WatchedEvent $event, Model $instance): bool
    {
        return $event !== WatchedEvent::Updated || $instance->wasChanged($this->changed);
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function previousValues(Model $instance): array
    {
        return array_intersect_key($instance->getPrevious(), array_flip($this->changed));
    }

    /**
     * @internal
     *
     * @param  class-string<Model>  $controlled
     * @return Builder<Model>
     *
     * @throws InvalidReindexRowsException When the callback returns no query or relation of the controlled model.
     */
    #[\Override]
    public function rowsFor(Model $instance, string $keys, string $controlled): Builder
    {
        $rows = ($this->rows)($instance);
        $query = $rows instanceof Relation ? $rows->getQuery() : $rows;

        if (! $query instanceof Builder) {
            throw InvalidReindexRowsException::returned($keys, $this->model, get_debug_type($rows));
        }

        if (! $query->getModel() instanceof $controlled) {
            throw InvalidReindexRowsException::anotherModel($keys, $controlled, $query->getModel()::class);
        }

        return $query;
    }

    /** @internal */
    public function matches(self $other): bool
    {
        return $this->model === $other->model
            && $this->changed === $other->changed
            && ClosureShape::of($this->rows)->matches(ClosureShape::of($other->rows));
    }
}
