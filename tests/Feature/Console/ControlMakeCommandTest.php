<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ControlMakeCommandTest extends PackageTestCase
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

        File::ensureDirectoryExists(app_path('Access/Controls'));
        File::ensureDirectoryExists(app_path('Access/Perimeters'));
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
        File::delete([
            app_path('Access/Controls/ReviewControl.php'),
            app_path('Access/Perimeters/ReviewPerimeter.php'),
            app_path('Access/Controls/ArchiveControl.php'),
            app_path('Models/Archive.php'),
        ]);
    }

    #[Test]
    public function it_generates_perimeter_callbacks_that_constrain_the_builder_they_receive(): void
    {
        $this->artisan('make:perimeter', ['name' => 'ReviewPerimeter'])->assertOk();
        $this->artisan('make:control', ['name' => 'ReviewControl', '--model' => 'Post', '--perimeters' => ['ReviewPerimeter']])->assertOk();

        $source = File::get(app_path('Access/Controls/ReviewControl.php'));

        $this->assertStringContainsString("->query(function (Builder \$query, Model \$user): void {\n                    \$query->whereRaw('0=1');\n                })", $source);
        $this->assertStringContainsString("->scoutQuery(function (\\Laravel\\Scout\\Builder \$query, Model \$user): void {\n                    \$query->where('__NOT_A_VALID_FIELD__', 0);\n                })", $source);
        $this->assertStringNotContainsString('return $query', $source);

        require_once app_path('Access/Perimeters/ReviewPerimeter.php');
        require_once app_path('Access/Controls/ReviewControl.php');

        /** @var Control $control */
        $control = new ('App\\Access\\Controls\\ReviewControl');
        $perimeter = $control->getPerimeters()[0];
        $reader = User::factory()->createOne();
        $query = Post::query();

        $this->assertFalse($perimeter->applyAllowedCallback($reader, 'view'));
        $this->assertFalse($perimeter->applyShouldCallback($reader, new Post));
        $this->assertSame($query, $perimeter->applyQueryCallback($query, $reader));
        $this->assertSame([['type' => 'raw', 'sql' => '0=1', 'boolean' => 'and']], $query->getQuery()->wheres);
    }

    #[Test]
    public function it_generates_one_entry_per_perimeter(): void
    {
        $this->artisan('make:control', ['name' => 'ReviewControl', '--model' => 'Post', '--perimeters' => ['ReviewPerimeter', 'AuditPerimeter']])->assertOk();

        $source = File::get(app_path('Access/Controls/ReviewControl.php'));

        $this->assertStringContainsString("return [\n            \\App\\Access\\Perimeters\\ReviewPerimeter::new()\n", $source);
        $this->assertStringContainsString("}),\n            \\App\\Access\\Perimeters\\AuditPerimeter::new()\n", $source);
    }

    #[Test]
    public function it_generates_a_control_that_the_discovery_links_to_its_model(): void
    {
        File::ensureDirectoryExists(app_path('Models'));
        File::put(app_path('Models/Archive.php'), "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\nuse Lomkit\\Access\\Controls\\HasControl;\n\nclass Archive extends Model\n{\n    use HasControl;\n}\n");

        $this->artisan('make:control', ['name' => 'ArchiveControl', '--model' => 'Archive'])->assertOk()->run();

        require_once app_path('Models/Archive.php');
        require_once app_path('Access/Controls/ArchiveControl.php');

        $this->assertStringContainsString('protected string $model = \\App\\Models\\Archive::class;', File::get(app_path('Access/Controls/ArchiveControl.php')));
        $this->assertInstanceOf('App\\Access\\Controls\\ArchiveControl', Access::controlFor('App\\Models\\Archive'));
    }
}
