<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

class Editor extends User
{
    protected $table = 'users';

    #[\Override]
    public function getMorphClass(): string
    {
        return 'editor';
    }
}
