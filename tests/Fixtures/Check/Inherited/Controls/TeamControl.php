<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Inherited\Controls;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\TeamPerimeter;

abstract class TeamControl extends Control
{
    #[\Override]
    protected function perimeters(): array
    {
        return [
            TeamPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->should(fn (Model $user, Model $row): bool => $row->getAttribute('team_id') === $user->getAttribute('team_id')),
        ];
    }
}
