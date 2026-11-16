<?php

namespace Lomkit\Access\Tests\Feature\Registry;

use Illuminate\Support\Facades\File;
use Lomkit\Access\Access;
use Lomkit\Access\Discovery\ClassFiles;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\Admin\NoteControl as AdminNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\NoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Duplicates\FirstNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Duplicates\SecondNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Admin\Note as AdminNote;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Memo;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;
use Lomkit\Access\Tests\Fixtures\Discovery\Module\MemoControl;
use Lomkit\Access\Tests\Fixtures\Discovery\OtherModule\RivalMemoControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Providers\MemosServiceProvider;
use Lomkit\Access\Tests\Fixtures\Discovery\Providers\NotesServiceProvider;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ControlDiscoveryTest extends PackageTestCase
{
    #[Test]
    public function it_discovers_controls_with_the_same_short_name_in_their_own_namespaces(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Controls')]);

        $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
        $this->assertInstanceOf(AdminNoteControl::class, Access::controlFor(AdminNote::class));
        $this->assertInstanceOf(AdminNoteControl::class, (new AdminNote)->newControl());
    }

    #[Test]
    public function it_reads_the_paths_as_glob_patterns(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Control*')]);

        $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
    }

    #[Test]
    public function it_discovers_nothing_in_a_missing_directory(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Controls').'/Missing']);

        $this->assertFalse(Access::hasControl(Note::class));
    }

    #[Test]
    public function it_discovers_the_application_controls_by_default(): void
    {
        File::ensureDirectoryExists(app_path('Access/Controls'));
        File::copy($this->fixturePath('Controls').'/NoteControl.php', app_path('Access/Controls/NoteControl.php'));

        try {
            $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
        } finally {
            File::delete(app_path('Access/Controls/NoteControl.php'));
        }
    }

    #[Test]
    public function it_finds_a_control_added_after_a_first_resolution(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Controls')]);
        $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
        $this->assertFalse(Access::hasControl(Memo::class));

        Access::discoverControlsIn([$this->fixturePath('Module')]);

        $this->assertInstanceOf(MemoControl::class, Access::controlFor(Memo::class));
        $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
    }

    #[Test]
    public function it_adds_the_directories_of_each_service_provider(): void
    {
        $this->app->register(NotesServiceProvider::class);
        $this->app->register(MemosServiceProvider::class);

        $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
        $this->assertInstanceOf(AdminNoteControl::class, Access::controlFor(AdminNote::class));
        $this->assertInstanceOf(MemoControl::class, Access::controlFor(Memo::class));
    }

    #[Test]
    public function it_always_scans_the_application_directory(): void
    {
        File::ensureDirectoryExists(app_path('Access/Controls'));
        File::copy($this->fixturePath('Controls').'/NoteControl.php', app_path('Access/Controls/NoteControl.php'));

        try {
            Access::discoverControlsIn([$this->fixturePath('Module')]);
            Access::discoverControlsIn([]);

            $this->assertSame([app_path('Access/Controls'), $this->fixturePath('Module')], $this->discovery()->paths());
            $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
            $this->assertInstanceOf(MemoControl::class, Access::controlFor(Memo::class));
        } finally {
            File::delete(app_path('Access/Controls/NoteControl.php'));
        }
    }

    #[Test]
    public function it_scans_the_same_directory_once(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Module'), $this->fixturePath('Module').'/']);
        Access::discoverControlsIn([$this->fixturePath('Module').DIRECTORY_SEPARATOR]);

        $this->assertSame([app_path('Access/Controls'), $this->fixturePath('Module')], $this->discovery()->paths());
        $this->assertSame([MemoControl::class], ClassFiles::in($this->discovery()->paths()));
    }

    #[Test]
    public function it_scans_once_a_directory_that_a_glob_also_matches(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Module'), $this->fixturePath('Modul*')]);

        $this->assertSame([MemoControl::class], ClassFiles::in($this->discovery()->paths()));
        $this->assertInstanceOf(MemoControl::class, Access::controlFor(Memo::class));
    }

    /** @return iterable<string, array{list<string>}> */
    public static function moduleOrders(): iterable
    {
        yield 'module first' => [['Module', 'OtherModule']];
        yield 'other module first' => [['OtherModule', 'Module']];
    }

    /** @param  list<string>  $modules */
    #[Test]
    #[DataProvider('moduleOrders')]
    public function it_throws_for_two_controls_of_one_model_in_two_directories_whatever_the_order(array $modules): void
    {
        foreach ($modules as $module) {
            Access::discoverControlsIn([$this->fixturePath($module)]);
        }

        try {
            Access::hasControl(Memo::class);

            $this->fail('Two discovered controls for one model were accepted.');
        } catch (DuplicateControlException $exception) {
            $this->assertStringContainsString(MemoControl::class, $exception->getMessage());
            $this->assertStringContainsString(RivalMemoControl::class, $exception->getMessage());
        }
    }

    #[Test]
    public function it_throws_when_two_discovered_controls_declare_the_same_model(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Duplicates')]);

        $this->expectException(DuplicateControlException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] model has two controls, [%s] and [%s].',
            Note::class,
            FirstNoteControl::class,
            SecondNoteControl::class,
        ));

        Access::hasControl(Note::class);
    }

    #[Test]
    public function it_throws_for_a_duplicate_even_when_the_lookup_is_for_another_model(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Duplicates'), $this->fixturePath('Controls')]);

        $this->expectException(DuplicateControlException::class);

        Access::hasControl(AdminNote::class);
    }

    protected function discovery(): ControlDiscovery
    {
        return $this->app->make(ControlDiscovery::class);
    }

    protected function fixturePath(string $directory): string
    {
        return (string) realpath(__DIR__.'/../../Fixtures/Discovery').'/'.$directory;
    }
}
