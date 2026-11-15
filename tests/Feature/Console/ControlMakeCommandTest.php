<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
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
        File::ensureDirectoryExists(app_path('Models'));
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
            app_path('Access/Controls/Control.php'),
            app_path('Access/Controls/ReviewControl.php'),
            app_path('Access/Controls/DigestControl.php'),
            app_path('Access/Controls/ArchiveControl.php'),
            app_path('Access/Controls/MemoControl.php'),
            app_path('Access/Controls/LedgerControl.php'),
            app_path('Access/Controls/CommentControl.php'),
            app_path('Access/Controls/AbilityControl.php'),
            app_path('Access/Controls/ForecastControl.php'),
            app_path('Access/Perimeters/AuditPerimeter.php'),
            app_path('Access/Perimeters/MemoPerimeter.php'),
            app_path('Models/Review.php'),
            app_path('Models/Archive.php'),
            app_path('Models/Memo.php'),
            app_path('Models/Ledger.php'),
            app_path('Models/Invoice.php'),
        ]);
    }

    protected function writeModel(string $name): void
    {
        File::put(app_path("Models/{$name}.php"), <<<PHP
            <?php

            namespace App\\Models;

            use Illuminate\\Database\\Eloquent\\Model;
            use Lomkit\\Access\\Controls\\HasControl;

            class {$name} extends Model
            {
                use HasControl;

                protected \$table = 'posts';
            }

            PHP);

        require_once app_path("Models/{$name}.php");
    }

    protected function assertValidPhp(string $path): void
    {
        $tokens = token_get_all(File::get($path), TOKEN_PARSE);

        $this->assertNotEmpty($tokens);
    }

    protected function reader(): User
    {
        return User::factory()->for(Client::factory())->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
    }

    #[Test]
    public function it_generates_callbacks_for_an_empty_generated_perimeter_that_load_without_error(): void
    {
        $this->writeModel('Review');
        $this->artisan('make:perimeter', ['name' => 'AuditPerimeter'])->assertOk();
        require_once app_path('Access/Perimeters/AuditPerimeter.php');

        $this->artisan('make:control', ['name' => 'ReviewControl', '--model' => 'Review', '--perimeters' => ['AuditPerimeter']])->assertOk();

        $source = File::get(app_path('Access/Controls/ReviewControl.php'));

        $this->assertStringContainsString("use Lomkit\\Access\\Ability;\n", $source);
        $this->assertStringContainsString("return [\n            \\App\\Access\\Perimeters\\AuditPerimeter::new()\n                ->allowed(function (Model \$user, Ability \$ability): bool {\n", $source);
        $this->assertStringContainsString("->should(function (Model \$user, Model \$model): bool {\n                    return false;\n                })", $source);
        $this->assertStringContainsString("->query(function (Builder \$query, Model \$user): void {\n                    \$query->whereRaw('0=1');\n                }),\n        ];", $source);
        $this->assertStringNotContainsString('string $method', $source);
        $this->assertStringNotContainsString('return $query', $source);
        $this->assertValidPhp(app_path('Access/Controls/ReviewControl.php'));

        require_once app_path('Access/Controls/ReviewControl.php');

        $control = Access::controlFor('App\\Models\\Review');
        $reader = $this->reader();
        $post = Post::factory()->for(Project::factory()->for($reader->client))->for($reader, 'author')->createOne(['title' => 'Draft']);

        $this->assertInstanceOf('App\\Access\\Controls\\ReviewControl', $control);
        $this->assertFalse($control->applies($reader, Ability::view(), $control->getModel()::query()->findOrFail($post->getKey())));
        $this->assertSame(0, $control->getModel()::controlledFor($reader)->count());
    }

    #[Test]
    public function it_generates_one_entry_per_perimeter(): void
    {
        $this->writeModel('Review');

        $this->artisan('make:control', ['name' => 'DigestControl', '--model' => 'Review', '--perimeters' => ['ReviewPerimeter', 'AuditPerimeter']])->assertOk();

        $source = File::get(app_path('Access/Controls/DigestControl.php'));

        $this->assertStringContainsString("return [\n            \\App\\Access\\Perimeters\\ReviewPerimeter::new()\n", $source);
        $this->assertStringContainsString("}),\n            \\App\\Access\\Perimeters\\AuditPerimeter::new()\n", $source);
        $this->assertValidPhp(app_path('Access/Controls/DigestControl.php'));
    }

    #[Test]
    public function it_names_the_model_after_the_control_without_the_model_option(): void
    {
        $this->writeModel('Archive');

        $this->artisan('make:control', ['name' => 'ArchiveControl'])->assertOk();

        $this->assertStringContainsString('protected string $model = \\App\\Models\\Archive::class;', File::get(app_path('Access/Controls/ArchiveControl.php')));
        $this->assertValidPhp(app_path('Access/Controls/ArchiveControl.php'));

        require_once app_path('Access/Controls/ArchiveControl.php');

        $this->assertInstanceOf('App\\Access\\Controls\\ArchiveControl', Access::controlFor('App\\Models\\Archive'));
    }

    #[Test]
    public function it_refuses_a_control_named_after_a_model_that_does_not_exist(): void
    {
        $this->artisan('make:control', ['name' => 'ForecastControl'])
            ->expectsOutputToContain('The [App\\Models\\Forecast] model is not an Eloquent model class.')
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Access/Controls/ForecastControl.php'));
    }

    #[Test]
    public function it_refuses_a_model_option_that_names_no_eloquent_model(): void
    {
        $this->artisan('make:control', ['name' => 'ForecastControl', '--model' => 'Quota'])
            ->expectsOutputToContain('The [App\\Models\\Quota] model is not an Eloquent model class.')
            ->assertFailed();
        $this->artisan('make:control', ['name' => 'AbilityControl', '--model' => Ability::class])
            ->expectsOutputToContain(sprintf('The [%s] model is not an Eloquent model class.', Ability::class))
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Access/Controls/ForecastControl.php'));
        $this->assertFileDoesNotExist(app_path('Access/Controls/AbilityControl.php'));
    }

    #[Test]
    public function it_accepts_a_fully_qualified_model_outside_the_application_namespace(): void
    {
        $this->artisan('make:control', ['name' => 'CommentControl', '--model' => 'Lomkit\\Access\\Tests\\Fixtures\\Blog\\Comment'])->assertOk();

        $this->assertStringContainsString(
            'protected string $model = \\Lomkit\\Access\\Tests\\Fixtures\\Blog\\Comment::class;',
            File::get(app_path('Access/Controls/CommentControl.php')),
        );
    }

    #[Test]
    public function it_generates_a_through_parent_entry_for_each_parent_before_the_perimeters(): void
    {
        $this->artisan('make:control', ['name' => 'CommentControl', '--model' => Comment::class, '--parent' => ['post', 'author'], '--perimeters' => ['AuditPerimeter']])->assertOk();

        $source = File::get(app_path('Access/Controls/CommentControl.php'));

        $this->assertStringContainsString("return [\n            \\Lomkit\\Access\\Perimeters\\ThroughParent::belongsTo('post'),\n            \\Lomkit\\Access\\Perimeters\\ThroughParent::belongsTo('author'),\n            \\App\\Access\\Perimeters\\AuditPerimeter::new()\n", $source);
        $this->assertValidPhp(app_path('Access/Controls/CommentControl.php'));
    }

    #[Test]
    public function it_generates_a_control_that_inherits_the_access_of_the_parent(): void
    {
        $this->artisan('make:control', ['name' => 'CommentControl', '--model' => Comment::class, '--parent' => ['post']])->assertOk();
        require_once app_path('Access/Controls/CommentControl.php');
        Gate::define('view own posts', fn (User $user): bool => true);
        $reader = $this->reader();
        $post = Post::factory()->for(Project::factory()->for($reader->client))->for($reader, 'author')->createOne(['title' => 'Draft', 'workspace_id' => $reader->current_workspace_id]);
        Comment::factory()->for($post)->for($reader, 'author')->createOne(['body' => 'Looks good to me']);
        Comment::factory()->createOne(['body' => 'Elsewhere']);

        $this->assertSame(['Looks good to me'], Comment::controlledFor($reader)->pluck('body')->all());
    }

    #[Test]
    public function it_refuses_a_parent_that_is_not_a_belongs_to_of_the_model(): void
    {
        foreach (['body', 'nothing', "post'), evil('"] as $relation) {
            $this->artisan('make:control', ['name' => 'CommentControl', '--model' => Comment::class, '--parent' => [$relation]])
                ->expectsOutputToContain(sprintf('The [%s] relation is not a belongsTo of the [%s] model.', $relation, Comment::class))
                ->assertFailed();
        }

        $this->artisan('make:control', ['name' => 'CommentControl', '--model' => Post::class, '--parent' => ['comments']])
            ->expectsOutputToContain(sprintf('The [comments] relation is not a belongsTo of the [%s] model.', Post::class))
            ->assertFailed();
        $this->assertFileDoesNotExist(app_path('Access/Controls/CommentControl.php'));
    }

    #[Test]
    public function it_generates_only_the_callbacks_of_the_hooks_a_perimeter_class_does_not_override(): void
    {
        $this->writeModel('Memo');
        $this->artisan('make:perimeter', ['name' => 'MemoPerimeter', '--hooks' => true])->assertOk();
        require_once app_path('Access/Perimeters/MemoPerimeter.php');

        $this->artisan('make:control', ['name' => 'MemoControl', '--model' => 'Memo', '--perimeters' => ['MemoPerimeter']])->assertOk();

        $source = File::get(app_path('Access/Controls/MemoControl.php'));

        $this->assertStringContainsString("return [\n            \\App\\Access\\Perimeters\\MemoPerimeter::new(),\n        ];", $source);
        $this->assertStringNotContainsString('scout', strtolower($source));
        $this->assertStringNotContainsString('->allowed(', $source);
        $this->assertStringNotContainsString('->should(', $source);
        $this->assertStringNotContainsString('->query(', $source);

        require_once app_path('Access/Controls/MemoControl.php');

        $control = Access::controlFor('App\\Models\\Memo');

        $this->assertSame(0, $control->getModel()::controlledFor($this->reader())->count());
    }

    #[Test]
    public function it_extends_the_base_control_of_the_application_when_there_is_one(): void
    {
        $this->writeModel('Review');
        File::put(app_path('Access/Controls/Control.php'), '');

        $this->artisan('make:control', ['name' => 'LedgerControl', '--model' => 'Review'])->assertOk();

        $source = File::get(app_path('Access/Controls/LedgerControl.php'));

        $this->assertStringContainsString('class LedgerControl extends Control', $source);
        $this->assertStringNotContainsString('use Lomkit\\Access\\Controls\\Control;', $source);
        $this->assertStringContainsString('use Lomkit\\Access\\Ability;', $source);
    }

    #[Test]
    public function it_asks_for_the_model_when_no_option_is_given(): void
    {
        $this->writeModel('Ledger');
        $this->writeModel('Invoice');

        $this->artisan('make:control')
            ->expectsQuestion('What should the control be named?', 'LedgerControl')
            ->expectsChoice('What model should this control apply to?', 'Ledger', ['Invoice', 'Ledger'])
            ->assertOk();

        $this->assertStringContainsString('protected string $model = \\App\\Models\\Ledger::class;', File::get(app_path('Access/Controls/LedgerControl.php')));
    }
}
