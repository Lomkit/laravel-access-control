<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lomkit\Access\Controls\Control;

class ModelControl extends Control
{
    protected function shouldShared()
    {
        return Cache::get('model-should-shared', false);
    }

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

    public function sharedQuery(Builder $query)
    {
        $query->orWhere('is_shared', true);
    }

    public function clientQuery(Builder $query)
    {
        $query->orWhere('is_client', true);
    }

    public function siteQuery(Builder $query)
    {
        $query->orWhere('is_site', true);
    }

    public function ownQuery(Builder $query)
    {
        $query->orWhere('is_own', true);
    }

    public function fallbackQuery(Builder $query): Builder
    {
        return $query->whereRaw('0 = 1');
    }

    public function sharedPolicy(string $method, Model $user, Model $model): bool
    {
        return true;
    }

    public function clientPolicy(string $method, Model $user, Model $model): bool
    {
        return true;
    }

    public function sitePolicy(string $method, Model $user, Model $model): bool
    {
        return true;
    }

    public function ownPolicy(string $method, Model $user, Model $model): bool
    {
        return true;
    }
}
