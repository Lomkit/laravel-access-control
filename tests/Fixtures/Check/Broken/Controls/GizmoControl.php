<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Broken\Controls;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Broken\Models\Gizmo;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\TeamPerimeter;

class GizmoControl extends Control
{
    private int $builds = 0;

    protected string $model = Gizmo::class;

    #[\Override]
    protected function perimeters(): array
    {
        $own = OwnPerimeter::new()
            ->allowed(fn (Model $user): bool => true)
            ->should(fn (Model $user, Gizmo $gizmo): bool => true);

        return $this->builds++ === 0 ? [$own] : [$own, TeamPerimeter::new()->allowed(fn (Model $user): bool => true)->unrestricted()];
    }
}
