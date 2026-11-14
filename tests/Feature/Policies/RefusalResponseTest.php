<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RefusalResponseTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

    protected Post $roadmap;

    protected Post $archive;

    protected Post $elsewhere;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com', 'current_workspace_id' => 1]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->releaseNotes = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes', 'workspace_id' => 1]);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap', 'workspace_id' => 1]);
        $this->archive = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Archive', 'workspace_id' => 1, 'archived_at' => '2026-01-01 00:00:00']);
        $this->elsewhere = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Elsewhere', 'workspace_id' => 2]);
    }

    /** @param  list<string>  $permissions */
    protected function allow(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Gate::define($permission, fn (User $user): bool => true);
        }
    }

    protected function inspect(string $ability, Post|string $subject): Response
    {
        return Gate::forUser($this->ada)->inspect($ability, $subject);
    }

    #[Test]
    public function it_allows_a_granted_row_without_a_code(): void
    {
        $this->allow(['update client posts']);

        $response = $this->inspect('update', $this->releaseNotes);

        $this->assertTrue($response->allowed());
        $this->assertNull($response->code());
        $this->assertNull($response->message());
    }

    #[Test]
    public function it_refuses_a_reader_without_a_perimeter(): void
    {
        $response = $this->inspect('update', $this->releaseNotes);

        $this->assertTrue($response->denied());
        $this->assertSame('no_perimeter', $response->code());
        $this->assertSame('You are not allowed to perform this action.', $response->message());
        $this->assertNull($response->status());
        $this->assertSame('no_perimeter', $this->inspect('viewAny', Post::class)->code());
    }

    #[Test]
    public function it_refuses_a_row_outside_the_perimeters(): void
    {
        $this->allow(['update client posts']);

        $response = $this->inspect('update', $this->roadmap);

        $this->assertSame('outside_rows', $response->code());
        $this->assertSame('You are not allowed to perform this action on this record.', $response->message());
        $this->assertNull($response->status());
    }

    #[Test]
    public function it_refuses_a_readable_row_outside_the_boundary_with_a_403(): void
    {
        $this->allow(['update own posts', 'view own posts']);

        $response = $this->inspect('update', $this->archive);

        $this->assertSame('outside_boundary', $response->code());
        $this->assertSame('This record is not available for this action.', $response->message());
        $this->assertNull($response->status());
        $this->assertTrue($this->inspect('view', $this->archive)->allowed());
    }

    #[Test]
    public function it_hides_a_row_that_the_boundary_also_refuses_to_view_with_a_404(): void
    {
        $this->allow(['update global posts']);

        $response = $this->inspect('update', $this->elsewhere);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
        $this->assertSame('not_found', $response->code());
        $this->assertSame('Not found.', $response->message());
    }

    #[Test]
    public function it_hides_a_row_outside_the_boundary_from_view_with_a_404(): void
    {
        $this->allow(['view global posts']);

        $response = $this->inspect('view', $this->elsewhere);

        $this->assertSame(404, $response->status());
        $this->assertSame('not_found', $response->code());
    }

    #[Test]
    public function it_hides_the_row_even_without_the_view_permission(): void
    {
        $this->allow(['update global posts']);

        $this->assertSame(404, $this->inspect('update', $this->elsewhere)->status());
        $this->assertFalse($this->ada->can('view', $this->elsewhere));
    }

    #[Test]
    public function it_chooses_between_404_and_403_with_a_boundary_checked_in_sql(): void
    {
        ConfiguredPostControl::bindWithin(
            new WorkspaceBoundary,
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->unrestricted(),
            ),
        );

        DB::enableQueryLog();
        $archived = $this->inspect('update', $this->archive);
        $elsewhere = $this->inspect('update', $this->elsewhere);
        DB::disableQueryLog();

        $this->assertSame('outside_boundary', $archived->code());
        $this->assertNull($archived->status());
        $this->assertSame('not_found', $elsewhere->code());
        $this->assertSame(404, $elsewhere->status());
        $this->assertCount(4, DB::getQueryLog());
    }

    #[Test]
    public function it_refuses_an_unsaved_model(): void
    {
        $response = $this->inspect('update', new Post);

        $this->assertSame('unsaved', $response->code());
        $this->assertSame('This record does not exist yet.', $response->message());
        $this->assertNull($response->status());
    }

    #[Test]
    public function it_checks_the_permission_before_the_boundary(): void
    {
        $this->assertSame('no_perimeter', $this->inspect('update', $this->elsewhere)->code());
    }

    #[Test]
    public function it_checks_the_boundary_before_the_rows(): void
    {
        $this->allow(['update client posts', 'view client posts']);

        $this->assertSame('outside_boundary', $this->inspect('update', $this->archive)->code());
        $this->assertSame('not_found', $this->inspect('update', $this->elsewhere)->code());
        $this->assertSame('outside_rows', $this->inspect('update', $this->roadmap)->code());
    }

    #[Test]
    public function it_translates_the_messages(): void
    {
        $this->allow(['update client posts', 'update own posts']);
        $this->app->setLocale('fr');

        $this->assertSame('Vous n\'êtes pas autorisé à effectuer cette action.', $this->inspect('delete', $this->releaseNotes)->message());
        $this->assertSame('Vous n\'êtes pas autorisé à effectuer cette action sur cet élément.', $this->inspect('update', $this->roadmap)->message());
        $this->assertSame('Cet élément n\'est pas disponible pour cette action.', $this->inspect('update', $this->archive)->message());
        $this->assertSame('Introuvable.', $this->inspect('update', $this->elsewhere)->message());
        $this->assertSame('Cet élément n\'existe pas encore.', $this->inspect('update', new Post)->message());
    }

    #[Test]
    public function it_reads_the_messages_that_the_application_overrides(): void
    {
        $this->allow(['update client posts']);
        $this->app['translator']->addLines(['refusals.outside_rows' => 'This post belongs to another client.'], 'en', 'access-control');

        $this->assertSame('This post belongs to another client.', $this->inspect('update', $this->roadmap)->message());
    }

    #[Test]
    public function it_throws_an_authorization_exception_with_the_status_and_the_code(): void
    {
        $this->allow(['update client posts']);

        try {
            Gate::forUser($this->ada)->authorize('update', $this->elsewhere);
            $this->fail('The update was authorized.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
            $this->assertSame('not_found', $exception->response()?->code());
        }

        try {
            Gate::forUser($this->ada)->authorize('update', $this->roadmap);
            $this->fail('The update was authorized.');
        } catch (AuthorizationException $exception) {
            $this->assertNull($exception->status());
            $this->assertSame('outside_rows', $exception->response()?->code());
        }
    }
}
