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

use function Laravel\Prompts\confirm;
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
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/control.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     *
     * @return string
     */
    protected function resolveStubPath($stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
                        ? $customPath
                        : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Access\Controls';
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function buildClass($name): string
    {
        $rootNamespace = $this->rootNamespace();
        $controlNamespace = $this->getNamespace($name);

        $replace = [];

        $baseControlExists = file_exists($this->getPath("{$rootNamespace}Access\Controls\Control"));

        $replace = $this->buildPerimetersReplacements($replace, $this->option('perimeters'));

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
     * @param  array  $replace
     * @return array
     */
    protected function buildModelReplacements(array $replace): array
    {
        $modelClass = $this->parseModel($this->option('model'));

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
     * @param array $replace
     * @param array $perimeters
     *
     * @return array
     */
    protected function buildPerimetersReplacements(array $replace, array $perimeters)
    {
        $perimetersImplementation = '';

        foreach ($perimeters as $perimeter) {
            $perimeterClass = '\\'.$this->rootNamespace().'Access\\Perimeters\\'.$perimeter;

            $perimetersImplementation .= <<<PERIMETER
            $perimeterClass::new()
                    ->allowed(function (Model \$user, string \$method) {
                        return true;
                    })
                    ->should(function (Model \$user, Model \$model) {
                        return true;
                    })
                    ->query(function (Builder \$query, Model \$user) {
                        return \$query;
                    }),
                    
            PERIMETER;
        }

        return array_merge($replace, [
            '{{ perimeters }}' => $perimetersImplementation,
            '{{perimeters}}'   => $perimetersImplementation,
        ]);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['perimeters', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The perimeters that the control relies on'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model the control relies on'],
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        if (!empty($this->possiblePerimeters())) {
            $perimeters = multiselect(
                'What perimeters should this control apply to? (Optional)',
                $this->possiblePerimeters(),
            );

            if ($perimeters) {
                $input->setOption('perimeters', $perimeters);
            }
        }

        if (!empty($this->possibleModels())) {
            $model = select(
                'What model should this control apply to? (Optional)',
                $this->possibleModels(),
            );

            if ($model) {
                $input->setOption('model', $model);
            }
        }
    }

    /**
     * Get a list of possible model names.
     *
     * @return array<int, string>
     */
    protected function possiblePerimeters()
    {
        $perimetersPath = is_dir(app_path('Access/Perimeters')) ? app_path('Access/Perimeters') : app_path();

        return (new Collection(Finder::create()->files()->depth(0)->in($perimetersPath)))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel(string $model): string
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }
}
