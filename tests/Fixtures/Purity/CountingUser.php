<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Lomkit\Access\Tests\Fixtures\Blog\User;

class CountingUser extends User
{
    public static int $comparisons = 0;

    protected $table = 'users';

    #[\Override]
    public function is($model): bool
    {
        static::$comparisons++;

        return parent::is($model);
    }
}
