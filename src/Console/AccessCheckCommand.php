<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Lomkit\Access\Console\Check\CheckReport;
use Lomkit\Access\Console\Check\Checks\ControlCheck;
use Lomkit\Access\Console\Check\Checks\DiscoveryCheck;
use Lomkit\Access\Console\Check\Checks\MemorySpreadCheck;
use Lomkit\Access\Console\Check\Checks\ModelEngine;
use Lomkit\Access\Console\Check\Checks\ModelScan;
use Lomkit\Access\Console\Check\Checks\PivotCheck;
use Lomkit\Access\Console\Check\Checks\PolicyCheck;
use Lomkit\Access\Console\Check\Checks\SearchCheck;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\HumanReport;
use Lomkit\Access\Console\Check\Inventory\ControlInventory;
use Lomkit\Access\Console\Check\Inventory\DiscoveryInventory;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Search\UsesControlledSearchable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'access:check')]
#[Signature('access:check
    {--only=* : Check only the controls of these models or control classes}
    {--models=* : The directories scanned for models that use HasControl or #[ControlledBy], app/Models by default}
    {--json : Print the report as JSON}')]
#[Description('Check the access controls, their policies and their searches, and list their perimeters')]
class AccessCheckCommand extends Command
{
    public function handle(ControlDiscovery $discovery, DiscoveryCache $cache): int
    {
        $findings = new Findings;
        $paths = $this->modelPaths();
        $scanned = ModelScan::in($paths);
        $models = $this->laravel->make(DiscoveryCheck::class)->run($findings, $scanned, DiscoveryInventory::relative($this->laravel->basePath(), $cache->path()));
        $only = $this->stringList('only');
        $selected = $models->only($only);

        if ($only !== [] && $selected->all() === []) {
            $this->components->error('No discovered control or controlled model is named ['.implode(', ', $only).'].');

            return self::INVALID;
        }

        $controls = $this->laravel->make(ControlCheck::class)->run($findings, $selected);
        $this->laravel->make(PolicyCheck::class)->run($findings, $controls);
        (new MemorySpreadCheck)->run($findings, $controls);
        $relations = $this->checkSearch($findings, $controls, $scanned);

        $report = new CheckReport(
            DiscoveryInventory::of($this->laravel, $discovery, $cache, $paths, $this->scoutDriver()),
            array_map(fn (Control $control): ControlInventory => ControlInventory::of($control, $this->engineOf($control)), $controls),
            $relations,
            $findings->problems(),
            $findings->notes(),
        );

        $this->output($report);

        return $report->passed() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<Control>  $controls
     * @param  list<class-string<Model>>  $scanned
     */
    private function checkSearch(Findings $findings, array $controls, array $scanned): int
    {
        if (! trait_exists(Searchable::class)) {
            return 0;
        }

        (new SearchCheck)->run($findings, $controls);

        return (new PivotCheck)->run($findings, array_values(array_unique([...array_map(fn (Control $control): string => $control->getModel(), $controls), ...$scanned])));
    }

    private function output(CheckReport $report): void
    {
        if ($this->option('json') === true) {
            $this->output->writeln(json_encode($report->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return;
        }

        (new HumanReport($report))->render($this->components);

        $report->passed()
            ? $this->components->info('No problem found.')
            : $this->components->error(Str::plural('problem', count($report->problems), prependCount: true).' found.');
    }

    private function engineOf(Control $control): ?string
    {
        if (! trait_exists(Searchable::class) || ! UsesControlledSearchable::check($control->getModel())) {
            return null;
        }

        try {
            return get_debug_type(ModelEngine::of($control->getModel()));
        } catch (Throwable) {
            return null;
        }
    }

    private function scoutDriver(): ?string
    {
        $driver = trait_exists(Searchable::class) ? config('scout.driver') : null;

        return is_string($driver) ? $driver : null;
    }

    /** @return list<string> */
    private function modelPaths(): array
    {
        $paths = $this->stringList('models');

        return $paths === [] ? [$this->laravel->path('Models')] : $paths;
    }

    /** @return list<string> */
    private function stringList(string $option): array
    {
        return array_values(array_filter((array) $this->option($option), is_string(...)));
    }
}
