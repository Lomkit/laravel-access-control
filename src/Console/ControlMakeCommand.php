<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\multiselect;

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
    protected function getStub()
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
    protected function resolveStubPath($stub)
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
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Access\Controls';
    }

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in the base namespace.
     *
     * @param string $name
     *
     * @return string
     */
    protected function buildClass($name)
    {
        $rootNamespace = $this->rootNamespace();
        $controlNamespace = $this->getNamespace($name);

        $replace = [];

        $baseControlExists = file_exists($this->getPath("{$rootNamespace}Access\Controls\Control"));

        $replace = $this->buildPerimetersReplacements($replace, $this->option('perimeters'));

        if ($baseControlExists) {
            $replace["use {$controlNamespace}\Control;\n"] = '';
        } else {
            $replace[' extends Control'] = '';
            $replace["use {$rootNamespace}Access\Controls\Control;\n"] = '';
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
     * @param array $replace
     * @param array $perimeters
     *
     * @return array
     */
    protected function buildPerimetersReplacements(array $replace, array $perimeters)
    {
        $perimetersImplementation = '';

        foreach ($perimeters as $perimeter) {
            $perimeterClass = $this->rootNamespace().'Access\\Perimeters\\'.$perimeter;

            $perimetersImplementation .= <<<PERIMETER
                \\n
                $perimeterClass::new()
                    ->should(function (Model \$user, string \$method, Model \$model) {
                        return true;
                    })
                    ->allowed(function (Model \$user) {
                        return true;
                    })
                    ->query(function (Builder \$query, Model \$user) {
                        return \$query;
                    }),\\n
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
        $perimeters = multiselect(
            'What perimeters should this control apply to? (Optional)',
            $this->possiblePerimeters(),
        );

        if ($perimeters) {
            $input->setOption('perimeters', $perimeters);
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
}
