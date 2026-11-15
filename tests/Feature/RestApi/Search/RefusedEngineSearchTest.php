<?php

namespace Lomkit\Access\Tests\Feature\RestApi\Search;

use Laravel\Scout\EngineManager;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedRestSearchException;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\TrustedSearchEngines;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\InMemoryWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\FakeTurbopufferEngine;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\MemoryProfileExtension;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\SnippetResource;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\ThreadResource;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use Lomkit\Access\Tests\Fixtures\Search\Snippet;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredThreadControl;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use Lomkit\Rest\Text\Engine\Profiles\MatchesStringLists;
use Lomkit\Rest\Text\Engine\Profiles\TurbopufferProfile;
use Orchestra\Testbench\Attributes\DefineRoute;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
#[Group('scout')]
class RefusedEngineSearchTest extends RestApiSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant(['view client threads' => ['Ada'], 'view global snippets' => ['Ada']]);
        $this->withoutExceptionHandling();
    }

    #[\Override]
    protected function tearDown(): void
    {
        MemoryProfileExtension::$matchesStringLists = true;

        parent::tearDown();
    }

    #[Test]
    public function it_refuses_an_engine_that_the_package_does_not_trust(): void
    {
        $this->useMemoryEngine();
        $this->app->forgetInstance(TrustedSearchEngines::class);

        $this->expectExceptionObject(UnsupportedRestSearchException::untrustedEngine(ThreadResource::class, $this->engine, AccessControlExtension::class));

        $this->textSearch('threads', $this->ada, 'o');
    }

    #[Test]
    public function it_refuses_an_engine_whose_profile_matches_no_list_of_strings(): void
    {
        MemoryProfileExtension::$matchesStringLists = false;
        $this->useMemoryEngine();

        $this->expectExceptionObject(UnsupportedRestSearchException::withoutStringLists(ThreadResource::class, $this->engine, AccessControlExtension::class));

        $this->textSearch('threads', $this->ada, 'o');
    }

    #[Test]
    #[DefineRoute('defineSnippetRoutes')]
    public function it_refuses_a_controlled_model_without_the_controlled_searchable_trait(): void
    {
        Access::discoverControlsIn([dirname(__DIR__, 3).'/Fixtures/RestApi/Search/Controls']);
        Snippet::query()->create(['title' => 'Onboarding']);

        $this->expectExceptionObject(UnsupportedRestSearchException::notControlledSearchable(SnippetResource::class, Snippet::class, AccessControlExtension::class));

        $this->textSearch('snippets', $this->ada, 'o');
    }

    #[Test]
    public function it_refuses_a_boundary_without_a_search_form(): void
    {
        $this->grant(['view global threads' => ['Ada']]);
        ConfiguredThreadControl::bindWithin(
            new InMemoryWorkspaceBoundary,
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global threads"))
                ->unrestricted(),
        );

        $this->expectException(UnsearchableBoundaryException::class);

        $this->textSearch('threads', $this->ada, 'o');
    }

    #[Test]
    public function it_filters_turbopuffer_by_the_access_keys_once_its_profile_matches_lists_of_strings(): void
    {
        if (! is_subclass_of(TurbopufferProfile::class, MatchesStringLists::class)) {
            $this->markTestSkipped('The Turbopuffer profile of laravel-rest-api 3.x does not match lists of strings yet: it gains native ContainsAny / NotContainsAny before the 3.6.0 tag.');
        }

        $engine = new FakeTurbopufferEngine;
        $this->app->make(EngineManager::class)->extend('memory', fn (): FakeTurbopufferEngine => $engine);
        $this->app->make(EngineManager::class)->forgetDrivers();

        $this->textSearch('threads', $this->ada, 'o')->assertOk();

        $this->assertSame([SearchField::Boundary->value => ['workspace_id.1'], SearchField::Access->value => ['client.'.$this->acme->getKey()]], $engine->searches[0]->whereIns);
    }

    private function useMemoryEngine(): void
    {
        config(['rest.extensions' => [MemoryProfileExtension::class]]);
        $engine = $this->engine;
        $this->app->make(EngineManager::class)->extend('memory', fn (): MemoryEngine => $engine);
        $this->app->make(EngineManager::class)->forgetDrivers();
        $this->assertInstanceOf(MemoryEngine::class, (new Thread)->searchableUsing());
    }
}
