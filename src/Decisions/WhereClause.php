<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class WhereClause
{
    private const string ALWAYS = '1 = 1';

    /** @param  list<mixed>  $bindings */
    private function __construct(
        private string $sql,
        private array $bindings,
    ) {}

    /** @param  Builder<covariant Model>  $query */
    public static function of(Builder $query): self
    {
        $base = $query->getQuery();
        $wheres = (string) preg_replace('/^where /', '', $base->getGrammar()->compileWheres($base));

        return new self($wheres === '' ? self::ALWAYS : $wheres, $base->getRawBindings()['where']);
    }

    public function sql(): string
    {
        return "({$this->sql})";
    }

    /** @return list<mixed> */
    public function bindings(): array
    {
        return $this->bindings;
    }
}
