<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class RuleBoundPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $owner = Config::get('posts.owner');
        $owner = $owner instanceof Model ? $owner : null;
        $rule = Config::boolean('posts.prefix', false)
            ? TitleRule::startingWith(Config::get('posts.title'), $owner)
            : TitleRule::exactly(Config::get('posts.title'), $owner);

        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query) => $rule->apply($query)),
        ];
    }
}
