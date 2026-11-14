<?php

namespace Lomkit\Access\Tests\Feature\Registry;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\Admin\NoteControl as AdminNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\NoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Admin\Note as AdminNote;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DiscoveryCacheTest extends PackageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::delete($this->cachePath());
    }

    #[\Override]
    protected function tearDown(): void
    {
        File::delete($this->cachePath());

        parent::tearDown();
    }

    #[Test]
    public function it_caches_the_control_of_each_discovered_model(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Controls')]);

        $this->artisan('access:cache')->assertOk()->run();

        $this->assertSame(
            [AdminNote::class => AdminNoteControl::class, Note::class => NoteControl::class],
            collect(require $this->cachePath())->sortKeys()->all(),
        );
    }

    #[Test]
    public function it_reads_the_cache_instead_of_discovering_the_controls(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Controls')]);
        $this->artisan('access:cache')->assertOk()->run();

        Access::discoverControlsIn([$this->fixturePath('Duplicates')]);

        $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
        $this->assertInstanceOf(AdminNoteControl::class, Access::controlFor(AdminNote::class));
    }

    #[Test]
    public function it_refuses_to_cache_two_controls_for_the_same_model(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Duplicates')]);

        $this->expectException(DuplicateControlException::class);

        try {
            $this->artisan('access:cache')->run();
        } finally {
            $this->assertFileDoesNotExist($this->cachePath());
        }
    }

    #[Test]
    public function it_skips_a_cached_control_class_that_no_longer_exists(): void
    {
        $this->writeCache([
            Note::class => 'Lomkit\Access\Tests\Fixtures\Discovery\Controls\DeletedNoteControl',
            AdminNote::class => AdminNoteControl::class,
        ]);

        $this->assertFalse(Access::hasControl(Note::class));
        $this->assertInstanceOf(AdminNoteControl::class, Access::controlFor(AdminNote::class));
    }

    #[Test]
    public function it_skips_a_cached_control_that_now_controls_another_model(): void
    {
        $this->writeCache([Note::class => AdminNoteControl::class]);

        $this->assertFalse(Access::hasControl(Note::class));
    }

    #[Test]
    public function it_skips_the_entries_of_an_unreadable_cache(): void
    {
        $this->writeCache([NoteControl::class, 'key' => ['value']]);

        $this->assertFalse(Access::hasControl(Note::class));
    }

    #[Test]
    public function it_clears_the_cache_and_discovers_the_controls_again(): void
    {
        $this->writeCache([Note::class => NoteControl::class]);
        $this->assertTrue(Access::hasControl(Note::class));

        $this->artisan('access:clear')->assertOk()->run();

        $this->assertFileDoesNotExist($this->cachePath());
        $this->assertFalse(Access::hasControl(Note::class));
    }

    #[Test]
    public function it_clears_without_a_cache_file(): void
    {
        $this->artisan('access:clear')->assertOk()->run();

        $this->assertFileDoesNotExist($this->cachePath());
    }

    #[Test]
    public function it_hooks_the_cache_commands_into_optimize(): void
    {
        $this->assertContains('access:cache', ServiceProvider::$optimizeCommands);
        $this->assertContains('access:clear', ServiceProvider::$optimizeClearCommands);
    }

    /** @param  array<int|string, mixed>  $entries */
    protected function writeCache(array $entries): void
    {
        File::put($this->cachePath(), '<?php return '.var_export($entries, true).';'.PHP_EOL);
    }

    protected function cachePath(): string
    {
        return $this->app->bootstrapPath('cache/access-controls.php');
    }

    protected function fixturePath(string $directory): string
    {
        return (string) realpath(__DIR__.'/../../Fixtures/Discovery/'.$directory);
    }
}
