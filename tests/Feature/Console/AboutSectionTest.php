<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Scout\Searchable;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\PackageTestCase;
use Lomkit\Rest\Facades\Rest;
use PHPUnit\Framework\Attributes\Test;

class AboutSectionTest extends PackageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::delete([$this->cachePath(), $this->app->bootstrapPath('cache/access-reindex.php')]);
        Access::discoverControlsIn([$this->fixture('Clean/Controls')]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        File::delete([$this->cachePath(), $this->app->bootstrapPath('cache/access-reindex.php')]);

        parent::tearDown();
    }

    #[Test]
    public function it_describes_the_discovered_controls_without_cache(): void
    {
        $this->assertSame([
            'version' => InstalledVersions::getPrettyVersion('lomkit/laravel-access-control'),
            'controls' => '2',
            'discovery_paths' => 'app/Access/Controls, '.$this->fixture('Clean/Controls'),
            'cache' => 'not cached',
            'automatic_policies' => 'not registered',
            'scout' => trait_exists(Searchable::class) ? 'installed, collection driver' : 'not installed',
            'laravel-rest-api' => class_exists(Rest::class) ? 'installed, AccessControlExtension enabled' : 'not installed',
        ], $this->about());
    }

    #[Test]
    public function it_counts_the_cached_controls_once_access_cache_ran(): void
    {
        $this->artisan('access:cache')->assertOk()->run();
        $this->forgetDiscoveredControls();

        $about = $this->about();

        $this->assertSame('2', $about['controls']);
        $this->assertSame('cached', $about['cache']);
        $this->assertSame('app/Access/Controls', $about['discovery_paths']);
    }

    #[Test]
    public function it_lists_the_directories_of_every_call(): void
    {
        Access::discoverControlsIn([$this->fixture('Drafts/Controls')]);

        $this->assertSame(
            'app/Access/Controls, '.$this->fixture('Clean/Controls').', '.$this->fixture('Drafts/Controls'),
            $this->about()['discovery_paths'],
        );
    }

    #[Test]
    public function it_tells_that_the_automatic_policies_are_registered(): void
    {
        Access::registerPolicies();

        $this->assertSame('registered', $this->about()['automatic_policies']);
    }

    #[Test]
    public function it_tells_in_one_line_that_the_discovery_failed_instead_of_breaking_about(): void
    {
        File::put($this->cachePath(), "<?php throw new LogicException(\"The cached controls are corrupt.\nRun access:cache.\");\n");

        $about = $this->about();

        $this->assertSame('discovery failed: The cached controls are corrupt.', $about['controls']);
        $this->assertSame('cached', $about['cache']);
    }

    #[Test]
    public function it_prints_the_section_in_the_text_output(): void
    {
        $this->artisan('about', ['--only' => 'access_control'])
            ->expectsOutputToContain('Access Control')
            ->expectsOutputToContain('Automatic policies')
            ->expectsOutputToContain('not cached')
            ->assertOk();
    }

    /** @return array<string, string> */
    private function about(): array
    {
        Artisan::call('about', ['--only' => 'access_control', '--json' => true]);

        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['access_control'];
    }

    private function fixture(string $path): string
    {
        return dirname(__DIR__, 2)."/Fixtures/Check/{$path}";
    }

    private function cachePath(): string
    {
        return $this->app->bootstrapPath('cache/access-controls.php');
    }
}
