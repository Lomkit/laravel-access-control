---
name: laravel-access-control
description: Build and work with Lomkit Access Control features in Laravel, including Controls linked with #[ControlledBy], the Access facade, Perimeters, ControlledPolicy, unrestricted perimeters, the controlled(), controlledFor() and uncontrolled() query scopes, and Scout integration.
---

# Laravel Access Control

## When to use this skill

Use this skill when working with Lomkit Access Control in a Laravel project that requires `lomkit/laravel-access-control`. Activate when the user:

- Writes or modifies a `Policy` and wants authorization centralized in a single class.
- Needs row-level filtering on Eloquent queries (multi-tenant, per-client, per-team, ownership).
- Runs `php artisan make:control` or `php artisan make:perimeter`.
- Mentions `#[ControlledBy]`, `Access::controlFor()`, `Access::hasControl()`, or `access:cache`.
- Mentions `Control`, `Perimeter`, `OverlayPerimeter`, `unrestricted()`, `ControlledPolicy`, `HasControl`, `controlled()`, `controlledFor()`, or `uncontrolled()`.
- Wants `Model::search(...)` (Laravel Scout) to respect the same authorization rules.

Before applying, confirm `lomkit/laravel-access-control` is in `composer.json`. If missing, suggest `composer require lomkit/laravel-access-control` and stop. If present, note that `config/access-control.php` only maps Gate methods to perimeter methods: publish it with `php artisan vendor:publish --tag=access-control-config` only to change that map. Flag that the package is in **Beta** before recommending it for production. Full docs: https://laravel-access-control.lomkit.com

## Features

- Perimeter: fluent definition of `allowed`/`should`/`query`/`scoutQuery` for one access rule. Non-overlay perimeters short-circuit — the first match wins. The `query` and `scoutQuery` callbacks constrain the builder they receive and return nothing (returning that same builder is accepted, e.g. from an arrow function); their return value is never used. Example usage:

```php
use Lomkit\Access\Perimeters\Perimeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

Perimeter::new()
    ->allowed(fn (Model $user, string $method) => $user->can("{$method} client models"))
    ->should(fn (Model $user, Model $model)    => $model->client_id === $user->client_id)
    ->query(function (Builder $query, Model $user): void {
        $query->where('client_id', $user->client_id);
    })
    ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user): void {
        $query->where('client_id', $user->client_id);
    });
```

- Unrestricted perimeter: `unrestricted()` marks a perimeter that reaches every row, such as an administrator's. It takes only `allowed`; declaring `query`, `should` or `scoutQuery` with it throws `UnrestrictedPerimeterCallbackException`. When it applies to the reader, the controlled query and the controlled search get no perimeter constraint, the policy grants the row without calling `should`, and the other perimeters are not evaluated, so an overlay never narrows it. Never write a global perimeter as `query(fn ($query) => $query)`: a `query` callback that adds no constraint throws `EmptyQueryCallbackException`. Example usage:

```php
GlobalPerimeter::new()
    ->allowed(fn (Model $user, string $method) => $user->can("{$method} global models"))
    ->unrestricted();
```

- OverlayPerimeter: combines additively with other perimeters via OR instead of short-circuiting. In a Scout query the engine can only combine constraints with AND, so combined perimeters narrow each other there (see the pitfalls). Useful when several grants should stack (e.g. a user is both in the `client` perimeter and a `shared with me` perimeter). Example usage:

```php
use Lomkit\Access\Perimeters\OverlayPerimeter;

class SharedPerimeter extends OverlayPerimeter
{
    // overlays() returns true automatically
}
```

- Control: one class per model declaring `$model` (as the property default, an Eloquent model class) and an ordered array of perimeters. The model names its control with `#[ControlledBy(PostControl::class)]`; a model without the attribute falls back to the control discovered in `app/Access/Controls` whose `$model` is that model (change the directories with `Access::discoverControlsIn([...])` in a service provider's `boot()`). A model has a single control: two controls for one model, declared or discovered, throw `DuplicateControlException`; a `$model` that is missing, not a class or not an Eloquent model throws `InvalidControlModelException`. Example usage:

```php
namespace App\Access\Controls;

use Lomkit\Access\Controls\Control;
use App\Access\Perimeters\GlobalPerimeter;
use App\Access\Perimeters\ClientPerimeter;
use App\Models\Post;

class PostControl extends Control
{
    protected string $model = Post::class;

    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()->allowed(...)->unrestricted(),
            ClientPerimeter::new()->allowed(...)->should(...)->query(...)->scoutQuery(...),
        ];
    }
}

namespace App\Models;

use App\Access\Controls\PostControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[ControlledBy(PostControl::class)]
class Post extends Model
{
    use HasControl;
}
```

- Access facade: `Lomkit\Access\Access` is a facade over the control registry, a container singleton (one per container, so one per test). `Access::controlFor($model)` returns the model's control or throws `MissingControlException`; `Access::hasControl($model)` checks first. Both take a model instance or class name. `Access::control(PostControl::class)` and `PostControl::new()` return the registry's single instance, resolved through the container (constructor injection works; bind a replacement with `$this->app->instance(PostControl::class, $control)` in tests). `php artisan access:cache` (hooked into `optimize`) caches the control of each discovered model; `php artisan access:clear` removes it. Example usage:

```php
use Lomkit\Access\Access;

if (Access::hasControl($model)) {
    $control = Access::controlFor($model);
}
```

- HasControl trait: adds three local scopes, declared with Laravel's `#[Scope]` attribute, and exposes the model's Control via `newControl()`. A query is never controlled unless it says so: `controlled()` applies the control for `Auth::user()`, read when the query is built; `controlledFor($reader)` applies it for a reader you name, for jobs, listeners and commands, which have no signed-in user; `uncontrolled()` states that the query reads every row on purpose. Without a signed-in user, `controlled()` reads nothing, and so does `controlledFor(null)`. The perimeters are grouped in one nested `where`, so the caller's own constraints are never widened. The trait also registers `HasControlScope` as a global scope that filters nothing; the three scopes remove it, and `withoutGlobalScope(HasControlScope::class)` still works. Each scope works on relations and subqueries too. The Scout `controlled()` macro is registered independently by `AccessServiceProvider` whenever Laravel Scout is installed (see Laravel Scout integration below); `controlledFor()` and `uncontrolled()` are Eloquent-only. Example usage:

```php
use Lomkit\Access\Controls\HasControl;

class Post extends Model { use HasControl; }

Post::controlled()->get();                      // the perimeters of Auth::user()
Post::where('published', true)->controlled();   // published and within the perimeters
Post::controlledFor($recipient)->get();         // the perimeters of $recipient (jobs, listeners)
$user->posts()->controlled()->get();            // a relation query
Post::uncontrolled()->count();                  // every row, stated on purpose (stats, seeders)
Post::query()->get();                           // every row: a plain query is not controlled
```

- ControlledPolicy: drop-in policy that delegates every Gate method (`view`, `viewAny`, `create`, `update`, `delete`, `restore`, `forceDelete`) to the Control. Its `$control` must be the control registered for the model it decides on, or it throws `PolicyControlMismatchException`. `viewAny` and `create` only run `allowed` (no instance to check `should`). Example usage:

```php
use Lomkit\Access\Policies\ControlledPolicy;
use App\Access\Controls\PostControl;

class PostPolicy extends ControlledPolicy
{
    protected string $control = PostControl::class;
}

$user->can('view', $post);          // → PostControl perimeters with method='view'
$user->can('viewAny', Post::class); // → method='view' (via the methods map)
```

- Artisan generators: scaffold Controls and Perimeters. Generated files land in `app/Access/Controls` and `app/Access/Perimeters`. Example usage:

```bash
php artisan make:perimeter GlobalPerimeter
php artisan make:perimeter SharedPerimeter --overlay
php artisan make:control PostControl --model=Post --perimeters=GlobalPerimeter --perimeters=ClientPerimeter
```

- Laravel Scout integration: registers a `controlled()` macro on `Laravel\Scout\Builder` that uses each perimeter's `scoutQuery` closure; an unrestricted perimeter adds no constraint. Scout only supports equality-style filters, so any field used in `scoutQuery` must be exposed via `toSearchableArray()`. If no perimeter matches, or no user is signed in, the search returns an empty page without querying the engine. Example usage:

```php
Post::search('hello')->controlled()->get();
Post::search('hello')->controlled()->paginate(15);
```

- Configuration: `config/access-control.php` maps each Gate method to the method its perimeters receive. Example usage:

```php
return [
    'methods' => [
        'viewAny' => 'view', 'view' => 'view', 'create' => 'create',
        'update'  => 'update', 'delete' => 'delete',
        'restore' => 'restore', 'forceDelete' => 'forceDelete',
    ],
];
```

## Common pitfalls to flag when reviewing or writing code

  - No perimeter matches → query is forced to `0=1` (Eloquent), or the search returns an empty page without querying the engine (Scout). Empty results after wiring control usually mean no `allowed()` returned true.
  - Order matters for non-overlay perimeters: the first match short-circuits. Put admin grants first, declared with `unrestricted()`.
  - A `query` callback must constrain the builder it receives: returning another builder (e.g. `Model::query()->where(...)`) throws `QueryCallbackReturnedAnotherBuilderException`, and so does a `scoutQuery` callback that returns another Scout builder. A `query` callback that adds no `where` (nothing, only `orderBy`/`limit`/`join`, or a `when()` that skips) throws `EmptyQueryCallbackException`: use `unrestricted()` for every row, or `whereRaw('0=1')` for no row.
  - `should` is skipped for `viewAny`/`create` — use `allowed` for creation-time gates.
  - Scout cannot combine perimeters with OR: when an overlay perimeter and another perimeter both apply, their `scoutQuery` constraints are joined with AND, so a search returns fewer records than the Eloquent query. Search through a single perimeter, or denormalize the access into one indexed field (e.g. a `visible_to` array) and filter on it.
  - `scoutQuery` cannot run `whereHas`/`orWhereNotNull`; denormalize into the indexed payload.
  - Plain queries, relations, lazy loads and route model binding are never controlled: every query that must be filtered says `controlled()`, or `controlledFor($reader)` where no user is signed in. Authorize a bound model through its policy.
  - `controlled()` and the Scout macro read `Auth::user()` when they run: do not call them from an event of the user model that the guard fires while it retrieves the user (such as `retrieved`), or the guard is asked for the user again.
  - A model is linked to its control by `#[ControlledBy]` or by the discovery, never by a policy: a control outside the discovered directories without the attribute leaves the model without control (`MissingControlException`).
  - Never write two controls for one model (e.g. an admin variant): `DuplicateControlException`. Put the variation in perimeters of the single control.
  - After adding, moving or deleting a control in production, run `php artisan access:cache` again (or `optimize`): the cache maps each model to its control.
  - Controls may execute `allowed()` twice in `index`-style endpoints (policy + `controlled()` scope); cache it on the user if expensive.
