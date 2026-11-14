<?php

namespace Lomkit\Access\Tests\Feature\Testing;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Agencies\Agencies;
use Lomkit\Access\Tests\Fixtures\Agencies\Agency;
use Lomkit\Access\Tests\Fixtures\Agencies\Member;
use Lomkit\Access\Tests\Fixtures\Agencies\Mission;
use Lomkit\Access\Tests\Fixtures\Agencies\MissionControl;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\UserControl;
use Lomkit\Access\Tests\Fixtures\Testing\AccessTestCase;
use PHPUnit\Framework\Attributes\Test;

class ControlDifferentialTest extends AccessTestCase
{
    protected const array ABILITIES = ['view', 'update', 'delete', 'approve'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Agencies];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::registerPolicies();
    }

    protected function code(User|Member $reader, string $ability, mixed $row): string
    {
        $response = Gate::forUser($reader)->inspect($ability, $row);

        return $response->allowed() ? 'granted' : (string) $response->code();
    }

    /** @param  array<string, list<string>>  $permissions */
    protected function grant(array $permissions): void
    {
        foreach ($permissions as $permission => $names) {
            Gate::define($permission, fn (User|Member $reader): bool => in_array($reader->getAttribute('name'), $names, true));
        }
    }

    #[Test]
    public function it_agrees_on_the_posts_for_every_reader_of_the_blog(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com', 'current_workspace_id' => 1]);
        $linus = User::factory()->for($globex)->createOne(['name' => 'Linus', 'email' => 'linus@example.com', 'current_workspace_id' => 1]);
        $root = User::factory()->for($globex)->createOne(['name' => 'Root', 'email' => 'root@example.com', 'current_workspace_id' => 2]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $portal = Project::factory()->for($globex)->createOne(['name' => 'Portal']);
        Post::factory()->for($website)->for($ada, 'author')->createOne(['title' => 'Release notes', 'workspace_id' => 1]);
        Post::factory()->for($website)->for($grace, 'author')->createOne(['title' => 'Roadmap', 'workspace_id' => 1]);
        Post::factory()->for($website)->for($ada, 'author')->createOne(['title' => 'Archive', 'workspace_id' => 1, 'archived_at' => '2026-01-01 00:00:00']);
        Post::factory()->for($website)->for($ada, 'author')->createOne(['title' => 'Elsewhere', 'workspace_id' => 2]);
        Post::factory()->for($portal)->for($linus, 'author')->createOne(['title' => 'Pricing', 'workspace_id' => 1])->sharedWith()->attach($grace);
        Post::factory()->for($portal)->for($root, 'author')->createOne(['title' => 'Budget', 'workspace_id' => 2]);
        $this->grant([
            'view global posts' => ['Root'],
            'update global posts' => ['Root'],
            'view client posts' => ['Ada', 'Linus'],
            'update client posts' => ['Linus'],
            'view own posts' => ['Ada', 'Grace'],
            'update own posts' => ['Ada', 'Grace'],
            'delete own posts' => ['Ada'],
            'approve own posts' => ['Grace'],
            'view shared posts' => ['Grace'],
            'update shared posts' => ['Grace'],
        ]);
        $posts = Post::uncontrolled()->orderBy('id')->get();

        foreach ([$ada, $grace, $linus, $root] as $reader) {
            $this->assertControlAgrees(PostControl::class, $reader, $posts, self::ABILITIES);
        }

        $this->assertSame(
            ['granted', 'outside_boundary', 'not_found', 'outside_rows', 'no_perimeter'],
            [
                $this->code($ada, 'update', $posts[0]),
                $this->code($ada, 'update', $posts[2]),
                $this->code($ada, 'update', $posts[3]),
                $this->code($grace, 'update', $posts[0]),
                $this->code($linus, 'delete', $posts[4]),
            ],
        );
    }

    #[Test]
    public function it_agrees_on_the_users_of_the_blog(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        User::factory()->for($globex)->createOne(['name' => 'Linus', 'email' => 'linus@example.com']);
        $this->grant([
            'view client users' => ['Ada'],
            'view own users' => ['Grace'],
            'update own users' => ['Ada', 'Grace'],
        ]);

        foreach ([$ada, $grace] as $reader) {
            $this->assertControlAgrees(UserControl::class, $reader, User::query()->orderBy('id')->get());
        }
    }

    #[Test]
    public function it_agrees_on_the_missions_of_the_agencies(): void
    {
        $north = Agency::factory()->createOne(['name' => 'North']);
        $south = Agency::factory()->createOne(['name' => 'South']);
        $margaret = Member::factory()->for($north)->createOne(['name' => 'Margaret', 'email' => 'margaret@example.com']);
        $linus = Member::factory()->for($north)->createOne(['name' => 'Linus', 'email' => 'linus@example.com']);
        $ken = Member::factory()->for($south)->createOne(['name' => 'Ken', 'email' => 'ken@example.com']);
        $hedy = Member::factory()->for($north)->createOne(['name' => 'Hedy', 'email' => 'hedy@example.com']);
        Mission::factory()->for($north)->for($linus)->createOne(['title' => 'Survey']);
        Mission::factory()->for($north)->for($linus)->createOne(['title' => 'Closed survey', 'closed_at' => '2026-01-01 00:00:00']);
        Mission::factory()->for($north)->for($margaret)->createOne(['title' => 'Open day', 'public' => true]);
        Mission::factory()->for($north)->for($margaret)->createOne(['title' => 'Audit']);
        Mission::factory()->for($south)->for($ken)->createOne(['title' => 'Harbour', 'public' => true]);
        $this->grant([
            'view every mission' => ['Margaret'],
            'update every mission' => ['Margaret'],
            'view assigned missions' => ['Linus', 'Ken'],
            'update assigned missions' => ['Linus', 'Ken'],
            'delete assigned missions' => ['Linus'],
            'view public missions' => ['Linus', 'Hedy', 'Ken'],
            'update public missions' => ['Hedy'],
        ]);
        $missions = Mission::uncontrolled()->orderBy('id')->get();

        foreach ([$margaret, $linus, $ken, $hedy] as $reader) {
            $this->assertControlAgrees(MissionControl::class, $reader, $missions, ['view', 'update', 'delete']);
        }

        $this->assertSame(
            ['granted', 'granted', 'outside_boundary', 'not_found', 'granted', 'outside_rows'],
            [
                $this->code($margaret, 'update', $missions[0]),
                $this->code($linus, 'update', $missions[0]),
                $this->code($linus, 'update', $missions[1]),
                $this->code($ken, 'view', $missions[0]),
                $this->code($hedy, 'update', $missions[2]),
                $this->code($hedy, 'update', $missions[3]),
            ],
        );
    }
}
