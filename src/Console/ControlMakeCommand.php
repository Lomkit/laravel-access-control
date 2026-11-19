<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/** @internal */
#[AsCommand(name: 'make:control')]
#[Description('Create a new control class')]
class ControlMakeCommand extends GeneratorCommand
{
    protected $type = 'Control';

    #[\Override]
    public function handle(): bool|int|null
    {
        $model = $this->controlledModel();

        if (! is_subclass_of($model, Model::class)) {
            $this->components->error(sprintf(
                'The [%s] model is not an Eloquent model class. A control needs the model it controls: create the model first, or name it with --model.',
                $model,
            ));

            return self::FAILURE;
        }

        foreach ($this->parents() as $parent) {
            if (! $parent->isBelongsToOf($model)) {
                $this->components->error(sprintf(
                    'The [%s] relation is not a belongsTo of the [%s] model. --parent names the belongsTo relation the rows inherit their access from, such as --parent=post.',
                    $parent->relation(),
                    $model,
                ));

                return self::FAILURE;
            }
        }

        return parent::handle();
    }

    #[\Override]
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/control.stub');
    }

    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
                        ? $customPath
                        : __DIR__.$stub;
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Access\Controls';
    }

    #[\Override]
    protected function buildClass($name): string
    {
        $replace = (new ControlStubReplacements($this->controlledModel(), $this->buildablePerimeters(), $this->parents()))->toArray();

        if (file_exists($this->getPath("{$this->rootNamespace()}Access\Controls\Control"))) {
            $replace['use Lomkit\Access\Controls\Control;'] = '';
        } else {
            $replace["use {$this->getNamespace($name)}\Control;\n"] = '';
        }

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    protected function controlledModel(): string
    {
        $model = $this->option('model');

        if (! is_string($model) || $model === '') {
            $model = Str::replaceLast('Control', '', class_basename($this->getNameInput()));
        }

        $model = ltrim(str_replace('/', '\\', $model), '\\');

        return class_exists($model) ? $model : $this->qualifyModel($model);
    }

    /** @return list<string> */
    private function buildablePerimeters(): array
    {
        $generated = new GeneratedPerimeters($this->perimeterClasses());

        foreach ($generated->warnings() as $warning) {
            $this->components->warn($warning);
        }

        return $generated->buildable();
    }

    /** @return list<string> */
    protected function perimeterClasses(): array
    {
        $perimeters = $this->option('perimeters');

        return array_values(array_map(
            fn (string $perimeter): string => $this->rootNamespace().'Access\\Perimeters\\'.$perimeter,
            array_filter(is_array($perimeters) ? $perimeters : [], is_string(...)),
        ));
    }

    /** @return list<GeneratedParent> */
    protected function parents(): array
    {
        $parents = $this->option('parent');

        return array_values(array_map(
            fn (string $relation): GeneratedParent => new GeneratedParent($relation),
            array_filter(is_array($parents) ? $parents : [], is_string(...)),
        ));
    }

    /** @return list<array{string, string|null, int, string}> */
    #[\Override]
    protected function getOptions(): array
    {
        return [
            ['perimeters', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The perimeters that the control relies on'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model the control relies on, named after the control by default'],
            ['parent', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The belongsTo relations the rows inherit their access from'],
        ];
    }

    #[\Override]
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        if (! empty($this->possiblePerimeters())) {
            $perimeters = multiselect(
                'What perimeters should this control apply to? (Optional)',
                $this->possiblePerimeters(),
            );

            if ($perimeters) {
                $input->setOption('perimeters', $perimeters);
            }
        }

        if (! empty($this->possibleModelNames())) {
            $model = select(
                'What model should this control apply to?',
                $this->possibleModelNames(),
            );

            $input->setOption('model', $model);
        }
    }

    /** @return array<int, string> */
    protected function possiblePerimeters(): array
    {
        return $this->classNamesIn(is_dir(app_path('Access/Perimeters')) ? app_path('Access/Perimeters') : app_path());
    }

    /** @return array<int, string> */
    protected function possibleModelNames(): array
    {
        return $this->classNamesIn(is_dir(app_path('Models')) ? app_path('Models') : app_path());
    }

    /** @return array<int, string> */
    private function classNamesIn(string $directory): array
    {
        return (new Collection(Finder::create()->files()->depth(0)->in($directory)))
            ->map(fn (SplFileInfo $file): string => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
    }
}
