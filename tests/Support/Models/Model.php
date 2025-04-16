<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Laravel\Scout\Searchable;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Tests\Support\Database\Factories\ModelFactory;

class Model extends BaseModel
{
    use HasFactory;
    use HasControl;
    use Searchable;

    /**
     * Creates a new instance of the model's factory.
     *
     * @return ModelFactory The factory instance for this model.
     */
    protected static function newFactory()
    {
        return new ModelFactory();
    }

    protected $fillable = [
        'id',
    ];
}
