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

**Declare who reaches which rows once. Policies, queries and searches follow.**

Write one control for an Eloquent model: which users reach which rows, for each ability. Laravel Access Control turns it into the policy behind `$user->can()`, the `controlled()` scope that filters your queries in SQL, the filter of your Laravel Scout searches and the row restriction of your laravel-rest-api resources. They all read the same declaration, so what a list shows and what the policy grants come from one place.

No access rule repeated in every query, no policy that grants what the list hides.

- **One control per model**: perimeters say who reaches which rows, as SQL constraints and as in-memory checks.
- **One declaration, everywhere**: automatic policies, `controlled()` queries, controlled Scout searches and the laravel-rest-api extension.
- **Levels, boundaries and parents**: `Levels::of()` applies the highest level a user reaches, a boundary keeps every row inside the user's workspace or tenant, and `ThroughParent` gives a comment the access of its post.
- **Fails closed**: no signed-in user, no perimeter or no boundary value means no row, and a missing declaration throws instead of opening rows.
- **Checked before production**: `php artisan access:check` reports what would throw, open or hide rows, and test assertions prove that the policy, the queries and the search agree on your data.
- **Ready for AI assistants**: the package ships a Laravel Boost skill.

## Documentation

The full guide lives on the [documentation site](https://laravel-access-control.lomkit.com). Coming from 0.x? Read the [upgrade guide](UPGRADE.md), which lists every change of 1.0 area by area.

For AI assistants, the package ships a Laravel Boost skill with the full feature reference: [resources/boost/skills/laravel-access-control/SKILL.md](resources/boost/skills/laravel-access-control/SKILL.md).

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
                    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
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

`allowed()` says whether a perimeter applies to the user for an ability (`view`, `update`, a business ability such as `approve`), `query()` gives its rows in SQL and `should()` checks one row in memory. In a `Levels::of()` group, widest first, only the highest level the user reaches applies. Perimeters outside a group add up, and a `boundary()` can keep every row inside the user's workspace or tenant.

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

**3. Register the policies**, in the `boot()` method of a service provider. Every controlled model without a policy of its own gets one for its control.

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

With Laravel Scout, use `ControlledSearchable` instead of `Searchable` and give each perimeter that restricts rows its search keys: here, `->searchKeys(name: 'client', row: fn (Post $post) => $post->client_id, reader: fn (User $user) => $user->client_id)` on the client perimeter, and `->searchKeys(name: 'author', row: fn (Post $post) => $post->author_id, reader: fn (User $user) => $user->id)` on the own perimeter. Each document is indexed with the keys that open it, and a search keeps the documents that share a key with the user.

```php
use Lomkit\Access\Search\ControlledSearchable;

class Post extends Model
{
    use HasControl, ControlledSearchable;
}

Post::search('budget')->controlled()->paginate(25);
```

A search must say `controlled()` or `uncontrolled()`. Declare `_access` and `_boundary` as filterable in your engine, turn on Scout's `after_commit` and reindex: see [Search](UPGRADE.md#search).

**5. Check it.** In CI, run the command with the Scout driver of production:

```bash
php artisan access:check
```

In your tests, `InteractsWithAccess` proves that the policy, `controlled()` and the search agree on your test data:

```php
use Lomkit\Access\Testing\InteractsWithAccess;

class PostControlTest extends TestCase
{
    use InteractsWithAccess;

    public function test_its_rules_agree(): void
    {
        $this->assertControlAgrees(PostControl::class, $this->author, Post::uncontrolled()->get());
        $this->assertSearchAgrees(PostControl::class, readers: [$this->admin, $this->manager, $this->author]);
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
