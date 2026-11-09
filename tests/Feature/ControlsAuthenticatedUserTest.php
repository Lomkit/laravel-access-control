<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Support\Access\Controls\ControlledUserControl;
use Lomkit\Access\Tests\Support\Models\ControlledUser;
use Lomkit\Access\Tests\Support\Models\SearchableModel;
use PHPUnit\Framework\Attributes\Group;

class ControlsAuthenticatedUserTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new Access())->addControl(new ControlledUserControl());

        config([
            'access-control.queries.enabled_by_default' => true,
            'auth.providers.users.model'                => ControlledUser::class,
        ]);
    }

    /**
     * Normalize Scout's `$wheres` to a [field => value] map across laravel/scout 10 and 11.
     */
    protected function whereMap(\Laravel\Scout\Builder $query): array
    {
        $wheres = $query->wheres;

        if ($wheres !== [] && isset($wheres[0]) && is_array($wheres[0]) && array_key_exists('field', $wheres[0])) {
            return array_column($wheres, 'value', 'field');
        }

        return $wheres;
    }

    /**
     * Create a controlled user.
     */
    protected function createControlledUser(string $email): ControlledUser
    {
        return ControlledUser::create([
            'name'     => 'Controlled user',
            'email'    => $email,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ]);
    }

    /**
     * Log the given user in through the session, the guard retrieving it by id on first use.
     */
    protected function authenticateThroughSession(ControlledUser $user): void
    {
        Auth::forgetGuards();

        $this->app['session.store']->put(Auth::guard('web')->getName(), $user->getKey());
    }

    public function test_session_guard_retrieves_a_controlled_user_by_id(): void
    {
        $user = $this->createControlledUser('controlled@example.com');
        $this->createControlledUser('other@example.com');

        $this->authenticateThroughSession($user);

        $this->assertTrue($user->is(Auth::user()));
        $this->assertEquals([$user->getKey()], ControlledUser::query()->pluck('id')->all());
    }

    public function test_controlled_query_resolves_a_controlled_user_from_the_session(): void
    {
        $user = $this->createControlledUser('controlled@example.com');
        $this->createControlledUser('other@example.com');

        $this->authenticateThroughSession($user);

        $this->assertEquals([$user->getKey()], ControlledUser::query()->pluck('id')->all());
        $this->assertTrue($user->is(Auth::user()));
    }

    public function test_queries_run_once_the_guard_stored_the_user_stay_controlled(): void
    {
        $user = $this->createControlledUser('controlled@example.com');
        $this->createControlledUser('other@example.com');

        $visibleIds = [];

        Event::listen(Authenticated::class, function () use (&$visibleIds) {
            $visibleIds[] = ControlledUser::query()->pluck('id')->all();
        });

        $this->authenticateThroughSession($user);

        Auth::user();

        $this->assertNotEmpty($visibleIds);

        foreach ($visibleIds as $ids) {
            $this->assertEquals([$user->getKey()], $ids);
        }
    }

    #[Group('scout')]
    public function test_scout_controlled_query_resolves_a_controlled_user_from_the_session(): void
    {
        Gate::define('view own models', function (ControlledUser $user) {
            return true;
        });

        $user = $this->createControlledUser('controlled@example.com');

        $this->authenticateThroughSession($user);

        $query = SearchableModel::search()->controlled();

        $this->assertEquals(['author_id' => $user->getKey()], $this->whereMap($query));
    }
}
