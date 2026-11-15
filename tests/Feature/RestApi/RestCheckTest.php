<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\RestApi\CachedCommentResource;
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
    public function it_reports_a_controlled_model_without_a_policy(): void
    {
        Gate::guessPolicyNamesUsing(fn (string $class): array => []);

        [$code, $report] = $this->restCheck();

        $this->assertSame(1, $code);
        $this->assertSame([
            $this->problem(CommentResource::class, sprintf('its [%s] model has a control but no policy, so the Gate refuses every row of the resource. Call Access::registerPolicies() in a service provider, or give the model a policy that extends [%s].', Comment::class, ControlledPolicy::class)),
            $this->problem(CachedCommentResource::class, sprintf('its [%s] model has a control but no policy, so the Gate refuses every row of the resource. Call Access::registerPolicies() in a service provider, or give the model a policy that extends [%s].', Comment::class, ControlledPolicy::class)),
        ], $report['extension_checks']['problems']);
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

    private function problem(string $resource, string $message): string
    {
        return sprintf('The [%s] extension %s reports %s: %s', AccessControlExtension::name(), AccessControlExtension::class, $resource, $message);
    }
}
