<?php

namespace Lomkit\Access\Tests\Feature\Registry;

use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Exceptions\InvalidControlException;
use Lomkit\Access\Exceptions\InvalidControlledByException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\UserControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\Admin\NoteControl as AdminNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\BaseControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\NoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\NoteHelper;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\AbstractlyLinkedNote;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\HelpedNote;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\MislinkedNote;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;
use Lomkit\Access\Tests\Fixtures\Discovery\Rival\RivalPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class ControlLinkTest extends PackageTestCase
{
    #[Test]
    public function it_links_a_model_to_the_control_its_attribute_names(): void
    {
        $this->assertInstanceOf(PostControl::class, Access::controlFor(Post::class));
        $this->assertInstanceOf(PostControl::class, Access::controlFor(new Post));
        $this->assertInstanceOf(PostControl::class, (new Post)->newControl());
        $this->assertTrue(Access::hasControl(Post::class));
        $this->assertTrue(Access::hasControl(new Post));
    }

    #[Test]
    public function it_falls_back_to_the_discovered_control_of_a_model_without_attribute(): void
    {
        $this->assertFalse(Access::hasControl(Note::class));

        Access::discoverControlsIn([$this->fixturePath('Controls')]);

        $this->assertInstanceOf(NoteControl::class, Access::controlFor(Note::class));
        $this->assertTrue(Access::hasControl(Note::class));
    }

    #[Test]
    public function it_accepts_a_discovered_control_that_the_attribute_names_too(): void
    {
        Access::discoverControlsIn([realpath(__DIR__.'/../../Fixtures/Blog')]);

        $this->assertInstanceOf(PostControl::class, Access::controlFor(Post::class));
        $this->assertInstanceOf(UserControl::class, Access::controlFor(User::class));
    }

    #[Test]
    public function it_throws_when_another_control_is_discovered_for_a_model_that_declares_its_control(): void
    {
        Access::discoverControlsIn([$this->fixturePath('Rival')]);

        $this->expectException(DuplicateControlException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] model has two controls, [%s] and [%s].',
            Post::class,
            PostControl::class,
            RivalPostControl::class,
        ));

        Access::hasControl(Post::class);
    }

    #[Test]
    public function it_throws_when_the_model_has_no_control(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Comment::class));

        Access::controlFor(Comment::class);
    }

    #[Test]
    public function it_has_no_control_for_a_class_that_is_not_a_model(): void
    {
        $this->assertFalse(Access::hasControl(Comment::class));
        $this->assertFalse(Access::hasControl(stdClass::class));
        $this->assertFalse(Access::hasControl('Lomkit\Access\Tests\Fixtures\Missing'));
    }

    #[Test]
    public function it_throws_when_the_attribute_names_a_control_of_another_model(): void
    {
        $this->expectException(InvalidControlledByException::class);
        $this->expectExceptionMessage(sprintf(
            'The #[ControlledBy] attribute of the [%s] model names the [%s] control, which controls the [%s] model.',
            MislinkedNote::class,
            NoteControl::class,
            Note::class,
        ));

        Access::controlFor(MislinkedNote::class);
    }

    #[Test]
    public function it_throws_when_the_attribute_names_a_class_that_is_not_a_control(): void
    {
        $this->expectException(InvalidControlledByException::class);
        $this->expectExceptionMessage(sprintf(
            'The #[ControlledBy] attribute of the [%s] model names [%s], which is not a concrete control class.',
            HelpedNote::class,
            NoteHelper::class,
        ));

        Access::hasControl(HelpedNote::class);
    }

    #[Test]
    public function it_throws_when_the_attribute_names_an_abstract_control(): void
    {
        $this->expectException(InvalidControlledByException::class);
        $this->expectExceptionMessage(BaseControl::class);

        Access::hasControl(AbstractlyLinkedNote::class);
    }

    #[Test]
    public function it_keeps_one_instance_per_control_class(): void
    {
        $control = Access::controlFor(Post::class);

        $this->assertSame($control, Access::controlFor(new Post));
        $this->assertSame($control, Access::control(PostControl::class));
        $this->assertSame($control, PostControl::new());
        $this->assertSame($control, $this->app->make(ControlRegistry::class)->controlFor(Post::class));
    }

    #[Test]
    public function it_builds_a_control_outside_any_model_link_through_the_registry(): void
    {
        $control = NoteControl::new();

        $this->assertSame($control, NoteControl::new());
        $this->assertSame($control, Access::control(NoteControl::class));
        $this->assertFalse(Access::hasControl(Note::class));
    }

    #[Test]
    public function it_starts_each_container_with_a_new_registry(): void
    {
        $registry = $this->app->make(ControlRegistry::class);
        $control = PostControl::new();

        $this->assertSame($registry, $this->app->make(ControlRegistry::class));

        $this->refreshApplication();

        $this->assertNotSame($registry, $this->app->make(ControlRegistry::class));
        $this->assertNotSame($control, PostControl::new());
        $this->assertSame(PostControl::new(), Access::controlFor(Post::class));
    }

    #[Test]
    public function it_resolves_the_controls_through_the_container(): void
    {
        $control = new PostControl;

        $this->app->instance(PostControl::class, $control);

        $this->assertSame($control, Access::controlFor(Post::class));
    }

    #[Test]
    public function it_throws_when_the_container_resolves_a_control_as_another_class(): void
    {
        $this->app->bind(NoteControl::class, fn (): AdminNoteControl => new AdminNoteControl);

        $this->expectException(InvalidControlException::class);
        $this->expectExceptionMessage(sprintf(
            'The container resolved the [%s] control as [%s], which does not extend it.',
            NoteControl::class,
            AdminNoteControl::class,
        ));

        NoteControl::new();
    }

    #[Test]
    public function it_throws_when_asked_for_a_class_that_is_not_a_control(): void
    {
        $this->expectException(InvalidControlException::class);
        $this->expectExceptionMessage(sprintf('The [%s] class is not a concrete control class.', NoteHelper::class));

        Access::control(NoteHelper::class);
    }

    protected function fixturePath(string $directory): string
    {
        return (string) realpath(__DIR__.'/../../Fixtures/Discovery/'.$directory);
    }
}
