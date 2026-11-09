<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

#[AsCommand(name: 'make:control')]
class ControlMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:control';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new control class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Control';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/control.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
                        ? $customPath
                        : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Access\Controls';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $rootNamespace = $this->rootNamespace();
        $controlNamespace = $this->getNamespace($name);

        /** @var array<string, string> $replace */
        $replace = [];

        $baseControlExists = file_exists($this->getPath("{$rootNamespace}Access\Controls\Control"));

        /** @var list<string> $perimeters */
        $perimeters = $this->option('perimeters');

        $replace = $this->buildPerimetersReplacements($replace, $perimeters);

        if ($this->option('model')) {
            $replace = $this->buildModelReplacements($replace);
        }

        if ($baseControlExists) {
            $replace['use Lomkit\Access\Controls\Control;'] = '';
        } else {
            $replace["use {$controlNamespace}\Control;\n"] = '';
        }

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Build the model replacement values.
     *
     * @param  array<string, string>  $replace
     * @return array<string, string>
     */
    protected function buildModelReplacements(array $replace): array
    {
        /** @var string $model */
        $model = $this->option('model');

        $modelClass = $this->parseModel($model);

        return array_merge($replace, [
            'DummyFullModelClass' => $modelClass,
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
            'DummyModelClass' => class_basename($modelClass),
            '{{ model }}' => class_basename($modelClass),
            '{{model}}' => class_basename($modelClass),
            'DummyModelVariable' => lcfirst(class_basename($modelClass)),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{modelVariable}}' => lcfirst(class_basename($modelClass)),
        ]);
    }

    /**
     * Build the model replacement values.
     *
     * @param  array<string, string>  $replace
     * @param  list<string>  $perimeters
     * @return array<string, string>
     */
    protected function buildPerimetersReplacements(array $replace, array $perimeters): array
    {
        $perimetersImplementation = '';

        foreach ($perimeters as $perimeter) {
            $perimeterClass = '\\'.$this->rootNamespace().'Access\\Perimeters\\'.$perimeter;

            // Every generated callback denies access until the developer replaces it with the real rule
            $perimetersImplementation .= <<<PERIMETER
            $perimeterClass::new()
                    ->allowed(function (Model \$user, string \$method) {
                        return false;
                    })
                    ->should(function (Model \$user, Model \$model) {
                        return false;
                    })
                    ->query(function (Builder \$query, Model \$user) {
                        return \$query->whereRaw('0=1');
                    })
                    ->scoutQuery(function (\Laravel\Scout\Builder \$query, Model \$user) {
                        return \$query->where('__NOT_A_VALID_FIELD__', 0);
                    }),

            PERIMETER;
        }

        return array_merge($replace, [
            '{{ perimeters }}' => $perimetersImplementation,
            '{{perimeters}}' => $perimetersImplementation,
        ]);
    }

    /**
     * Get the console command options.
     *
     * @return list<array{string, string, int, string}>
     */
    protected function getOptions(): array
    {
        return [
            ['perimeters', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The perimeters that the control relies on'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model the control relies on'],
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     */
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
                'What model should this control apply to? (Optional)',
                $this->possibleModelNames(),
            );

            if ($model) {
                $input->setOption('model', $model);
            }
        }
    }

    /**
     * Get a list of possible perimeter names.
     *
     * @return array<int, string>
     */
    protected function possiblePerimeters(): array
    {
        return $this->classNamesIn(is_dir(app_path('Access/Perimeters')) ? app_path('Access/Perimeters') : app_path());
    }

    /**
     * Get a list of possible model names.
     *
     * @return array<int, string>
     */
    protected function possibleModelNames(): array
    {
        return $this->classNamesIn(is_dir(app_path('Models')) ? app_path('Models') : app_path());
    }

    /**
     * Get the names of the classes at the root of the given directory.
     *
     * @return array<int, string>
     */
    private function classNamesIn(string $directory): array
    {
        return (new Collection(Finder::create()->files()->depth(0)->in($directory)))
            ->map(fn (SplFileInfo $file): string => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Get the fully-qualified model class name.
     *
     *
     * @throws InvalidArgumentException
     */
    protected function parseModel(string $model): string
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }
}
