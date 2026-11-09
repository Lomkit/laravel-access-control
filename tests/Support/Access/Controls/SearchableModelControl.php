<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Lomkit\Access\Tests\Support\Models\SearchableModel;

class SearchableModelControl extends ModelControl
{
    protected string $model = SearchableModel::class;
}
