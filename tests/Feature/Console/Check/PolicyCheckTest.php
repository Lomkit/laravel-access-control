<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Exceptions\PolicyControlMismatchException;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketControl;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketReplyControl;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\Ticket;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\TicketReply;
use Lomkit\Access\Tests\Fixtures\Check\Policies\HandWrittenTicketPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Policies\ThrowingTicketPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Policies\TicketPolicyNamingReplies;
use Lomkit\Access\Tests\Fixtures\Check\Policies\TicketPolicyWithoutControl;
use PHPUnit\Framework\Attributes\Test;

class PolicyCheckTest extends CheckTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->discover(['Clean']);
    }

    #[Test]
    public function it_reports_a_controlled_policy_that_names_no_control(): void
    {
        Gate::policy(Ticket::class, TicketPolicyWithoutControl::class);

        [$code, $report] = $this->checkJson(['--only' => [Ticket::class]]);

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'policy_without_control', 'subject' => TicketPolicyWithoutControl::class, 'message' => 'The ['.TicketPolicyWithoutControl::class.'] policy of the ['.Ticket::class.'] model extends ControlledPolicy without a $control, so viewAny() and create() without a draft throw MissingPolicyControlException. Declare protected string $control = TicketControl::class, or remove the policy and call Access::registerPolicies().'],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_controlled_policy_that_names_the_control_of_another_model(): void
    {
        Gate::policy(Ticket::class, TicketPolicyNamingReplies::class);

        [$code, $report] = $this->checkJson(['--only' => [Ticket::class]]);

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'policy_for_another_control', 'subject' => TicketPolicyNamingReplies::class, 'message' => PolicyControlMismatchException::for(TicketPolicyNamingReplies::class, TicketReplyControl::class, Ticket::class, TicketControl::class)->getMessage()],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_policy_that_throws_when_the_gate_resolves_it(): void
    {
        Gate::policy(Ticket::class, ThrowingTicketPolicy::class);

        [$code, $report] = $this->checkJson(['--only' => [Ticket::class]]);

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'unresolvable_policy', 'subject' => Ticket::class, 'message' => 'The policy of the ['.Ticket::class.'] model throws when the Gate resolves it: The ticket policy needs a tenant.'],
        ], $report['problems']);
    }

    #[Test]
    public function it_notes_a_policy_that_does_not_extend_controlled_policy(): void
    {
        Gate::policy(Ticket::class, HandWrittenTicketPolicy::class);

        [$code, $report] = $this->checkJson(['--only' => [Ticket::class]]);

        $this->assertSame(0, $code);
        $this->assertSame([
            ['kind' => 'policy_outside_control', 'subject' => HandWrittenTicketPolicy::class, 'message' => 'The ['.HandWrittenTicketPolicy::class.'] policy of the ['.Ticket::class.'] model does not extend ControlledPolicy, so its decisions follow the control only where it calls it. Extend ControlledPolicy, or remove the policy and call Access::registerPolicies().'],
        ], $report['notes']);
    }

    #[Test]
    public function it_notes_a_controlled_model_without_policy(): void
    {
        [$code, $report] = $this->checkJson(['--only' => [TicketReply::class]]);

        $this->assertSame(0, $code);
        $this->assertSame([
            ['kind' => 'model_without_policy', 'subject' => TicketReply::class, 'message' => 'No policy is registered for the ['.TicketReply::class.'] model, so the Gate denies its abilities: only controlled() and the control itself decide its rows. Call Access::registerPolicies() in a service provider, or give the model a ControlledPolicy.'],
        ], $report['notes']);
    }
}
