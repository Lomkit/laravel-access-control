<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;

class GuideNoteControl extends Control
{
    protected string $model = GuideNote::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('guide'),
        ];
    }
}
