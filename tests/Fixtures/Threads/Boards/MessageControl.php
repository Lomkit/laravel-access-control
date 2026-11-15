<?php

namespace Lomkit\Access\Tests\Fixtures\Threads\Boards;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Threads\Message;

class MessageControl extends Control
{
    protected string $model = Message::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('board', inverse: 'messages'),
        ];
    }
}
