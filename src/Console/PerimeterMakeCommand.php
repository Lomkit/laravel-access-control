<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'make:perimeter')]
class PerimeterMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:perimeter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new perimeter class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Perimeter';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        $stub = null;

        if ($this->option('overlay')) {
            $stub = '/stubs/perimeter.overlay.stub';
        }

        $stub ??= '/stubs/perimeter.plain.stub';

        return $this->resolveStubPath($stub);
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
        return $rootNamespace.'\Access\Perimeters';
    }

    /**
     * Get the console command options.
     *
     * @return list<array{string, string, int, string}>
     */
    protected function getOptions(): array
    {
        return [
            ['overlay', 'o', InputOption::VALUE_NONE, 'Indicates if the perimeter overlays'],
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

        if (confirm('Should this perimeter be an overlay?', false)) {
            $input->setOption('overlay', true);
        }
    }
}
