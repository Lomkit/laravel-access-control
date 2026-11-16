<?php

namespace Lomkit\Access\Console\Check\Inventory;

use Lomkit\Access\Policies\AutomaticPolicy;
use Lomkit\Access\Policies\ControlledPolicy;

/** @internal */
enum PolicyForm: string
{
    case Controlled = 'controlled';
    case Automatic = 'automatic';
    case Own = 'own';
    case None = 'none';

    public static function of(?object $policy): self
    {
        return match (true) {
            $policy === null => self::None,
            $policy instanceof AutomaticPolicy => self::Automatic,
            $policy instanceof ControlledPolicy => self::Controlled,
            default => self::Own,
        };
    }
}
