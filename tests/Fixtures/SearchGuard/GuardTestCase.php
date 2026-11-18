<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuard;

use Illuminate\Support\Facades\File;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Lomkit\Access\Access;
use Lomkit\Access\Search\Tokens\Syntax\FilterSyntax;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;

abstract class GuardTestCase extends SearchTestCase
{
    protected User $ada;

    /** @return class-string<Engine> */
    abstract protected function engineClass(): string;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::delete([$this->app->bootstrapPath('cache/access-controls.php'), $this->app->bootstrapPath('cache/access-reindex.php')]);
        Access::discoverControlsIn([__DIR__]);
        $this->ada = $this->user('Ada');
        $this->allow(['view own articles']);
        $this->useEngine($this->createStub($this->engineClass()));
    }

    protected function useEngine(Engine $engine): void
    {
        $this->app->make(EngineManager::class)->extend('guarded', fn (): Engine => $engine);
        config(['scout.driver' => 'guarded']);
    }

    protected function index(): string
    {
        return (new GuardedArticle)->searchableAs();
    }

    protected function accessFilter(FilterSyntax $syntax, ?User $reader): ?string
    {
        return $syntax->render(Access::controlFor(GuardedArticle::class)->searchFilter($reader));
    }
}
