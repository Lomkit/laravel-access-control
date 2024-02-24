<?php

namespace Lomkit\Access\Tests\Support\Models;

use Lomkit\Access\Tests\Support\Access\Controls\NotImplementedQueryControl;
use Lomkit\Access\Tests\Support\Models\Model as BaseModel;

class NotImplementedQueryModel extends BaseModel
{
    public function getControl(): string
    {
        return NotImplementedQueryControl::class;
    }
}
