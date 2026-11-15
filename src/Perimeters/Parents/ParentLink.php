<?php

namespace Lomkit\Access\Perimeters\Parents;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidParentRelationException;
use Lomkit\Access\Exceptions\MissingParentControlException;
use Lomkit\Access\Exceptions\ParentCycleException;

/** @internal */
final readonly class ParentLink
{
    private function __construct(
        private string $relation,
        private Control $parent,
        private string $foreignKey,
        private string $ownerKey,
    ) {}

    /**
     * @throws InvalidParentRelationException When the relation is not a belongsTo of the child model, or the inverse is not its hasMany.
     * @throws MissingParentControlException When no control is registered for the parent model.
     * @throws ParentCycleException When the parents of the parent lead back to the child.
     */
    public static function resolve(Control $child, string $relation, ?string $inverse): self
    {
        $model = $child->getModel();
        $belongsTo = self::belongsTo($child, new $model, $relation);
        $parent = $belongsTo->getRelated();

        if ($inverse !== null && ! self::isInverse($parent, $inverse, $belongsTo, $model)) {
            throw InvalidParentRelationException::inverse($child, $parent::class, $inverse, $relation, $model);
        }

        if (! Access::hasControl($parent)) {
            throw MissingParentControlException::for($child, $relation, $parent::class);
        }

        $control = Access::controlFor($parent);
        $control->declaration();

        return new self($relation, $control, $belongsTo->getForeignKeyName(), $belongsTo->getOwnerKeyName());
    }

    public function control(): Control
    {
        return $this->parent;
    }

    public function stepFor(Ability $parentAbility): ParentStep
    {
        return new ParentStep($this->relation, $this->foreignKey, $this->ownerKey, $this->parent, $parentAbility);
    }

    /** @param  Builder<covariant Model>  $query */
    public function constrain(Builder $query, AccessContext $parent): void
    {
        $query->whereHas($this->relation, function (Builder $parents) use ($parent): void {
            $this->parent->restrictFor($parents, $parent);
        });
    }

    public function admitsDraft(Model $draft, AccessContext $parent): bool
    {
        $belongsTo = $draft->{$this->relation}();

        if (! $belongsTo instanceof BelongsTo) {
            return false;
        }

        $key = $draft->getAttributes()[$belongsTo->getForeignKeyName()] ?? null;

        if (! is_int($key) && (! is_string($key) || $key === '')) {
            return false;
        }

        $row = $belongsTo->first();

        return $row instanceof Model && $this->parent->applies($parent->reader(), $parent->ability(), $row);
    }

    /** @return BelongsTo<Model, Model> */
    private static function belongsTo(Control $child, Model $model, string $relation): BelongsTo
    {
        if (! $model->isRelation($relation)) {
            throw InvalidParentRelationException::missing($child, $model::class, $relation);
        }

        $belongsTo = $model->{$relation}();

        if (! $belongsTo instanceof BelongsTo || $belongsTo instanceof MorphTo) {
            throw InvalidParentRelationException::unsupported($child, $model::class, $relation, get_debug_type($belongsTo));
        }

        return $belongsTo;
    }

    /** @param  BelongsTo<Model, Model>  $belongsTo */
    private static function isInverse(Model $parent, string $inverse, BelongsTo $belongsTo, string $model): bool
    {
        if (! $parent->isRelation($inverse)) {
            return false;
        }

        $hasMany = $parent->{$inverse}();

        return $hasMany instanceof HasMany
            && $hasMany->getRelated()::class === $model
            && $hasMany->getForeignKeyName() === $belongsTo->getForeignKeyName()
            && $hasMany->getLocalKeyName() === $belongsTo->getOwnerKeyName();
    }
}
