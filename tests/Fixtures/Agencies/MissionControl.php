<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;

class MissionControl extends Control
{
    protected string $model = Mission::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            Levels::of(
                DirectorPerimeter::new()
                    ->allowed(fn (Member $member, Ability $ability): bool => $member->can("{$ability} every mission"))
                    ->unrestricted(),
                AssignedPerimeter::new()
                    ->allowed(fn (Member $member, Ability $ability): bool => $member->can("{$ability} assigned missions"))
                    ->query(function (Builder $query, Member $member): void {
                        $query->where('member_id', $member->getKey());
                    }),
            ),
            PublicPerimeter::new()
                ->allowed(fn (Member $member, Ability $ability): bool => $member->can("{$ability} public missions"))
                ->should(fn (Member $member, Mission $mission): bool => $mission->public)
                ->query(function (Builder $query): void {
                    $query->where('public', true);
                }),
        ];
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return new AgencyBoundary;
    }
}
