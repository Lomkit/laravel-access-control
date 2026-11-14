<?php

namespace Lomkit\Access\Tests\Feature\Registry;

use Illuminate\Support\Facades\File;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\Admin\NoteControl as AdminNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\NoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Duplicates\FirstNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Duplicates\SecondNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Admin\Note as AdminNote;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;
use Lomkit\Access\Tests\PackageTestCase;
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
    public function it_honours_paths_set_after_a_lookup(): void
    {
        $this->assertFalse(Access::hasControl(Note::class));

        Access::discoverControlsIn([$this->fixturePath('Controls')]);

        $this->assertTrue(Access::hasControl(Note::class));

        Access::discoverControlsIn([]);

        $this->assertFalse(Access::hasControl(Note::class));
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

    protected function fixturePath(string $directory): string
    {
        return (string) realpath(__DIR__.'/../../Fixtures/Discovery').'/'.$directory;
    }
}
