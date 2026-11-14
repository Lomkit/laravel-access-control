<?php

namespace Lomkit\Access;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Decisions\DecisionContext;
use Lomkit\Access\Decisions\DecisionContextResolver;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;

final readonly class AccessContext
{
    private function __construct(
        private Model $reader,
        private Ability $ability,
        private DecisionContext $decision,
    ) {}

    /**
     * @internal
     *
     * @throws InvalidDecisionContextException When the decision context resolver returns an invalid context.
     */
    public static function of(Model $reader, Ability $ability): self
    {
        $decision = Container::getInstance()
            ->make(DecisionContextResolver::class)
            ->current();

        return new self($reader, $ability, $decision);
    }

    public function reader(): Model
    {
        return $this->reader;
    }

    public function ability(): Ability
    {
        return $this->ability;
    }

    /** @return array<string, string|int|bool|null> */
    public function decisionContext(): array
    {
        return $this->decision->values();
    }
}
