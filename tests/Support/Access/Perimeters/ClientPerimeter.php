<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;

class ClientPerimeter extends Perimeter
{
    public function should(Authenticatable $user, string $method, Model $model): bool
    {
        return $user->should_client;
    }
}
