<?php

namespace Lomkit\Access\Console\Check;

use Illuminate\Support\Str;
use Lomkit\Access\Console\Check\Notes\Note;
use Lomkit\Access\Console\Check\Notes\NoteKind;

/** @internal */
final class NoteLines
{
    private const int LISTED_PER_KIND = 5;

    private const int FIRST_SUBJECTS = 3;

    private const int SHARED_ANCESTORS = 3;

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

            $lines[] = self::grouped($kind[0]->kind(), $kind);
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

    /** @param  non-empty-list<Note>  $notes */
    private static function grouped(NoteKind $kind, array $notes): string
    {
        $subjects = array_values(array_unique(array_map(fn (Note $note): string => $note->subject(), $notes)));
        $named = count($subjects).' '.Str::plural($kind->subjectName(), count($subjects));
        $counted = [count($subjects) === count($notes) ? $named : count($notes)." notes on {$named}"];

        foreach (self::sharedAncestors($subjects) as $ancestor => $descendants) {
            $counted[] = "{$descendants} extending {$ancestor}";
        }

        return sprintf(
            '%s: %s (first %d: %s); --json lists them all',
            $kind->value,
            implode(', ', $counted),
            min(self::FIRST_SUBJECTS, count($subjects)),
            implode(', ', array_slice($subjects, 0, self::FIRST_SUBJECTS)),
        );
    }

    /**
     * @param  list<string>  $subjects
     * @return array<string, int>
     */
    private static function sharedAncestors(array $subjects): array
    {
        $ancestors = [];

        foreach ($subjects as $subject) {
            foreach (class_exists($subject) ? class_parents($subject) : [] as $ancestor) {
                if (ApplicationClass::is($ancestor)) {
                    $ancestors[$ancestor] = ($ancestors[$ancestor] ?? 0) + 1;
                }
            }
        }

        arsort($ancestors);

        return array_slice(array_filter($ancestors, fn (int $descendants): bool => $descendants > 1), 0, self::SHARED_ANCESTORS, true);
    }
}
