<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Support\Facades\Artisan;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\RestApi\CommentResource;
use Lomkit\Access\Tests\Fixtures\RestApi\PostResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
class RestCheckTest extends RestApiTestCase
{
    #[Test]
    public function it_lists_the_extension_as_registered_at_runtime_and_finds_no_problem_in_well_configured_resources(): void
    {
        [$code, $report] = $this->restCheck();

        $this->assertSame(0, $code);
        $this->assertSame([['name' => AccessControlExtension::name(), 'class' => AccessControlExtension::class, 'origin' => 'runtime', 'disabled' => false]], $report['extensions']['registered']);
        $this->assertSame([], $report['extension_checks']['problems']);
        $this->assertContains(['extension' => AccessControlExtension::name(), 'resource' => CommentResource::class], $report['extension_checks']['checked']);
        $this->assertContains(['resource' => PostResource::class, 'extensions' => [AccessControlExtension::name()]], $report['rows']['restricted']);
    }

    #[Test]
    public function it_provides_the_perimeter_of_the_controlled_resources(): void
    {
        [$code, $report] = $this->restCheck();

        $this->assertSame(0, $code);
        $this->assertSame([], $report['rows']['problems']);
        $this->assertSame([], $report['rows']['without_perimeter']);
        $this->assertContains(['resource' => PostResource::class, 'extensions' => [AccessControlExtension::name()]], $report['rows']['extension_perimeter']);
        $this->assertContains(['resource' => CommentResource::class, 'extensions' => [AccessControlExtension::name()]], $report['rows']['extension_perimeter']);
    }

    #[Test]
    public function it_leaves_every_row_visible_to_rest_check_once_the_extension_is_disabled(): void
    {
        config(['rest.disabled_extensions' => [AccessControlExtension::class]]);

        [$code, $report] = $this->restCheck();

        $this->assertSame(1, $code);
        $this->assertSame([], $report['rows']['extension_perimeter']);
        $this->assertCount(4, $report['rows']['problems']);
        $this->assertStringContainsString(PostResource::class, implode("\n", $report['rows']['problems']));
    }

    #[Test]
    public function it_reports_a_control_that_cannot_be_built(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('body'));

        [$code, $report] = $this->restCheck();

        $this->assertSame(1, $code);
        $this->assertCount(2, $report['extension_checks']['problems']);
        $this->assertStringStartsWith(sprintf('The [%s] extension %s throws while it checks %s: ', AccessControlExtension::name(), AccessControlExtension::class, CommentResource::class), $report['extension_checks']['problems'][0]);
    }

    /** @return array{int, array<string, mixed>} */
    private function restCheck(): array
    {
        $code = Artisan::call('rest:check', ['--json' => true]);

        return [$code, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
    }
}
