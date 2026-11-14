<?php

namespace Lomkit\Access\Tests\Fixtures\Ledger;

use Lomkit\Access\Controls\Control;

class ReceiptControl extends Control
{
    protected string $model = Receipt::class;
}
