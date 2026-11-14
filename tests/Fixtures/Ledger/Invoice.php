<?php

namespace Lomkit\Access\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[ControlledBy(InvoiceControl::class)]
class Invoice extends Model
{
    use HasControl;
}
