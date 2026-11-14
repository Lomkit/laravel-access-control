<?php

namespace Lomkit\Access\Tests\Feature\Registry;

use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidControlModelException;
use Lomkit\Access\Tests\Fixtures\Discovery\Invalid\ClasslessControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Invalid\ModellessControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Invalid\StdClassControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\ModellessNote;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class ControlModelGuardTest extends PackageTestCase
{
    #[Test]
    public function it_throws_for_a_control_that_declares_no_model(): void
    {
        $this->expectException(InvalidControlModelException::class);
        $this->expectExceptionMessage(sprintf('The [%s] control declares no model.', ModellessControl::class));

        ModellessControl::new();
    }

    #[Test]
    public function it_throws_for_a_control_whose_model_is_not_a_class(): void
    {
        $this->expectException(InvalidControlModelException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] control declares [%s] as its model, which is not a class.',
            ClasslessControl::class,
            'Lomkit\Access\Tests\Fixtures\Discovery\Models\Missing',
        ));

        Access::control(ClasslessControl::class);
    }

    #[Test]
    public function it_throws_for_a_control_whose_model_is_not_an_eloquent_model(): void
    {
        $this->expectException(InvalidControlModelException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] control declares [%s] as its model, which is not an Eloquent model.',
            StdClassControl::class,
            stdClass::class,
        ));

        StdClassControl::new();
    }

    #[Test]
    public function it_throws_for_a_model_whose_attribute_names_a_control_without_model(): void
    {
        $this->expectException(InvalidControlModelException::class);
        $this->expectExceptionMessage(sprintf('The [%s] control declares no model.', ModellessControl::class));

        Access::controlFor(ModellessNote::class);
    }

    #[Test]
    public function it_throws_when_the_discovery_finds_a_control_without_a_valid_model(): void
    {
        Access::discoverControlsIn([(string) realpath(__DIR__.'/../../Fixtures/Discovery/Invalid')]);

        $this->expectException(InvalidControlModelException::class);
        $this->expectExceptionMessage(ClasslessControl::class);

        Access::hasControl(Note::class);
    }
}
