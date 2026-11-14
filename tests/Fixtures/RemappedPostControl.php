<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;

class RemappedPostControl extends PostControl
{
    use ConfiguresPerimeters;

    #[\Override]
    public function abilityFor(string $policyMethod): Ability
    {
        return match ($policyMethod) {
            'viewAny' => Ability::of('browse'),
            'approve' => Ability::update(),
            default => parent::abilityFor($policyMethod),
        };
    }
}
