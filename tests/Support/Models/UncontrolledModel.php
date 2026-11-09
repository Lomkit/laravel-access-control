<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Lomkit\Access\Controls\HasControl;

class UncontrolledModel extends BaseModel
{
    use HasControl;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'models';
}
