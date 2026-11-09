<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Support\Registry\Controls\Admin\PostControl as AdminPostControl;
use Lomkit\Access\Tests\Support\Registry\Controls\BaseControl;
use Lomkit\Access\Tests\Support\Registry\Controls\PostControl;
use Lomkit\Access\Tests\Support\Registry\Controls\PostHelper;
use Lomkit\Access\Tests\Support\Registry\Models\Admin\Post as AdminPost;
use Lomkit\Access\Tests\Support\Registry\Models\Post;

class AccessRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        @unlink(Access::cachePath());

        Access::flush();

        Access::$controlDiscoveryPaths = [$this->controlsPath()];
    }

    protected function tearDown(): void
    {
        @unlink(Access::cachePath());

        Access::flush();

        Access::$controlDiscoveryPaths = null;

        parent::tearDown();
    }

    /**
     * Get the directory holding the registry control fixtures.
     */
    protected function controlsPath(): string
    {
        return realpath(__DIR__.'/../Support/Registry/Controls');
    }

    public function test_controls_with_the_same_short_name_are_both_registered(): void
    {
        $this->assertInstanceOf(PostControl::class, Access::controlForModel(Post::class));
        $this->assertInstanceOf(AdminPostControl::class, Access::controlForModel(AdminPost::class));
        $this->assertInstanceOf(PostControl::class, (new Post)->newControl());
        $this->assertInstanceOf(AdminPostControl::class, (new AdminPost)->newControl());
        $this->assertSame(Post::class, Access::control(PostControl::class)->getModel());
        $this->assertSame(AdminPost::class, Access::control(AdminPostControl::class)->getModel());
    }

    public function test_discovery_reads_the_namespace_of_controls_outside_the_app_namespace(): void
    {
        $this->assertEqualsCanonicalizing(
            [PostControl::class, AdminPostControl::class],
            Access::discoverControlClasses([$this->controlsPath()])
        );
    }

    public function test_discovery_ignores_abstract_and_non_control_classes(): void
    {
        $classes = Access::discoverControlClasses([$this->controlsPath()]);

        $this->assertNotContains(BaseControl::class, $classes);
        $this->assertNotContains(PostHelper::class, $classes);
    }

    public function test_discovery_paths_set_after_the_provider_registration_are_honoured(): void
    {
        Access::$controlDiscoveryPaths = [];

        $this->assertNull(Access::controlForModel(Post::class));

        Access::flush();

        Access::$controlDiscoveryPaths = [$this->controlsPath()];

        $this->assertInstanceOf(PostControl::class, Access::controlForModel(Post::class));
    }

    public function test_cache_command_writes_the_discovered_controls(): void
    {
        $this
            ->artisan('access:cache')
            ->assertOk()
            ->run();

        $this->assertFileExists(Access::cachePath());
        $this->assertEqualsCanonicalizing(
            [PostControl::class, AdminPostControl::class],
            require Access::cachePath()
        );
    }

    public function test_discovery_uses_the_cache_file(): void
    {
        $this
            ->artisan('access:cache')
            ->assertOk()
            ->run();

        Access::flush();

        Access::$controlDiscoveryPaths = [];

        $this->assertInstanceOf(PostControl::class, Access::controlForModel(Post::class));
        $this->assertInstanceOf(AdminPostControl::class, Access::controlForModel(AdminPost::class));
    }

    public function test_clear_command_removes_the_cache_file(): void
    {
        $this
            ->artisan('access:cache')
            ->assertOk()
            ->run();

        $this->assertFileExists(Access::cachePath());

        $this
            ->artisan('access:clear')
            ->assertOk()
            ->run();

        $this->assertFileDoesNotExist(Access::cachePath());

        Access::$controlDiscoveryPaths = [];

        $this->assertNull(Access::controlForModel(Post::class));
    }

    public function test_clear_command_succeeds_without_cache_file(): void
    {
        $this
            ->artisan('access:clear')
            ->assertOk()
            ->run();

        $this->assertFileDoesNotExist(Access::cachePath());
    }

    public function test_registry_is_usable_after_flush(): void
    {
        $this->assertInstanceOf(PostControl::class, Access::controlForModel(Post::class));

        Access::flush();

        $this->assertInstanceOf(PostControl::class, Access::controlForModel(Post::class));
        $this->assertInstanceOf(AdminPostControl::class, Access::controlForModel(AdminPost::class));
        $this->assertSame(Access::control(PostControl::class), Access::controlForModel(Post::class));
    }

    public function test_cache_commands_are_hooked_into_optimize(): void
    {
        $this->assertContains('access:cache', ServiceProvider::$optimizeCommands);
        $this->assertContains('access:clear', ServiceProvider::$optimizeClearCommands);
    }
}
