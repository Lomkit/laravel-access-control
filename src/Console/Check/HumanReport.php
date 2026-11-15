<?php

namespace Lomkit\Access\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;
use Lomkit\Access\Console\Check\Notes\Note;
use Lomkit\Access\Console\Check\Problems\Problem;

/** @internal */
final readonly class HumanReport
{
    public function __construct(private CheckReport $report) {}

    public function render(Factory $components): void
    {
        $this->renderDiscovery($components);

        foreach ($this->report->controls as $control) {
            $components->twoColumnDetail("<fg=green;options=bold>{$control->control}</>", $control->model);

            foreach ($control->perimeters as $perimeter) {
                $components->twoColumnDetail('  '.$perimeter->label(), $perimeter->summary());
            }

            $components->twoColumnDetail('  boundary', $control->boundarySummary());
            $components->twoColumnDetail('  search', $control->searchSummary());
        }

        $this->renderProblems($components);
        $this->renderNotes($components);
    }

    private function renderDiscovery(Factory $components): void
    {
        $discovery = $this->report->discovery;

        $components->twoColumnDetail('controls', count($this->report->controls).' built from '.$this->listed($discovery->paths));
        $components->twoColumnDetail('cache', $discovery->cached ? 'access:cache is in use' : 'not cached');
        $components->twoColumnDetail('models', $this->listed($discovery->models));
        $components->twoColumnDetail('scout driver', $discovery->driver === null ? 'Scout is not installed' : "{$discovery->driver}, from the config of this environment");
        $components->twoColumnDetail('pivots', Str::plural('BelongsToMany relation', $this->report->relations, prependCount: true).' with a declared return type scanned');
    }

    private function renderProblems(Factory $components): void
    {
        if ($this->report->passed()) {
            $components->twoColumnDetail('problems', '<fg=green;options=bold>none</>');

            return;
        }

        $components->twoColumnDetail('problems', '<fg=red;options=bold>'.count($this->report->problems).' FAILED</>');
        $components->bulletList(array_map(fn (Problem $problem): string => $problem->message(), $this->report->problems));
    }

    private function renderNotes(Factory $components): void
    {
        $components->twoColumnDetail('notes', $this->report->notes === [] ? 'none' : (string) count($this->report->notes));

        if ($this->report->notes !== []) {
            $components->bulletList(array_map(fn (Note $note): string => $note->message(), $this->report->notes));
        }
    }

    /** @param  list<string>  $values */
    private function listed(array $values): string
    {
        return $values === [] ? 'none' : implode(', ', $values);
    }
}
