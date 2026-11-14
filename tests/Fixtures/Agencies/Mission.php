<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[UseFactory(MissionFactory::class)]
#[ControlledBy(MissionControl::class)]
class Mission extends Model
{
    use HasControl;
    use HasFactory;

    #[\Override]
    protected function casts(): array
    {
        return ['public' => 'boolean'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
