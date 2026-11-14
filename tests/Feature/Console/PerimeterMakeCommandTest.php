<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PerimeterMakeCommandTest extends PackageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::delete(app_path('Access/Perimeters/LedgerPerimeter.php'));
    }

    #[\Override]
    protected function tearDown(): void
    {
        File::delete(app_path('Access/Perimeters/LedgerPerimeter.php'));

        parent::tearDown();
    }

    #[Test]
    public function it_generates_a_plain_perimeter(): void
    {
        $this->artisan('make:perimeter', ['name' => 'LedgerPerimeter'])->assertOk();

        $this->assertStringContainsString('class LedgerPerimeter extends Perimeter', File::get(app_path('Access/Perimeters/LedgerPerimeter.php')));
    }

    #[Test]
    public function it_generates_an_overlay_perimeter(): void
    {
        $this->artisan('make:perimeter', ['name' => 'LedgerPerimeter', '--overlay' => true])->assertOk();

        $this->assertStringContainsString('class LedgerPerimeter extends OverlayPerimeter', File::get(app_path('Access/Perimeters/LedgerPerimeter.php')));
    }

    #[Test]
    public function it_asks_whether_the_perimeter_overlays_when_no_option_is_given(): void
    {
        $this->artisan('make:perimeter')
            ->expectsQuestion('What should the perimeter be named?', 'LedgerPerimeter')
            ->expectsConfirmation('Should this perimeter be an overlay?', 'yes')
            ->assertOk();

        $this->assertStringContainsString('class LedgerPerimeter extends OverlayPerimeter', File::get(app_path('Access/Perimeters/LedgerPerimeter.php')));
    }
}
