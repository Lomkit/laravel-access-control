<?php

namespace Lomkit\Access\Tests\Feature\RestApi\Search;

use Illuminate\Support\Facades\Artisan;
use Laravel\Scout\EngineManager;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Search\TrustedSearchEngines;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\InMemoryWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\MemoryProfileExtension;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\SnippetResource;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\ThreadResource;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use Lomkit\Access\Tests\Fixtures\Search\Snippet;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredThreadControl;
use Orchestra\Testbench\Attributes\DefineRoute;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
#[Group('scout')]
class SearchRestCheckTest extends RestApiSearchTestCase
{
    #[Test]
    public function it_finds_no_problem_in_resources_searched_on_a_trusted_engine(): void
    {
        [$code, $report] = $this->restCheck();

        $this->assertSame([], $report['extension_checks']['problems']);
        $this->assertSame([], $report['rows']['problems']);
        $this->assertSame(0, $code, json_encode($report['problems'], JSON_THROW_ON_ERROR));
    }

    #[Test]
    #[DefineRoute('defineSnippetRoutes')]
    public function it_reports_a_controlled_model_without_the_controlled_searchable_trait(): void
    {
        Access::discoverControlsIn([dirname(__DIR__, 3).'/Fixtures/RestApi/Search/Controls']);

        [, $report] = $this->restCheck();

        $this->assertSame([$this->problem(SnippetResource::class, sprintf('its controlled [%s] model does not use the ControlledSearchable trait, so its text searches on the [%s] engine are refused. Use ControlledSearchable on the model and reindex it.', Snippet::class, $this->index::class))], $report['extension_checks']['problems']);
    }

    #[Test]
    public function it_reports_an_engine_that_the_package_does_not_trust(): void
    {
        config(['rest.extensions' => [MemoryProfileExtension::class]]);
        $engine = $this->engine;
        $this->app->make(EngineManager::class)->extend('memory', fn (): MemoryEngine => $engine);
        $this->app->make(EngineManager::class)->forgetDrivers();
        $this->app->forgetInstance(TrustedSearchEngines::class);

        [, $report] = $this->restCheck();

        $this->assertContains($this->problem(ThreadResource::class, sprintf('the [%1$s] engine is not trusted to match the access keys, so its text searches are refused. If it matches lists of strings, call Access::trustSearchEngine(%1$s::class) in a service provider.', MemoryEngine::class)), $report['extension_checks']['problems']);
    }

    #[Test]
    public function it_reports_a_boundary_without_a_search_form(): void
    {
        $control = ConfiguredThreadControl::bindWithin(
            new InMemoryWorkspaceBoundary,
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global threads"))
                ->unrestricted(),
        );

        [, $report] = $this->restCheck();

        $this->assertContains($this->problem(ThreadResource::class, sprintf('the boundary of the [%s] control has no search form, so its text searches on the [%s] engine are refused. Declare the boundary with Boundary::where(), or return its search keys from searchKeys().', $control::class, $this->index::class)), $report['extension_checks']['problems']);
    }

    /** @return array{int, array<string, mixed>} */
    private function restCheck(): array
    {
        $code = Artisan::call('rest:check', ['--json' => true]);

        return [$code, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
    }

    private function problem(string $resource, string $message): string
    {
        return sprintf('The [%s] extension %s reports %s: %s', AccessControlExtension::name(), AccessControlExtension::class, $resource, $message);
    }
}
