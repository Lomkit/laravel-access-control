<p align="center"><img src="https://raw.githubusercontent.com/Lomkit/art/main/laravel-access-control/cover.png" alt="Laravel Access Control: one control per model drives your policies, queries and searches"></p>

<p align="center">
<a href="https://github.com/Lomkit/laravel-access-control/actions/workflows/tests.yml"><img src="https://github.com/Lomkit/laravel-access-control/actions/workflows/tests.yml/badge.svg?branch=1.x" alt="Tests"></a>
<a href="https://github.com/Lomkit/laravel-access-control/actions/workflows/code-quality.yml"><img src="https://github.com/Lomkit/laravel-access-control/actions/workflows/code-quality.yml/badge.svg?branch=1.x" alt="Code quality"></a>
<a href="https://packagist.org/packages/lomkit/laravel-access-control"><img src="https://img.shields.io/packagist/v/lomkit/laravel-access-control" alt="Latest version"></a>
<a href="https://packagist.org/packages/lomkit/laravel-access-control"><img src="https://img.shields.io/packagist/dt/lomkit/laravel-access-control" alt="Total downloads"></a>
<a href="LICENSE"><img src="https://img.shields.io/packagist/l/lomkit/laravel-access-control" alt="License"></a>
</p>

<p align="center"><a href="https://laravel-access-control.lomkit.com"><strong>Documentation</strong></a> · <a href="UPGRADE.md">Upgrade from 0.x</a></p>

# Laravel Access Control

**Declare once who can see and change which rows. Policies, queries and searches follow.**

Write one control for an Eloquent model: which users reach which rows, for each ability. Laravel Access Control turns it into the policy behind `$user->can()`, the `controlled()` scope that filters your queries in SQL, the filter of your Laravel Scout searches and the row restriction of your laravel-rest-api resources. They all read the same control, so each access rule lives in one place.

No access rule repeated in every query, and a test that proves the policy grants what the list shows.

- **One control per model**: its perimeters say which users reach which rows, for each ability, as SQL constraints and as in-memory checks.
- **One declaration, everywhere**: automatic policies, `controlled()` queries, controlled Scout searches and the laravel-rest-api extension.
- **Levels, boundaries and parents**: `Levels::of()` applies the highest level a user reaches, a boundary keeps every row inside the user's workspace or tenant, and `ThroughParent` gives a comment the access of its post.
- **Fails closed**: no signed-in user, no perimeter or no boundary value means no row, and a missing declaration throws instead of opening rows.
- **Checked before production**: `php artisan access:check` reports what would throw, open or hide rows, and test assertions prove that the policy, the queries and the search agree on your test data.

## Documentation

The full guide lives on the [documentation site](https://laravel-access-control.lomkit.com). Coming from 0.x? Read the [upgrade guide](UPGRADE.md), which lists every change of 1.0 area by area.

For AI assistants, the site serves [llms.txt](https://laravel-access-control.lomkit.com/llms.txt) and [llms-full.txt](https://laravel-access-control.lomkit.com/llms-full.txt), and the package ships a Laravel Boost skill with the full feature reference: [resources/boost/skills/laravel-access-control/SKILL.md](resources/boost/skills/laravel-access-control/SKILL.md). See [Use with AI](https://laravel-access-control.lomkit.com/digging-deeper/use-with-ai).

## Requirements

PHP 8.3+ and Laravel 13.

Optional: Laravel Scout 11 for controlled searches, and laravel-rest-api 3.6+ for its extension. Applications on Laravel 12 can use 0.7, the last 0.x release.

## Installation

```bash
composer require lomkit/laravel-access-control
```

There is no configuration file to publish: everything is declared in your controls.

## From model to controlled access

Take a `Post` model with a `client_id` and an `author_id`, and a `User` with a `client_id`.

**1. Declare who reaches which posts.** `php artisan make:perimeter ClientPerimeter` creates a perimeter class in `app/Access/Perimeters` (do the same for `GlobalPerimeter` and `OwnPerimeter`), and `php artisan make:control PostControl` creates the control in `app/Access/Controls`.

```php
namespace App\Access\Controls;

use App\Access\Perimeters\ClientPerimeter;
use App\Access\Perimeters\GlobalPerimeter;
use App\Access\Perimeters\OwnPerimeter;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;

class PostControl extends Control
{
    protected string $model = Post::class;

    protected function perimeters(): array
    {
        return [
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} all posts"))
                    ->unrestricted(),
                ClientPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability) => $user->client_id !== null && $user->can("{$ability} client posts"))
                    ->should(fn (User $user, Post $post) => $post->client_id === $user->client_id)
                    ->query(function (Builder $query, User $user): void {
                        $query->where('client_id', $user->client_id);
                    }),
                OwnPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} own posts"))
                    ->should(fn (User $user, Post $post) => $post->author_id === $user->id)
                    ->query(function (Builder $query, User $user): void {
                        $query->where('author_id', $user->id);
                    }),
            ),
        ];
    }
}
```

`allowed()` says whether a perimeter applies to the user for an ability (`view`, `update`, a business ability such as `approve`): the permission. `query()` gives its rows in SQL, for the lists, and `should()` checks one row in memory, for the policy. They are two rules you write, so they must describe the same rows: `assertControlAgrees()` (step 5) proves it. The client perimeter refuses a user without a client, so a `null` never matches the posts without a client.

In a `Levels::of()` group, widest first, only the highest level the user reaches applies, and perimeters outside a group add up. `GlobalPerimeter` reaches the posts of every client: when clients are tenants, keep each user inside theirs with a `boundary()`.

**2. Link the model.** The control is discovered in `app/Access/Controls` by its `$model`. A control kept elsewhere is named on the model with `#[ControlledBy(PostControl::class)]`.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;

class Post extends Model
{
    use HasControl;
}
```

**3. Register the policies** in the `boot()` method of a service provider. Every controlled model without a policy of its own gets one for its control.

```php
use Lomkit\Access\Access;

public function boot(): void
{
    Access::registerPolicies();
}
```

**4. Use it.**

```php
Post::query()->controlled()->paginate();         // the posts the signed-in user may view
Post::query()->controlled('update')->paginate(); // the posts the signed-in user may update
Post::controlledFor($user)->get();               // for a named user, in jobs and commands

$user->can('update', $post);                     // the policy, from the same perimeters
```

A query is controlled only when it says so, and a controlled query without a signed-in user returns no row.

With Laravel Scout, give each perimeter that restricts rows its search keys, at the end of its chain in the control. Each document is indexed with the keys that open it, and a search keeps the documents that share a key with the user.

```php
ClientPerimeter::new()
    // allowed(), should() and query() as in step 1
    ->searchKeys(name: 'client', row: fn (Post $post) => $post->client_id, reader: fn (User $user) => $user->client_id),
OwnPerimeter::new()
    // allowed(), should() and query() as in step 1
    ->searchKeys(name: 'author', row: fn (Post $post) => $post->author_id, reader: fn (User $user) => $user->id),
```

Then use `ControlledSearchable` instead of `Searchable` on the model:

```php
use Lomkit\Access\Search\ControlledSearchable;

class Post extends Model
{
    use HasControl, ControlledSearchable;
}

Post::search('budget')->controlled()->paginate(25);
```

A search must say `controlled()` or `uncontrolled()`. Declare `_access` and `_boundary` as filterable in your engine ([Engines](UPGRADE.md#engines)), turn on Scout's `after_commit` ([Keeping the keys fresh](UPGRADE.md#keeping-the-keys-fresh)) and [reindex](UPGRADE.md#reindex).

**5. Check it.** In CI, run the command with the Scout driver of production:

```bash
SCOUT_DRIVER=meilisearch php artisan access:check
```

In your tests, `InteractsWithAccess` proves that the policy, `controlled()` and, with Scout, the search agree on your test data, for each reader you give it. The example uses the factories of your models:

```php
namespace Tests\Feature;

use App\Access\Controls\PostControl;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Access\Testing\InteractsWithAccess;
use Tests\TestCase;

class PostControlTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    public function test_its_rules_agree(): void
    {
        // Give each reader the permissions your application gives it: a reader without
        // permissions sees no row, and the assertions then pass without proving anything.
        // Clients 1 and 2 must already exist.
        $admin = User::factory()->create(['client_id' => 1]);   // "... all posts"
        $manager = User::factory()->create(['client_id' => 1]); // "... client posts"
        $author = User::factory()->create(['client_id' => 1]);  // "... own posts"
        $other = User::factory()->create(['client_id' => 2]);   // "... own posts"
        Post::factory()->create(['client_id' => 1, 'author_id' => $author->id]);
        Post::factory()->create(['client_id' => 1, 'author_id' => $manager->id]);
        Post::factory()->create(['client_id' => 2, 'author_id' => $other->id]);

        $readers = [$admin, $manager, $author, $other];

        foreach ($readers as $reader) {
            $this->assertControlAgrees(PostControl::class, $reader, Post::uncontrolled()->get());
        }

        $this->assertLevelsNest(PostControl::class, readers: $readers);
        $this->assertSearchAgrees(PostControl::class, readers: $readers); // with Scout
    }
}
```

The [upgrade guide](UPGRADE.md) and the [documentation](https://laravel-access-control.lomkit.com) cover the rest: boundaries, parents, drafts, business abilities, batched decisions, browser search tokens and the laravel-rest-api extension.

## Contributing

Contributions are welcome. Read the [contributing guide](CONTRIBUTING.md) first.

## Security

Do not report a security issue in a public issue. Report it in private from the [Security tab](https://github.com/Lomkit/laravel-access-control/security) of the repository, with "Report a vulnerability".

## License

Laravel Access Control is open-source software licensed under the [MIT license](LICENSE).
