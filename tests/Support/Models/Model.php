<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Tests\Support\Database\Factories\ModelFactory;

class Model extends BaseModel
{
    use HasFactory;
    use HasControl;

    protected static function newFactory()
    {
        return new ModelFactory();
    }

    protected $fillable = [
        'id',
    ];
}
