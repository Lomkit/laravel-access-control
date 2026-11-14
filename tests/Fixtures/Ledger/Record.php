<?php

namespace Lomkit\Access\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[UsePolicy(RecordPolicy::class)]
abstract class Record extends Model {}
