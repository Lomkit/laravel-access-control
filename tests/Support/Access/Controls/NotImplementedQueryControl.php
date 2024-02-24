<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Lomkit\Access\Controls\Control;

class NotImplementedQueryControl extends Control
{
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

    public function fallbackQuery(Builder $query): Builder
    {
        return $query->whereRaw('0 = 1');
    }
}
