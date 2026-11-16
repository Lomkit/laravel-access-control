<?php

namespace Lomkit\Access\Relations;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\RelationInspectionQueryException;

/** @internal */
final class InertConnectionResolver implements ConnectionResolverInterface
{
    private const string DRIVER = 'inert';

    public function __construct(
        private readonly Model $model,
        private readonly string $relation,
        private string $default,
    ) {}

    #[\Override]
    public function connection($name = null): Connection
    {
        $name = is_string($name) && $name !== '' ? $name : $this->default;

        return new Connection(
            fn (): never => throw RelationInspectionQueryException::for($this->model::class, $this->relation, $name),
            config: ['name' => $name, 'driver' => self::DRIVER],
        );
    }

    #[\Override]
    public function getDefaultConnection(): string
    {
        return $this->default;
    }

    #[\Override]
    public function setDefaultConnection($name): void
    {
        $this->default = $name;
    }
}
