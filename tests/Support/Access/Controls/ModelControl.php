<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Illuminate\Support\Facades\Cache;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Queries\Query;
use Lomkit\Access\Tests\Support\Access\Queries\ModelQuery;

class ModelControl extends Control
{
    protected string $query = ModelQuery::class;

    protected function shouldClient()
    {
        return Cache::has('model-should-client');
    }

    protected function shouldSite()
    {
        return Cache::has('model-should-site');
    }

    protected function shouldOwn()
    {
        return Cache::has('model-should-own');
    }
}