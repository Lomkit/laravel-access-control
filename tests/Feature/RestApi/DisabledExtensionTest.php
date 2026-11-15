<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
#[WithConfig('rest.disabled_extensions', [AccessControlExtension::class])]
class DisabledExtensionTest extends RestApiTestCase
{
    #[Test]
    public function it_leaves_the_rows_and_the_row_checks_to_the_resource_when_the_app_disables_it(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update client posts' => ['Ada']]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $titles = $this->searched('posts', $this->ada, 'title', ['gates' => ['update']]);
        DB::disableQueryLog();

        $this->assertSame(['Release notes', 'Roadmap', 'Archive', 'Elsewhere', 'Pricing'], $titles);
        $this->assertSame([], array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $query): bool => str_contains($query, '__access_verdict')));
    }

    #[Test]
    public function it_lists_the_extension_as_disabled_in_rest_check(): void
    {
        Artisan::call('rest:check', ['--json' => true]);

        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([['name' => null, 'class' => AccessControlExtension::class, 'origin' => 'runtime', 'disabled' => true]], $report['extensions']['registered']);
        $this->assertSame([], $report['extension_checks']['checked']);
        $this->assertSame([], $report['rows']['restricted']);
    }
}
