<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\AbilityColumns;
use Lomkit\Access\Decisions\DecisionMemo;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\MissingControlledReaderException;

trait HasControl
{
    public static function bootHasControl(): void
    {
        static::addGlobalScope(new HasControlScope);
    }

    /**
     * @throws MissingControlException When no control is registered for the model.
     */
    public function newControl(): Control
    {
        return Access::controlFor(static::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function controlled(Builder $query): void
    {
        $reader = Auth::user();

        $this->controlledFor($query, $reader instanceof Model ? $reader : null);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function controlledFor(Builder $query, ?Model $reader): void
    {
        $control = $this->newControl();

        $query->withoutGlobalScope(HasControlScope::class);

        $control->restrict($query, $reader);

        Container::getInstance()
            ->make(ControlledReaders::class)
            ->remember($query, $reader);
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<Ability|string>  $abilities
     *
     * @throws MissingControlledReaderException When neither controlled() nor controlledFor() was called before.
     * @throws InvalidAbilityException When the control cannot map an ability.
     */
    #[Scope]
    protected function withAbilities(Builder $query, array $abilities): void
    {
        $controlled = Container::getInstance()
            ->make(ControlledReaders::class)
            ->of($query) ?? throw MissingControlledReaderException::for($this);
        $reader = $controlled->reader();

        if ($reader === null) {
            return;
        }

        $control = $this->newControl();
        $columns = new AbilityColumns(
            $control,
            $reader,
            array_map(fn (Ability|string $ability): Ability => $ability instanceof Ability ? $ability : $control->abilityFor($ability), array_values($abilities)),
            Container::getInstance()->make(DecisionMemo::class),
        );

        $query
            ->withGlobalScope(AbilityColumns::class, $columns)
            ->afterQuery($columns->collect(...));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function uncontrolled(Builder $query): void
    {
        $query->withoutGlobalScope(HasControlScope::class);
    }
}
