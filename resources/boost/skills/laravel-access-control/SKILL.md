---
name: laravel-access-control
description: Build and work with Lomkit Access Control features in Laravel, including Controls, Perimeters, ControlledPolicy, the controlled()/uncontrolled() query macros, and Scout integration.
---

# Laravel Access Control

## When to use this skill

Use this skill when working with Lomkit Access Control in a Laravel project that requires `lomkit/laravel-access-control`. Activate when the user:

- Writes or modifies a `Policy` and wants authorization centralized in a single class.
- Needs row-level filtering on Eloquent queries (multi-tenant, per-client, per-team, ownership).
- Runs `php artisan make:control` or `php artisan make:perimeter`.
- Mentions `Control`, `Perimeter`, `OverlayPerimeter`, `ControlledPolicy`, `HasControl`, `controlled()`, or `uncontrolled()`.
- Wants `Model::search(...)` (Laravel Scout) to respect the same authorization rules.

Before applying, confirm `lomkit/laravel-access-control` is in `composer.json`. If missing, suggest `composer require lomkit/laravel-access-control` and stop. If present, check whether `config/access-control.php` exists; if not, recommend `php artisan vendor:publish --tag=access-control-config`. Flag that the package is in **Beta** before recommending it for production. Full docs: https://laravel-access-control.lomkit.com

## Features

- Perimeter: fluent definition of `allowed`/`should`/`query`/`scoutQuery` for one access rule. Non-overlay perimeters short-circuit — the first match wins. Example usage:

```php
use Lomkit\Access\Perimeters\Perimeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

Perimeter::new()
    ->allowed(fn (Model $user, string $method) => $user->can("{$method} client models"))
    ->should(fn (Model $user, Model $model)    => $model->client_id === $user->client_id)
    ->query(fn (Builder $query, Model $user)   => $query->where('client_id', $user->client_id))
    ->scoutQuery(fn (\Laravel\Scout\Builder $q, Model $user) => $q->where('client_id', $user->client_id));
```

- OverlayPerimeter: combines additively with other perimeters via OR instead of short-circuiting. Useful when several grants should stack (e.g. a user is both in the `client` perimeter and a `shared with me` perimeter). Example usage:

```php
use Lomkit\Access\Perimeters\OverlayPerimeter;

class SharedPerimeter extends OverlayPerimeter
{
    // overlays() returns true automatically
}
```

- Control: one class per model declaring `$model` and an ordered array of perimeters. Auto-discovered from `app/Access/Controls`; override with `Access::$controlDiscoveryPaths`. Example usage:

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
            GlobalPerimeter::new()->allowed(...)->should(...)->query(...),
            ClientPerimeter::new()->allowed(...)->should(...)->query(...)->scoutQuery(...),
        ];
    }
}
```

- HasControl trait: boots `HasControlScope`, which registers the `controlled()` and `uncontrolled()` macros on the model's **Eloquent** query builder, and exposes the model's Control via `newControl()`. The Scout `controlled()` macro is registered independently by `AccessServiceProvider` whenever Laravel Scout is installed (see Laravel Scout integration below); `uncontrolled()` is Eloquent-only. Example usage:

```php
use Lomkit\Access\Controls\HasControl;

class Post extends Model { use HasControl; }

Post::controlled()->get();    // applies perimeter queries for Auth::user()
Post::uncontrolled()->get();  // bypasses access control (admin tools, jobs, seeds)
```

- ControlledPolicy: drop-in policy that delegates every Gate method (`view`, `viewAny`, `create`, `update`, `delete`, `restore`, `forceDelete`) to the Control. `viewAny` and `create` only run `allowed` (no instance to check `should`). Example usage:

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

- Laravel Scout integration: registers a `controlled()` macro on `Laravel\Scout\Builder` that uses each perimeter's `scoutQuery` closure. Scout only supports equality-style filters, so any field used in `scoutQuery` must be exposed via `toSearchableArray()`. If no perimeter matches, an impossible filter forces an empty result. Example usage:

```php
Post::search('hello')->controlled()->get();
Post::search('hello')->controlled()->paginate(15);
```

- Configuration: `config/access-control.php` controls query behavior and Gate method aliasing. `enabled_by_default` makes every query controlled (use `uncontrolled()` to opt out, including in tests and seeders). `isolate_parent_query` and `isolate_perimeter_queries` prevent perimeter ORs from leaking into the caller's `where` chain. Example usage:

```php
return [
    'queries' => [
        'enabled_by_default'        => false,
        'isolate_parent_query'      => true,
        'isolate_perimeter_queries' => true,
    ],
    'methods' => [
        'viewAny' => 'view', 'view' => 'view', 'create' => 'create',
        'update'  => 'update', 'delete' => 'delete',
        'restore' => 'restore', 'forceDelete' => 'forceDelete',
    ],
];
```

## Common pitfalls to flag when reviewing or writing code

  - No perimeter matches → query is forced to `0=1` (Eloquent) or an impossible field (Scout). Empty results after wiring control usually mean no `allowed()` returned true.
  - Order matters for non-overlay perimeters: the first match short-circuits. Put admin grants first, or make them overlays.
  - `should` is skipped for `viewAny`/`create` — use `allowed` for creation-time gates.
  - `scoutQuery` cannot run `whereHas`/`orWhereNotNull`; denormalize into the indexed payload.
  - `enabled_by_default: true` also scopes tests and seeders — wrap fixtures in `->uncontrolled()` or disable it in `phpunit.xml`.
  - Controls may execute `allowed()` twice in `index`-style endpoints (policy + `controlled()` macro); cache it on the user if expensive.
