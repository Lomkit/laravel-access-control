<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class StubsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->removeGeneratedFiles();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeGeneratedFiles();

        parent::tearDown();
    }

    protected function removeGeneratedFiles(): void
    {
        File::deleteDirectory(base_path('stubs'));
        File::delete([
            app_path('Access/Controls/StubbedControl.php'),
            app_path('Access/Perimeters/StubbedPerimeter.php'),
        ]);
    }

    /** @return array<string, string> */
    protected function packageStubs(): array
    {
        return [
            (string) realpath(__DIR__.'/../../../src/Console/stubs/control.stub') => 'control.stub',
            (string) realpath(__DIR__.'/../../../src/Console/stubs/perimeter.plain.stub') => 'perimeter.plain.stub',
            (string) realpath(__DIR__.'/../../../src/Console/stubs/perimeter.overlay.stub') => 'perimeter.overlay.stub',
        ];
    }

    #[Test]
    public function it_registers_each_stub_with_stub_publish(): void
    {
        Event::dispatch($event = new PublishingStubs([]));

        $this->assertSame($this->packageStubs(), $event->stubs);
    }

    #[Test]
    public function it_publishes_each_stub_into_the_application(): void
    {
        $this->artisan('stub:publish')->assertOk();

        foreach ($this->packageStubs() as $source => $name) {
            $this->assertFileEquals($source, base_path("stubs/{$name}"));
        }
    }

    /** @return array<string, array{string, array<string, mixed>, string, string}> */
    public static function generators(): array
    {
        return [
            'control' => ['make:control', ['name' => 'StubbedControl', '--model' => 'Lomkit\\Access\\Tests\\Fixtures\\Blog\\Comment'], 'control.stub', 'Access/Controls/StubbedControl.php'],
            'perimeter' => ['make:perimeter', ['name' => 'StubbedPerimeter'], 'perimeter.plain.stub', 'Access/Perimeters/StubbedPerimeter.php'],
            'overlay perimeter' => ['make:perimeter', ['name' => 'StubbedPerimeter', '--overlay' => true], 'perimeter.overlay.stub', 'Access/Perimeters/StubbedPerimeter.php'],
        ];
    }

    /** @param  array<string, mixed>  $arguments */
    #[Test]
    #[DataProvider('generators')]
    public function it_generates_from_the_stub_the_application_published(string $command, array $arguments, string $stub, string $generated): void
    {
        File::ensureDirectoryExists(base_path('stubs'));
        File::put(base_path("stubs/{$stub}"), "<?php\n\nnamespace {{ namespace }};\n\nfinal class {{ class }} extends PublishedStub {}\n");

        $this->artisan($command, $arguments)->assertOk();

        $this->assertStringContainsString('extends PublishedStub', File::get(app_path($generated)));
    }
}
