<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

/** @internal */
#[AsCommand(name: 'make:perimeter')]
#[Description('Create a new perimeter class')]
class PerimeterMakeCommand extends GeneratorCommand
{
    protected $type = 'Perimeter';

    #[\Override]
    protected function getStub(): string
    {
        return $this->resolveStubPath($this->option('hooks') ? '/stubs/perimeter.hooks.stub' : '/stubs/perimeter.plain.stub');
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

    /** @return list<array{string, string|null, int, string}> */
    #[\Override]
    protected function getOptions(): array
    {
        return [
            ['hooks', null, InputOption::VALUE_NONE, 'Generate the protected hooks, to write the rules in the perimeter class instead of callbacks in the control'],
        ];
    }

    #[\Override]
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        if (confirm('Should the rules be written in the perimeter class, with hooks?', false)) {
            $input->setOption('hooks', true);
        }
    }
}
