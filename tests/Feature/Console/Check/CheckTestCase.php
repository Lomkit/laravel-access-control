<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\PackageTestCase;

abstract class CheckTestCase extends PackageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::delete([$this->cachePath(), $this->app->bootstrapPath('cache/access-reindex.php')]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        File::delete([$this->cachePath(), $this->app->bootstrapPath('cache/access-reindex.php')]);

        parent::tearDown();
    }

    /** @param  list<string>  $scenarios */
    protected function discover(array $scenarios): void
    {
        Access::discoverControlsIn(array_map(fn (string $scenario): string => $this->fixture("{$scenario}/Controls"), $scenarios));
    }

    protected function fixture(string $path): string
    {
        return dirname(__DIR__, 3)."/Fixtures/Check/{$path}";
    }

    protected function cachePath(): string
    {
        return $this->app->bootstrapPath('cache/access-controls.php');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{int, array<string, mixed>}
     */
    protected function checkJson(array $options = []): array
    {
        $code = Artisan::call('access:check', [...$options, '--json' => true]);

        return [$code, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{kind: string, subject: string, message: string}>
     */
    protected function problems(array $report): array
    {
        return $report['problems'];
    }
}
