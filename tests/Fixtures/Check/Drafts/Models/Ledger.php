<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Drafts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

class Ledger extends Model
{
    use HasControl;

    /** @return HasMany<LedgerLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class);
    }
}
