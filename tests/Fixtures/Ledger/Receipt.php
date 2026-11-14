<?php

namespace Lomkit\Access\Tests\Fixtures\Ledger;

use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[ControlledBy(ReceiptControl::class)]
class Receipt extends Record
{
    use HasControl;
}
