<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
enum NoteKind: string
{
    case InheritedMemoryPerimeters = 'inherited_memory_perimeters';
    case PolicyOutsideControl = 'policy_outside_control';
    case ModelWithoutPolicy = 'model_without_policy';
    case UngrantableDrafts = 'ungrantable_drafts';
    case BoundaryRefusingDrafts = 'boundary_refusing_drafts';

    public function subjectName(): string
    {
        return match ($this) {
            self::InheritedMemoryPerimeters => 'base control',
            self::PolicyOutsideControl => 'policy',
            self::ModelWithoutPolicy => 'model',
            self::UngrantableDrafts, self::BoundaryRefusingDrafts => 'control',
        };
    }
}
