<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Lomkit\Access\Tests\Support\Access\Controls\NotImplementedQueryControl;
use Lomkit\Access\Tests\Support\Models\Model as BaseModel;
use Lomkit\Access\QueriesControlled;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Access\Controls\ModelControl;
use Lomkit\Access\Tests\Support\Database\Factories\ModelFactory;

class NotImplementedQueryModel extends BaseModel
{
    public function getControl():string
    {
        return NotImplementedQueryControl::class;
    }
}
