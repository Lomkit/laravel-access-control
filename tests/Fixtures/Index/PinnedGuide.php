<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[Table('guides')]
#[ControlledBy(PinnedGuideControl::class)]
class PinnedGuide extends Model
{
    use HasControl;

    #[\Override]
    public function getMorphClass(): string
    {
        return (new Guide)->getMorphClass();
    }
}
