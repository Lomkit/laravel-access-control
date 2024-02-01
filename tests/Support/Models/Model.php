<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Lomkit\Access\QueriesControlled;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Access\Controls\ModelControl;
use Lomkit\Access\Tests\Support\Database\Factories\ModelFactory;

class Model extends BaseModel
{
    use HasFactory, QueriesControlled;

    /**
     * Return the control instance string
     *
     * @return class-string<Control>
     */
    public function getControl():string
    {
        return ModelControl::class;
    }

    protected static function newFactory()
    {
        return new ModelFactory;
    }

    protected $fillable = [
        'id',
    ];

    protected $casts = [
        'is_client' => 'bool',
        'is_site' => 'bool',
        'is_own' => 'bool',
    ];
}
