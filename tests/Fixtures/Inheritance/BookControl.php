<?php

namespace Lomkit\Access\Tests\Fixtures\Inheritance;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;

class BookControl extends Control
{
    protected string $model = Book::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [ThroughParent::belongsTo('coverChapter')];
    }
}
