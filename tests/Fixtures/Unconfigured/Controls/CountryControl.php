<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Controls;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;

class CountryControl extends Control
{
    protected string $model = Country::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (Model $user): bool => $user->getAttribute('name') === 'Ada')
                ->unrestricted(),
        ];
    }
}
