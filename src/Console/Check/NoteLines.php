<?php

namespace Lomkit\Access\Console\Check;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Console\Check\Notes\Note;
use Lomkit\Access\Console\Check\Notes\NoteKind;
use Lomkit\Access\Controls\Control;

/** @internal */
final class NoteLines
{
    private const int LISTED_PER_KIND = 5;

    private const int FIRST_SUBJECTS = 3;

    private const int SHARED_PARENTS = 3;

    private const array ROOTS = [Model::class, Control::class, Boundary::class];

    /**
     * @param  list<Note>  $notes
     * @return list<string>
     */
    public static function of(array $notes): array
    {
        $lines = [];

        foreach (self::byKind($notes) as $kind) {
            if (count($kind) <= self::LISTED_PER_KIND) {
                array_push($lines, ...array_map(fn (Note $note): string => $note->message(), $kind));

                continue;
            }

            $lines[] = self::grouped($kind[0]->kind(), array_map(fn (Note $note): string => $note->subject(), $kind));
        }

        return $lines;
    }

    /**
     * @param  list<Note>  $notes
     * @return list<non-empty-list<Note>>
     */
    private static function byKind(array $notes): array
    {
        $kinds = [];

        foreach ($notes as $note) {
            $kinds[$note->kind()->value][] = $note;
        }

        return array_values($kinds);
    }

    /** @param  list<string>  $subjects */
    private static function grouped(NoteKind $kind, array $subjects): string
    {
        $count = count($subjects);
        $counted = [$count.' '.Str::plural($kind->subjectName(), $count)];

        foreach (self::sharedParents($subjects) as $parent => $children) {
            $counted[] = "{$children} extending {$parent}";
        }

        return sprintf(
            '%s: %s (first %d: %s); --json lists them all',
            $kind->value,
            implode(', ', $counted),
            self::FIRST_SUBJECTS,
            implode(', ', array_slice($subjects, 0, self::FIRST_SUBJECTS)),
        );
    }

    /**
     * @param  list<string>  $subjects
     * @return array<string, int>
     */
    private static function sharedParents(array $subjects): array
    {
        $parents = [];

        foreach ($subjects as $subject) {
            $parent = class_exists($subject) ? get_parent_class($subject) : false;

            if ($parent !== false && ! in_array($parent, self::ROOTS, true)) {
                $parents[$parent] = ($parents[$parent] ?? 0) + 1;
            }
        }

        arsort($parents);

        return array_slice(array_filter($parents, fn (int $children): bool => $children > 1), 0, self::SHARED_PARENTS, true);
    }
}
