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
        return Cache::get('model-should-client', false);
    }

    protected function shouldSite()
    {
        return Cache::get('model-should-site', false);
    }

    protected function shouldOwn()
    {
        return Cache::get('model-should-own', false);
    }
}