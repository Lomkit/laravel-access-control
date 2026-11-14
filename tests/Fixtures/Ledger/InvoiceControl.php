<?php

namespace Lomkit\Access\Tests\Fixtures\Ledger;

use Lomkit\Access\Controls\Control;

class InvoiceControl extends Control
{
    protected string $model = Invoice::class;
}
