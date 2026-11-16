<?php

namespace Lomkit\Access\Relations;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

use function Illuminate\Support\enum_value;

/** @internal */
final class InertConnectionResolver implements ConnectionResolverInterface
{
    public function __construct(
        private readonly Model $model,
        private readonly string $relation,
        private string $default,
    ) {}

    #[\Override]
    public function connection($name = null): InertConnection
    {
        $name = enum_value($name);

        return new InertConnection($this->model::class, $this->relation, is_string($name) && $name !== '' ? $name : $this->default);
    }

    #[\Override]
    public function getDefaultConnection(): string
    {
        return $this->default;
    }

    #[\Override]
    public function setDefaultConnection($name): void
    {
        $name = enum_value($name);
        $this->default = is_string($name) ? $name : $this->default;
    }
}
