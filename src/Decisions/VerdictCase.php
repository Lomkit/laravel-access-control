<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class VerdictCase
{
    private const string ALIAS = 'access_verdict';

    /** @param  list<mixed>  $bindings */
    private function __construct(
        private string $sql,
        private array $bindings,
        private Verdict $failed,
    ) {}

    public static function rows(WhereClause $rows): self
    {
        return new self(
            sprintf("case when %s then '%s' else '%s' end", $rows->sql(), Verdict::Granted->value, Verdict::OutsideRows->value),
            $rows->bindings(),
            Verdict::OutsideRows,
        );
    }

    public static function bounded(WhereClause $boundary, self|Verdict $inside): self
    {
        $then = $inside instanceof self ? $inside->sql : "'{$inside->value}'";

        return new self(
            sprintf("case when %s then %s else '%s' end", $boundary->sql(), $then, Verdict::OutsideBoundary->value),
            [...$boundary->bindings(), ...($inside instanceof self ? $inside->bindings : [])],
            Verdict::OutsideBoundary,
        );
    }

    /** @param  Builder<covariant Model>  $query */
    public function select(Builder $query, string $alias): void
    {
        $query
            ->addSelect(new VerdictColumn($this->sql, $alias))
            ->addBinding($this->bindings, 'select');
    }

    public function verdictOf(Model $row): Verdict
    {
        $query = $row->newQueryWithoutScopes()->whereKey($row->getKey());

        $this->select($query, self::ALIAS);

        return $this->verdictFrom($query->toBase()->value(self::ALIAS));
    }

    public function verdictFrom(mixed $code): Verdict
    {
        return is_string($code) ? Verdict::tryFrom($code) ?? $this->failed : $this->failed;
    }
}
