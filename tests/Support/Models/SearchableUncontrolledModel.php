<?php

namespace Lomkit\Access\Tests\Support\Models;

use Laravel\Scout\Searchable;

class SearchableUncontrolledModel extends UncontrolledModel
{
    use Searchable;
}
