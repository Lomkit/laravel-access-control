<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\HookRecorder;

class RecordingPerimeter extends Perimeter
{
    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        $this->recorder()->record('allows', $context);

        return true;
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        $this->recorder()->record('includes', $context);

        return true;
    }

    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $this->recorder()->record('scope', $context);

        $query->whereRaw('1=1');
    }

    protected function recorder(): HookRecorder
    {
        return Container::getInstance()->make(HookRecorder::class);
    }
}
