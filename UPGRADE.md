# Upgrade Guide

## Upgrading to 1.0 from 0.x

This guide lists every breaking change of version 1.0, area by area. Go through the sections of the features that your application uses; the table at the end maps each removed call to its replacement.

### Requirements

Version 1.0 requires PHP 8.3 and Laravel 13. Applications that stay on Laravel 12 can use version 0.7, the last 0.x release.

### Coming from 0.6

Version 0.7 already changed several things. If you upgrade from 0.6, apply the [0.7.0 checklist](https://github.com/Lomkit/laravel-access-control/releases/tag/v0.7.0) first:

- Remove the `parent::__construct()` calls from your perimeter classes. The `Perimeter` constructor is gone, so the call is a fatal error.
- Declare every perimeter callback the application uses. An undeclared callback throws `MissingPerimeterCallbackException` instead of allowing every row.
- Remove `queries.isolate_parent_query` and `queries.isolate_perimeter_queries` from your published config.
- `HasControl::newControl()` returns `Control` instead of `?Control`.
- Overridden `ControlledPolicy` methods declare a return type compatible with `bool|Response`.

### The configuration file

The package has no configuration file anymore: `config/access-control.php`, its `methods` map and the `access-control-config` publish tag are removed, and the package reads no `access-control.*` key. Delete your published `config/access-control.php`. A file left in place is ignored, so a custom `methods` map stops applying: move it to the control, as [Abilities](#abilities) shows.

### Queries

A query is no longer controlled unless it says so. The `queries.enabled_by_default` key is removed, and nothing reads it anymore (the whole configuration file is gone, see [The configuration file](#the-configuration-file)). Add `controlled()` to every query that relied on the key:

```php
// Before, with 'queries' => ['enabled_by_default' => true]
$posts = Post::query()->where('published', true)->paginate();

// After
$posts = Post::query()->where('published', true)->controlled()->paginate();
```

Relations, `whereHas()` subqueries, eager loads, lazy loads and route model binding were controlled by the key too, and now read every row. Add `controlled()` to the relation queries that must stay filtered, such as `$user->posts()->controlled()->get()` or `whereHas('posts', fn (Builder $query) => $query->controlled())`, and authorize a bound model through its policy, such as `$this->authorize('view', $post)`.

`uncontrolled()` keeps working and still states that a query reads every row on purpose. The calls you added only to opt out of the key can stay or go: they no longer change the results.

Every model that uses `HasControl` still registers `HasControlScope` as a global scope, but the scope no longer adds any constraint. `withoutGlobalScope(HasControlScope::class)` keeps working, and `controlled()`, `controlledFor()` and `uncontrolled()` remove the scope from their query.

### The control scope

`HasControlScope::resolveUser()` and `HasControlScope::retrievingUser()` are removed. Controlled queries and the Scout `controlled()` macro read the user with `Auth::user()` when they run. Replace `HasControlScope::resolveUser()` with `Auth::user()`, and remove the calls to `HasControlScope::retrievingUser()`:

```php
// Before
$user = HasControlScope::resolveUser();

// After
$user = Auth::user();
```

A controlled query no longer skips its control while the guard retrieves the user, where it used to read every row. Do not run a controlled query from an event of your user model that the guard fires while it retrieves the user, such as `retrieved`: the query asks the guard for the user again.

`controlled()` and `uncontrolled()` are now local scopes of the `HasControl` trait, declared with Laravel's `#[Scope]` attribute, instead of builder macros registered by `HasControlScope::extend()`. `HasControlScope` is final and keeps only `apply()`. Remove the code that extends `HasControlScope` or calls its `extend()` method, and rename any `controlled`, `controlledFor` or `uncontrolled` method that a controlled model declares itself.

### Named readers

`controlledFor()` applies the control for a reader you name, where `controlled()` reads the signed-in user: use it in jobs, listeners and commands, which have no signed-in user. Replace the calls to `Control::queried()` with `controlledFor()`:

```php
// Before
$posts = Access::control(PostControl::class)->queried(Post::query(), $recipient)->get();

// After
$posts = Post::controlledFor($recipient)->get();
```

`controlledFor(null)` reads no row, as `controlled()` does without a signed-in user: a missing reader never means every row.

### Query callbacks

The `query` callback of a perimeter constrains the builder it receives, and its return value is no longer used. Returning nothing is the normal form. A callback that returns the builder it receives, such as an arrow function, keeps working:

```php
// Before
->query(fn (Builder $query, User $user) => $query->where('client_id', $user->client_id))

// After
->query(function (Builder $query, User $user): void {
    $query->where('client_id', $user->client_id);
})
```

A callback that returns another builder now throws `QueryCallbackReturnedAnotherBuilderException`, which names the perimeter and the control. In 0.x the returned builder was ignored, so its constraints were lost and the perimeter let every row through. Apply the constraints to the builder the callback receives instead:

```php
// Before: Post::query() is a new builder, so the perimeter added nothing
->query(fn (Builder $query, User $user) => Post::query()->where('author_id', $user->id))

// After
->query(function (Builder $query, User $user): void {
    $query->where('author_id', $user->id);
})
```

The same rule applies to the `scoutQuery` callback.

A `query` callback that adds no constraint to the builder it receives now throws `EmptyQueryCallbackException`. Laravel drops an empty group, so the other perimeters of the reader stopped applying, and the reader saw more or fewer rows than intended. A callback that only orders, limits or joins adds no constraint either: Laravel keeps only the `where` clauses of the group. A callback that adds its constraint under a condition throws for the readers it skips: add a constraint that matches no row for them, such as `$query->whereRaw('0=1')`, or refuse them in `allowed()`.

### Unrestricted perimeters

A perimeter that reaches every row is declared with `unrestricted()`, instead of a `query` callback that adds nothing and a `should` callback that returns `true`. Replace your global perimeters:

```php
// Before
GlobalPerimeter::new()
    ->allowed(fn (User $user, string $method) => $user->can("{$method} global posts"))
    ->should(fn () => true)
    ->query(fn (Builder $query) => $query)
    ->scoutQuery(fn (ScoutBuilder $query) => $query),

// After
GlobalPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} global posts"))
    ->unrestricted(),
```

An unrestricted perimeter takes no `query`, `should` or `scoutQuery` callback: declaring one of them with `unrestricted()` throws `UnrestrictedPerimeterCallbackException`. When an unrestricted perimeter applies to the reader, a controlled query and a controlled search get no perimeter constraint, the policy grants the row without calling any `should` callback, and the other perimeters of the reader are not evaluated.

This fixes a reader with both a global and an overlay permission, such as an administrator who also has "view shared posts": in 0.x the empty global group was dropped and the administrator only saw the shared posts. Such a reader now sees every row, which may widen what your tests expect.

### Linking a model to its control

A model names its control with the `#[ControlledBy]` attribute. Add it to each controlled model:

```php
use Lomkit\Access\Attributes\ControlledBy;

#[ControlledBy(PostControl::class)]
class Post extends Model
{
    use HasControl;
}
```

A model without the attribute falls back to the discovery: the control found in `app/Access/Controls` whose `$model` is that model. A control outside the discovered directories is only found through the attribute: in 0.x it was registered when a policy asked for it, so queries could run without it.

A control declares its model as the default value of its `$model` property, such as `protected string $model = Post::class;`. A control whose `$model` is missing, is not a class or is not an Eloquent model now throws `InvalidControlModelException` when it is registered: move a `$model` assigned in a constructor to the property declaration.

A model has a single control. Two controls for the same model, two discovered controls, or a discovered control and the one the attribute names, now throw `DuplicateControlException`, which names both. In 0.x the first control found won, in the order of the files on disk. Remove one of them, or point its `$model` at the right model.

### The Access facade

`Lomkit\Access\Access` is now a facade over the control registry, a singleton of the container. Keep the import, and replace the calls that are gone:

```php
// Before
$control = Access::controlForModel($post); // ?Control

// After
$control = Access::controlFor($post);      // throws MissingControlException
$controlled = Access::hasControl($post);
```

`Access::controlFor()` throws `MissingControlException` instead of returning `null`: check `Access::hasControl()` first where a model may have no control.

`Access::$controlDiscoveryPaths` is replaced by `Access::discoverControlsIn()`, called from the `boot()` method of a service provider:

```php
// Before
Access::$controlDiscoveryPaths = [app_path('Domain/*/Controls')];

// After
Access::discoverControlsIn([app_path('Domain/*/Controls')]);
```

`addControl()`, `addControls()` and `discoverControls()` are removed: declare the control on its model with `#[ControlledBy]`. `Access::flush()` is removed with every other static state: each container, so each test, gets a new registry. Remove the `Access::flush()` calls from your tests. `Access::cache()`, `Access::cachePath()`, `Access::discoverControlClasses()` and `discoverControlsWithin()` are removed: run `php artisan access:cache` and `php artisan access:clear`.

`Control::new()` and `Access::control(PostControl::class)` return the control instance of the registry, resolved through the container: one instance per control class and per container. A control can take constructor dependencies, and a test can swap one with `$this->app->instance(PostControl::class, $control)`.

`access:cache` now writes the control of each model instead of a list of controls. Run `php artisan access:cache` again after upgrading, or `php artisan optimize` in your deployment. A cached control class that was deleted since is skipped instead of failing.

`MissingControlException` is now final.

### Policies

A `ControlledPolicy` must name the control of its model. A policy whose `$control` is not the control registered for the model it decides on now throws `PolicyControlMismatchException`, which names both controls. In 0.x the decision and the controlled queries could use two different controls. Name the model's control in the policy:

```php
#[ControlledBy(PostControl::class)]
class Post extends Model {}

class PostPolicy extends ControlledPolicy
{
    protected string $control = PostControl::class;
}
```

### Abilities

The `allowed` callback of a perimeter receives a `Lomkit\Access\Ability` instead of a string. An ability is an immutable value object, open to any business ability:

```php
use Lomkit\Access\Ability;

Ability::view();           // also create(), update(), delete(), restore(), forceDelete()
Ability::of('approve');    // any business ability
$ability->is('view');      // compare by name, with an Ability or a string
$ability->name();          // 'view'
"{$ability} client posts"; // 'view client posts'
```

Type the second parameter of every `allowed` closure `Ability $ability`, and compare it with `$ability->is('update')` instead of a string comparison:

```php
// Before
ClientPerimeter::new()
    ->allowed(fn (User $user, string $method) => $user->can("{$method} client posts"))

OwnPerimeter::new()
    ->allowed(fn (User $user, $method) => match ($method) {
        'delete' => false,
        default => $user->can("{$method} own posts"),
    })

// After
use Lomkit\Access\Ability;

ClientPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))

OwnPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => ! $ability->is('delete') && $user->can("{$ability} own posts"))
```

A closure whose second parameter is untyped, or typed with a type that cannot receive an `Ability` such as `string` or `?string`, now throws `AbilityParameterTypeException` when the perimeter is declared, with the fix in the message. In 0.x it received a string; given the `Ability` object, its `===`, `match` and strict `in_array` comparisons would never match, and a `default` arm would grant every ability. The second parameter may also be typed `?Ability`, a union that contains `Ability`, `Stringable`, `object` or `mixed`: it then receives the `Ability` object. A closure that takes only the reader, such as `fn (User $user) => true`, needs no change.

Compare abilities with `is()`, never with `===`: two abilities with the same name are not the same object, and an ability restored by `unserialize()` is a new one.

Replace each read of the removed `methods` map with the ability it named:

```php
// Before
config('access-control.methods.view')

// After
Ability::view()
```

The same goes for `create`, `update`, `delete`, `restore` and `forceDelete`. `Control::viewMethod()` is removed too: replace `$this->viewMethod()` with `Ability::view()`.

`Control::abilityFor(string $policyMethod): Ability` maps a policy method to the ability its perimeters receive, and replaces the `methods` map. By default, `viewAny` and `view` map to `view`, `create`, `update`, `delete`, `restore` and `forceDelete` to themselves, and any other method to `Ability::of($method)`. A custom `methods` map becomes an override of `abilityFor()` on the control:

```php
// Before, in config/access-control.php
'methods' => [
    'viewAny' => 'list',
    // ...
],

// After, in the control
use Lomkit\Access\Ability;

public function abilityFor(string $policyMethod): Ability
{
    return match ($policyMethod) {
        'viewAny' => Ability::of('list'),
        default => parent::abilityFor($policyMethod),
    };
}
```

`Ability::of()` throws `InvalidAbilityException` for an empty name, a name with whitespace or a name that is not valid UTF-8, and so does `abilityFor()` for such a policy method.

`Control::applies()`, `Control::applicablePerimeters()` and `Perimeter::applyAllowedCallback()` take an `Ability` instead of a string, and `ControlledPolicy` passes the ability of each method. Update the calls, and the signatures of the methods your controls and perimeters override:

```php
// Before
$control->applies($user, 'update', $post);
$control->applies($user, 'viewAny', new Post);

// After
$control->applies($user, Ability::update(), $post);
$control->applies($user, $control->abilityFor('viewAny'), new Post);
```

A perimeter class that overrides `applyAllowedCallback()` declares its second parameter as `Ability $ability`, and compares it with `$ability->is('view')`. An override typed `string $method` is a fatal error, and an untyped one throws `AbilityParameterTypeException` when the control resolves its perimeters, instead of receiving an object that a string comparison would never match. An override typed `mixed` receives the `Ability` object:

```php
// Before
public function applyAllowedCallback(Model $user, $method): bool
{
    return $method === 'view';
}

// After
public function applyAllowedCallback(Model $user, Ability $ability): bool
{
    return $ability->is('view');
}
```

`make:control` now generates `fn (Model $user, Ability $ability)` closures, and its `control.stub` imports `Lomkit\Access\Ability`. If your application published `stubs/control.stub`, add `use Lomkit\Access\Ability;` to it, or the generated controls will not find the class.

laravel-rest-api has its own `Lomkit\Rest\Authorization\Ability` enum, closed to its standard actions. It is a different type: import the one of the package you call.

### Correspondence table

| 0.x | 1.0 |
| --- | --- |
| `'queries' => ['enabled_by_default' => true]` | `controlled()` on each query that must be filtered |
| `$control->queried($query, $user)` | `$query->controlledFor($user)` |
| `HasControlScope::resolveUser()` | `Auth::user()` |
| `HasControlScope::retrievingUser()` | Removed, no replacement |
| `->query(fn ($query) => $query)`, `->should(fn () => true)` on a global perimeter | `->unrestricted()` |
| `->query(fn ($query) => Model::query()->where(...))` | `->query(function ($query): void { $query->where(...); })` |
| `Access::controlForModel($model)` (returns `?Control`) | `Access::controlFor($model)` (throws `MissingControlException`) and `Access::hasControl($model)` |
| `(new Access)->addControl($control)`, `addControls()`, `discoverControls()` | `#[ControlledBy(PostControl::class)]` on the model |
| `Access::$controlDiscoveryPaths = [...]` | `Access::discoverControlsIn([...])` in a service provider's `boot()` |
| `Access::flush()` | Removed: the registry is a container singleton |
| `Access::cache()`, `Access::cachePath()`, `Access::discoverControlClasses()` | `php artisan access:cache`, `php artisan access:clear` |
| `config/access-control.php`, `--tag=access-control-config` | Removed: delete the published file |
| `config('access-control.methods.view')`, `$this->viewMethod()` | `Ability::view()` (and `create()`, `update()`, `delete()`, `restore()`, `forceDelete()`) |
| A custom `methods` map | `abilityFor()` overridden on the control |
| `->allowed(fn (User $user, string $method) => ...)`, or an untyped `$method` | `->allowed(fn (User $user, Ability $ability) => ...)`: an untyped or `string` parameter throws `AbilityParameterTypeException` |
| `$control->applies($user, 'update', $post)` | `$control->applies($user, Ability::update(), $post)` |
| `$ability === 'view'` | `$ability->is('view')` |
| `applyAllowedCallback(Model $user, $method)` overridden on a perimeter | `applyAllowedCallback(Model $user, Ability $ability)` |
