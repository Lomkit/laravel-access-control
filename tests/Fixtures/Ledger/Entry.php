<?php

namespace Lomkit\Access\Tests\Fixtures\Ledger;

use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[ControlledBy(EntryControl::class)]
class Entry extends Journal
{
    use HasControl;
}
