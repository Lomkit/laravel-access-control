<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'make:perimeter')]
#[Description('Create a new perimeter class')]
class PerimeterMakeCommand extends GeneratorCommand
{
    protected $type = 'Perimeter';

    #[\Override]
    protected function getStub(): string
    {
        $stub = null;

        if ($this->option('overlay')) {
            $stub = '/stubs/perimeter.overlay.stub';
        }

        $stub ??= '/stubs/perimeter.plain.stub';

        return $this->resolveStubPath($stub);
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
        return $rootNamespace.'\Access\Perimeters';
    }

    /** @return list<array{string, string, int, string}> */
    #[\Override]
    protected function getOptions(): array
    {
        return [
            ['overlay', 'o', InputOption::VALUE_NONE, 'Indicates if the perimeter overlays'],
        ];
    }

    #[\Override]
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
