<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Controls\LedgerControl;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Controls\LedgerLineControl;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Controls\MemoControl;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Models\Ledger;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Models\LedgerLine;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Models\Memo;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\SqlWorkspaceBoundary;
use PHPUnit\Framework\Attributes\Test;

class DraftCheckTest extends CheckTestCase
{
    #[Test]
    public function it_notes_a_control_whose_perimeters_all_check_the_rows_in_sql(): void
    {
        $this->discover(['Drafts']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson(['--only' => [Ledger::class]]);

        $this->assertSame(0, $code);
        $this->assertSame([$this->ungrantable(LedgerControl::class, Ledger::class)], $report['notes']);
    }

    #[Test]
    public function it_notes_a_parent_that_does_not_delegate_create(): void
    {
        $this->discover(['Drafts']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson(['--only' => [LedgerLine::class]]);

        $this->assertSame(0, $code);
        $this->assertSame([$this->ungrantable(LedgerLineControl::class, LedgerLine::class)], $report['notes']);
    }

    #[Test]
    public function it_notes_a_boundary_without_an_in_memory_form(): void
    {
        $this->discover(['Drafts']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson(['--only' => [Memo::class]]);

        $this->assertSame(0, $code);
        $this->assertSame([
            ['kind' => 'boundary_refusing_drafts', 'subject' => MemoControl::class, 'message' => 'The ['.SqlWorkspaceBoundary::class.'] boundary of the ['.MemoControl::class.'] control has no in-memory form, so it refuses every draft with outside_boundary, and laravel-rest-api passes a draft for each row it creates. Override includes() on the boundary, or declare it with Boundary::where(), unless the model is read-only.'],
        ], $report['notes']);
    }

    #[Test]
    public function it_notes_nothing_for_a_should_perimeter_and_a_parent_that_delegates_create(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson();

        $this->assertSame(0, $code);
        $this->assertSame([], $report['notes']);
    }

    #[Test]
    public function it_prints_the_draft_notes_in_the_report(): void
    {
        $this->discover(['Drafts']);
        Access::registerPolicies();

        $this->artisan('access:check')
            ->expectsOutputToContain('outside_boundary')
            ->expectsOutputToContain('No problem found.')
            ->assertExitCode(0);
    }

    /** @return array{kind: string, subject: string, message: string} */
    private function ungrantable(string $control, string $model): array
    {
        return ['kind' => 'ungrantable_drafts', 'subject' => $control, 'message' => 'No perimeter of the ['.$control.'] control can grant a draft: none has a should() callback or an includes() hook, none is unrestricted, and no ThroughParent delegates create. Every create with a draft of a ['.$model.'] row is refused with outside_rows, and laravel-rest-api passes a draft for each row it creates. Add a should() callback to the perimeters through which readers create these rows, unless the model is read-only.'];
    }
}
