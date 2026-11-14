<?php

namespace Lomkit\Access\Registry;

use Illuminate\Contracts\Foundation\Application;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\ImpurePerimetersException;

enum PerimeterPurity
{
    case Verified;
    case Trusted;

    public static function for(Application $app): self
    {
        if ($app->hasDebugModeEnabled() || $app->runningUnitTests()) {
            return self::Verified;
        }

        return self::Trusted;
    }

    /** @throws ImpurePerimetersException When the control builds perimeters of another shape than the ones it built first. */
    public function check(Control $control): void
    {
        if ($this === self::Verified) {
            $control->verifyPerimeters();
        }
    }
}
