<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\DenyingUserPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;
use Lomkit\Access\Tests\Fixtures\Ledger\Entry;
use Lomkit\Access\Tests\Fixtures\Ledger\Invoice;
use Lomkit\Access\Tests\Fixtures\Ledger\Journal;
use Lomkit\Access\Tests\Fixtures\Ledger\JournalPolicy;
use Lomkit\Access\Tests\Fixtures\Ledger\Policies\InvoicePolicy;
use Lomkit\Access\Tests\Fixtures\Ledger\Receipt;
use Lomkit\Access\Tests\Fixtures\Ledger\RecordPolicy;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RegisterPoliciesTest extends PackageTestCase
{
    protected Client $acme;

    protected User $ada;

    protected User $grace;

    protected User $hank;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($this->acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->grace = User::factory()->for($this->acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $this->hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
    }

    #[Test]
    public function it_gives_no_policy_to_a_controlled_model_until_the_application_asks_for_it(): void
    {
        $this->assertNull(Gate::getPolicyFor(User::class));
        $this->assertFalse($this->ada->can('view', $this->grace));
    }

    #[Test]
    public function it_gives_a_controlled_policy_to_a_controlled_model_without_policy(): void
    {
        Access::registerPolicies();

        $this->assertInstanceOf(ControlledPolicy::class, Gate::getPolicyFor(User::class));
        $this->assertInstanceOf(ControlledPolicy::class, Gate::getPolicyFor($this->grace));
    }

    #[Test]
    public function it_decides_every_ability_of_the_automatic_policy_with_the_control_of_the_model(): void
    {
        Access::registerPolicies();
        foreach (['view', 'update', 'create', 'approve'] as $ability) {
            Gate::define("{$ability} client users", fn (User $user): bool => true);
        }

        $this->assertTrue($this->ada->can('viewAny', User::class));
        $this->assertTrue($this->ada->can('view', $this->grace));
        $this->assertTrue($this->ada->can('update', $this->grace));
        $this->assertFalse($this->ada->can('update', $this->hank));
        $this->assertFalse($this->ada->can('delete', $this->grace));
        $this->assertTrue($this->ada->can('create', User::class));
        $this->assertTrue($this->ada->can('create', [User::class, new User(['client_id' => $this->acme->getKey()])]));
        $this->assertFalse($this->ada->can('create', [User::class, new User(['client_id' => $this->hank->client_id])]));
        $this->assertSame('outside_rows', Gate::forUser($this->ada)->inspect('update', $this->hank)->code());
    }

    #[Test]
    public function it_keeps_a_policy_registered_with_gate_policy(): void
    {
        Gate::policy(User::class, DenyingUserPolicy::class);
        Access::registerPolicies();
        Gate::define('update client users', fn (User $user): bool => true);

        $this->assertInstanceOf(DenyingUserPolicy::class, Gate::getPolicyFor(User::class));
        $this->assertSame('Users are read-only.', Gate::forUser($this->ada)->inspect('update', $this->grace)->message());
    }

    #[Test]
    public function it_lets_a_policy_registered_later_win(): void
    {
        Access::registerPolicies();
        Gate::policy(User::class, DenyingUserPolicy::class);

        $this->assertInstanceOf(DenyingUserPolicy::class, Gate::getPolicyFor(User::class));
    }

    #[Test]
    public function it_keeps_a_policy_declared_with_use_policy(): void
    {
        Access::registerPolicies();

        $this->assertInstanceOf(PostPolicy::class, Gate::getPolicyFor(Post::class));
    }

    #[Test]
    public function it_keeps_a_policy_guessed_by_the_naming_convention(): void
    {
        Access::registerPolicies();

        $this->assertInstanceOf(InvoicePolicy::class, Gate::getPolicyFor(Invoice::class));
    }

    #[Test]
    public function it_keeps_the_policy_names_guessed_by_the_application(): void
    {
        Gate::guessPolicyNamesUsing(fn (string $class): array => $class === User::class ? [DenyingUserPolicy::class] : []);
        Access::registerPolicies();

        $this->assertInstanceOf(DenyingUserPolicy::class, Gate::getPolicyFor(User::class));
        $this->assertInstanceOf(ControlledPolicy::class, Gate::getPolicyFor(Invoice::class));
        $this->assertNotInstanceOf(InvoicePolicy::class, Gate::getPolicyFor(Invoice::class));
    }

    #[Test]
    public function it_keeps_a_policy_declared_with_use_policy_on_a_parent_class(): void
    {
        Access::registerPolicies();

        $this->assertInstanceOf(RecordPolicy::class, Gate::getPolicyFor(Receipt::class));
    }

    #[Test]
    public function it_keeps_a_policy_registered_for_a_parent_class(): void
    {
        Gate::policy(Journal::class, JournalPolicy::class);
        Access::registerPolicies();

        $this->assertInstanceOf(JournalPolicy::class, Gate::getPolicyFor(Entry::class));
    }

    #[Test]
    public function it_gives_no_policy_to_a_model_without_control(): void
    {
        Access::registerPolicies();

        $this->assertNull(Gate::getPolicyFor(Comment::class));
        $this->assertNull(Gate::getPolicyFor(Project::class));
        $this->assertNull(Gate::getPolicyFor('App\\Missing'));
    }

    #[Test]
    public function it_finds_the_controls_only_when_the_gate_asks(): void
    {
        Access::registerPolicies();
        Access::discoverControlsIn([dirname(__DIR__, 2).'/Fixtures/Discovery/Controls']);

        $this->assertInstanceOf(ControlledPolicy::class, Gate::getPolicyFor(Note::class));
    }

    #[Test]
    public function it_gives_each_model_the_policy_of_its_own_control(): void
    {
        Access::registerPolicies();
        Gate::define('view client users', fn (User $user): bool => true);

        Gate::getPolicyFor(Entry::class);

        $this->assertTrue($this->ada->can('viewAny', User::class));
        $this->assertFalse($this->ada->can('viewAny', Entry::class));
    }
}
