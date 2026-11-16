<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class TitleRule
{
    public function __construct(
        public mixed $title,
        public Closure $constrain,
        public ?Model $owner = null,
    ) {}

    public static function exactly(mixed $title, ?Model $owner = null): self
    {
        return new self($title, function (Builder $query, mixed $title): void {
            $query->where('title', $title);
        }, $owner);
    }

    public static function startingWith(mixed $title, ?Model $owner = null): self
    {
        return new self($title, function (Builder $query, mixed $title): void {
            $query->where('title', 'like', "{$title}%");
        }, $owner);
    }

    public function apply(Builder $query): void
    {
        ($this->constrain)($query, $this->title);
    }
}
