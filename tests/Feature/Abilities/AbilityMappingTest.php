<?php

namespace Lomkit\Access\Tests\Feature\Abilities;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RemappedPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AbilityMappingTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    /** @return array<string, array{string, string}> */
    public static function policyMethods(): array
    {
        return [
            'viewAny' => ['viewAny', 'view'],
            'view' => ['view', 'view'],
            'create' => ['create', 'create'],
            'update' => ['update', 'update'],
            'delete' => ['delete', 'delete'],
            'restore' => ['restore', 'restore'],
            'forceDelete' => ['forceDelete', 'forceDelete'],
            'a business method' => ['approve', 'approve'],
            'another business method' => ['attach', 'attach'],
        ];
    }

    #[Test]
    #[DataProvider('policyMethods')]
    public function it_maps_each_policy_method_to_its_ability(string $policyMethod, string $ability): void
    {
        $this->assertTrue(Access::control(PostControl::class)->abilityFor($policyMethod)->is($ability));
    }

    #[Test]
    public function it_refuses_a_policy_method_that_is_not_an_ability_name(): void
    {
        $this->expectException(InvalidAbilityException::class);
        $this->expectExceptionMessage('The [approve post] ability name contains whitespace.');

        Access::control(PostControl::class)->abilityFor('approve post');
    }

    #[Test]
    public function it_lets_a_control_override_the_mapping(): void
    {
        $control = RemappedPostControl::bind();

        $this->assertTrue($control->abilityFor('viewAny')->is('browse'));
        $this->assertTrue($control->abilityFor('approve')->is(Ability::update()));
        $this->assertTrue($control->abilityFor('view')->is(Ability::view()));
        $this->assertTrue($control->abilityFor('archive')->is('archive'));
    }

    #[Test]
    public function it_decides_with_the_overridden_mapping_through_the_policy(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $releaseNotes = Post::factory()->for($website)->for($ada, 'author')->createOne(['title' => 'Release notes']);
        $received = [];
        RemappedPostControl::bind(
            GlobalPerimeter::new()
                ->allowed(function (User $user, Ability $ability) use (&$received): bool {
                    $received[] = $ability->name();

                    return $user->can("{$ability} global posts");
                })
                ->unrestricted(),
        );
        Gate::define('browse global posts', fn (User $user): bool => true);
        Gate::define('update global posts', fn (User $user): bool => true);

        $this->assertTrue($ada->can('viewAny', Post::class));
        $this->assertFalse($ada->can('view', $releaseNotes));
        $this->assertTrue($ada->can('approve', $releaseNotes));
        $this->assertSame(['browse', 'view', 'update'], $received);
    }
}
