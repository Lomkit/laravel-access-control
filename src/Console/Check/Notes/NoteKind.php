<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
enum NoteKind: string
{
    case InheritedMemoryPerimeters = 'inherited_memory_perimeters';
    case PolicyOutsideControl = 'policy_outside_control';
    case ModelWithoutPolicy = 'model_without_policy';
}
