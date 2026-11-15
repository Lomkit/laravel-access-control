<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

class RewrittenDocument extends Model
{
    use ControlledSearchable;
    use HasControl;

    /**
     * @param  Collection<int, static>  $models
     * @return Collection<int, static>
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models;
    }
}
