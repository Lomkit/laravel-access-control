<?php

namespace Lomkit\Access\Tests\Fixtures\Check\DraftsGranted\Controls;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\DraftsGranted\Models\Pin;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\AdminPerimeter;

class PinControl extends Control
{
    protected string $model = Pin::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            AdminPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->unrestricted(),
        ];
    }
}
