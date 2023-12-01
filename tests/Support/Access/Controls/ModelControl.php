<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Queries\Query;
use Lomkit\Access\Tests\Support\Access\Queries\ModelQuery;

class ModelControl extends Control
{
    protected string $query = ModelQuery::class;
}