<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Broken\Controls;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Broken\Models\Widget;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\AdminPerimeter;

class WidgetControl extends Control
{
    protected string $model = Widget::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [AdminPerimeter::new()->allowed(fn (Model $user): bool => true)->unrestricted()];
    }
}
