<?php

namespace Lomkit\Access\Relations;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\RelationInspectionQueryException;

/** @internal */
final class InertConnection extends Connection
{
    private const string DRIVER = 'inert';

    /** @param  class-string<Model>  $model */
    public function __construct(
        private readonly string $model,
        private readonly string $relation,
        private readonly string $connection,
    ) {
        parent::__construct(
            fn (): never => throw RelationInspectionQueryException::for($model, $relation, $connection),
            config: ['name' => $connection, 'driver' => self::DRIVER],
        );
    }

    #[\Override]
    public function getSchemaBuilder(): never
    {
        throw RelationInspectionQueryException::for($this->model, $this->relation, $this->connection);
    }
}
