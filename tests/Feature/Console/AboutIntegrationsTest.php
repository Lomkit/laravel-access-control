<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

class AboutIntegrationsTest extends PackageTestCase
{
    #[Test]
    #[Group('scout')]
    #[WithConfig('scout.driver', 'meilisearch')]
    public function it_names_the_scout_driver_of_the_environment(): void
    {
        $this->assertSame('installed, meilisearch driver', $this->about()['scout']);
    }

    #[Test]
    #[Group('rest-api')]
    #[WithConfig('rest.disabled_extensions', [AccessControlExtension::class])]
    public function it_tells_that_the_rest_api_extension_is_disabled(): void
    {
        $this->assertSame('installed, AccessControlExtension listed in rest.disabled_extensions', $this->about()['laravel-rest-api']);
    }

    #[Test]
    #[Group('rest-api')]
    public function it_tells_that_the_rest_api_extension_is_enabled(): void
    {
        $this->assertSame('installed, AccessControlExtension enabled', $this->about()['laravel-rest-api']);
    }

    /** @return array<string, string> */
    private function about(): array
    {
        Artisan::call('about', ['--only' => 'access_control', '--json' => true]);

        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['access_control'];
    }
}
