<?php

namespace Lomkit\Access\Tests\Fixtures\Ledger;

use Lomkit\Access\Controls\Control;

class EntryControl extends Control
{
    protected string $model = Entry::class;
}
