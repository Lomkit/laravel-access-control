<?php

namespace Lomkit\Access\Attributes;

use Attribute;
use Lomkit\Access\Controls\Control;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ControlledBy
{
    /** @param  class-string<Control>  $control */
    public function __construct(public string $control) {}
}
