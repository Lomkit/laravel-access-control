<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
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
    public function it_generates_a_perimeter_that_overrides_the_hooks_with_the_access_context(): void
    {
        $this->artisan('make:perimeter', ['name' => 'LedgerPerimeter'])->assertOk();

        $source = File::get(app_path('Access/Perimeters/LedgerPerimeter.php'));

        $this->assertStringContainsString("use Lomkit\\Access\\AccessContext;\n", $source);
        $this->assertStringContainsString("protected function allows(AccessContext \$context): bool\n    {\n        return false;\n    }", $source);
        $this->assertStringContainsString("protected function includes(Model \$row, AccessContext \$context): bool\n    {\n        return false;\n    }", $source);
        $this->assertStringContainsString("protected function scope(Builder \$query, AccessContext \$context): void\n    {\n        \$query->whereRaw('0=1');\n    }", $source);

        require_once app_path('Access/Perimeters/LedgerPerimeter.php');

        $perimeter = ('App\\Access\\Perimeters\\LedgerPerimeter')::new();
        $query = Post::query();
        $perimeter->applyQueryCallback($query, AccessContext::of(new User, Ability::view()));

        $this->assertInstanceOf(Perimeter::class, $perimeter);
        $this->assertFalse($perimeter->admits(new User, Ability::view(), new Post));
        $this->assertSame([['type' => 'raw', 'sql' => '0=1', 'boolean' => 'and']], $query->getQuery()->wheres);
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
