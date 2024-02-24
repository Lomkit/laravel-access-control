<?php

namespace Lomkit\Access\Controls\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Queries\Query;

trait HasPolicy
{
    public function runPolicy(string $method, Model $user, Model $model)
    {
        $concernedPerimeters = $this->getConcernedPerimeters();

        return $concernedPerimeters->contains(function (Perimeter $concernedPerimeter) use ($method, $model, $user) {
            return $this->policy($concernedPerimeter, $method, $user, $model);
        });
    }

    public function policy(Perimeter $perimeter, string $method, Model $user, Model $model): bool
    {
        // @TODO: for the "shared" example, implement the fact that for the query you can add multiple query
        $policyMethod = Str::camel($perimeter->name).'Policy';

        if (method_exists($this, $policyMethod)) {
            return $this->$policyMethod($method, $user, $model);
        }

        return false;
    }
}
