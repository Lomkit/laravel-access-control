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

`controlledFor()` applies the control for a reader you name, where `controlled()` reads the signed-in user: use it in jobs, listeners and commands, which have no signed-in user. `Control::queried()` is removed: replace its calls with `controlledFor()`:

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

A callback that returns another builder now throws `QueryCallbackReturnedAnotherBuilderException`, which names the perimeter and the model. In 0.x the returned builder was ignored, so its constraints were lost and the perimeter let every row through. Apply the constraints to the builder the callback receives instead:

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

The same rule applies to the ability that the `should`, `query` and `scoutQuery` callbacks receive as their third parameter (see [Perimeter callbacks](#perimeter-callbacks)).

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

`Control::applies()` and `Control::applicablePerimeters()` take an `Ability` instead of a string, and `ControlledPolicy` passes the ability of each method. Update the calls:

```php
// Before
$control->applies($user, 'update', $post);
$control->applies($user, 'viewAny', new Post);

// After
$control->applies($user, Ability::update(), $post);
$control->applies($user, $control->abilityFor('viewAny'), new Post);
```

A perimeter class that overrides `applyAllowedCallback()` moves to the `allows()` hook, which reads the ability from its context: see [Perimeter classes](#perimeter-classes).

`make:control` now generates `fn (Model $user, Ability $ability)` closures, and its `control.stub` imports `Lomkit\Access\Ability`. If your application published `stubs/control.stub`, add `use Lomkit\Access\Ability;` to it, or the generated controls will not find the class.

laravel-rest-api has its own `Lomkit\Rest\Authorization\Ability` enum, closed to its standard actions. It is a different type: import the one of the package you call.

### Perimeter classes

The `apply*Callback()` methods of `Perimeter` are now `final`: a perimeter class can no longer override them, and an override left in place is a fatal error when the class loads. A perimeter class overrides protected hooks instead. Each hook receives a `Lomkit\Access\AccessContext`, which gives the reader with `reader()` and the ability with `ability()`. The package builds the context; new information can be added to it in 1.x without changing the hook signatures.

| 0.x override | 1.0 hook | What it decides |
| --- | --- | --- |
| `applyAllowedCallback(Model $user, $method): bool` | `allows(AccessContext $context): bool` | Whether the perimeter applies to the reader, for the ability |
| `applyShouldCallback(Model $user, Model $model): bool` | `includes(Model $row, AccessContext $context): bool` | Whether a row belongs to the perimeter |
| `applyQueryCallback(Builder $query, Model $user): Builder` | `scope(Builder $query, AccessContext $context): void` | The rows of the perimeter, as constraints on `$query` |
| `applyScoutQueryCallback(ScoutBuilder $query, Model $user): ScoutBuilder` | `scoutScope(ScoutBuilder $query, AccessContext $context): void` | The rows of the perimeter, as Scout filters |

Rename each override, make it `protected`, and read the reader and the ability from the context. A rule is written once: either as a hook in the perimeter class, or as a callback in the control. Declaring the `allowed`, `should`, `query` or `scoutQuery` callback of a hook that the class overrides now throws `OverriddenPerimeterHookException`, which names the perimeter and the hook: in 0.x, an override that did not call its parent silently ignored the callback. Move the logic of the callback into the hook, or remove the override and keep the callback. A parent hook that has no callback throws `MissingPerimeterCallbackException`, so an override no longer calls it.

`allows()`:

```php
// Before
public function applyAllowedCallback(Model $user, string $method): bool
{
    return $user->is_active && $user->can("{$method} client posts");
}

// After
use Lomkit\Access\AccessContext;

protected function allows(AccessContext $context): bool
{
    $reader = $context->reader();

    return $reader instanceof User && $reader->is_active && $reader->can("{$context->ability()} client posts");
}
```

`includes()`, which now also receives the ability, so a perimeter can read the client's rows and update only its own:

```php
// Before
public function applyShouldCallback(Model $user, Model $model): bool
{
    return $model->client_id === $user->client_id;
}

// After
protected function includes(Model $row, AccessContext $context): bool
{
    $reader = $context->reader();

    if (! $reader instanceof User) {
        return false;
    }

    return $context->ability()->is('view')
        ? $row->client_id === $reader->client_id
        : $row->author_id === $reader->id;
}
```

`scope()`, which constrains the builder it receives and returns nothing:

```php
// Before
public function applyQueryCallback(Builder $query, Model $user): Builder
{
    return $query->where('client_id', $user->client_id)->where('archived', false);
}

// After
protected function scope(Builder $query, AccessContext $context): void
{
    $reader = $context->reader();

    $query
        ->where('client_id', $reader instanceof User ? $reader->client_id : 0)
        ->where('archived', false);
}
```

`scoutScope()`:

```php
// Before
public function applyScoutQueryCallback(ScoutBuilder $query, Model $user): ScoutBuilder
{
    return $query->where('client_id', $user->client_id);
}

// After
protected function scoutScope(ScoutBuilder $query, AccessContext $context): void
{
    $reader = $context->reader();

    $query->where('client_id', $reader instanceof User ? $reader->client_id : 0);
}
```

A hook receives the reader as `Model`, the type of every reader: a perimeter class cannot narrow it to `User` in its signature, so it checks the reader with `instanceof`, as above. The perimeter controls the rows of one model, so `$row` and `$query` are rows of that model.

`unrestricted()` throws `UnrestrictedPerimeterCallbackException` on a perimeter class that overrides `includes()`, `scope()` or `scoutScope()`: an unrestricted perimeter never runs them.

`Perimeter::admits(Model $reader, Ability $ability, Model $row): bool` tells whether the perimeter applies to the reader for the ability and contains the row, through its hooks or its callbacks. Inside a control, `applicablePerimeters(Model $reader, Ability $ability)` returns the perimeters that apply to the reader for the ability: together, they tell which perimeters grant a row.

`applyAllowedCallback()`, `applyShouldCallback()`, `applyQueryCallback()` and `applyScoutQueryCallback()` are internal: they take an `AccessContext` and are called by the control. Replace your calls with `admits()`, or with the policy and the controlled queries. `UntypedAbilityHookException` is removed, since `allows()` reads the ability from its context.

### Perimeter callbacks

The `should`, `query` and `scoutQuery` callbacks receive the ability after their other arguments. It is optional: the closures of 0.x keep working.

```php
OwnPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
    ->should(fn (User $user, Post $post, Ability $ability) => $ability->is('view')
        ? $post->client_id === $user->client_id
        : $post->author_id === $user->id)
    ->query(fn (Builder $query, User $user) => $query->where('client_id', $user->client_id)),
```

As for `allowed`, the parameter must accept an `Ability`, or the declaration throws `AbilityParameterTypeException`. The controlled queries and searches always pass `view`.

An `allowed` or `should` callback must return a boolean: any other value, such as `1`, `'yes'` or nothing, now throws `PerimeterCallbackResultException`, which names the perimeter and the callback, instead of being converted. Return `true` or `false`.

`QueryCallbackReturnedAnotherBuilderException` now names the perimeter and the model instead of the control. `MissingPerimeterCallbackException` is now final, and its message also names the hook to override.

### Readers of another type

A perimeter whose callbacks type the reader, such as `fn (User $user, ...)`, now applies only to the readers of that type. For another reader, such as an API client model, the perimeter does not apply: the policy and the controlled queries skip it, instead of failing with a `TypeError`. The package reads the type of the reader parameter of each callback once, when the perimeter is declared: a class, a nullable class, an interface, a union or an intersection. An untyped parameter, `Model`, `object` or `mixed` accepts every reader. Remove the guards that refused other readers by hand:

```php
// Before
->query(fn (Builder $query, Model $user) => $user instanceof User
    ? $query->where('client_id', $user->client_id)
    : $query->whereRaw('0 = 1'))

// After
->query(fn (Builder $query, User $user) => $query->where('client_id', $user->client_id))
```

The perimeter is skipped when any of its callbacks refuses the reader: type the callbacks of a perimeter with the same reader type.

### Immutable perimeters

A perimeter cannot change once it is declared. `allowed()`, `should()`, `query()`, `scoutQuery()` and `unrestricted()` return a new perimeter and leave the one they are called on unchanged, and return `static`. Chain the declarations, or keep the returned perimeter:

```php
// Before: each call changed $perimeter
$perimeter = OwnPerimeter::new();
$perimeter->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} own posts"));
$perimeter->query(fn (Builder $query, User $user) => $query->whereBelongsTo($user, 'author'));

// After
$perimeter = OwnPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} own posts"))
    ->query(fn (Builder $query, User $user) => $query->whereBelongsTo($user, 'author'));
```

A declaration whose result is dropped is lost: the perimeter then throws `MissingPerimeterCallbackException` when the callback is needed.

The callbacks are private properties of `Perimeter`. A subclass that assigned `$this->allowedCallback`, `$this->shouldCallback`, `$this->queryCallback` or `$this->scoutQueryCallback` now writes a property of its own, which the package never reads: declare the callbacks with the fluent methods, or override the hooks.

`Control::getPerimeters()` is removed. The perimeters of a control are built once by `perimeters()` and kept by the control; nothing can read or change them from outside. Inside a control, use `applicablePerimeters($reader, $ability)`; elsewhere, use the policy, `admits()` or the controlled queries.

### Pure perimeter lists

A control builds its perimeters once and keeps them for the whole process: a request, but also a queue worker or an Octane worker that serves many requests. `perimeters()` must therefore return the same perimeters every time it runs. In debug mode and in tests, each time the registry hands out a control, it builds `perimeters()` again and compares the result with the perimeters it keeps: the same classes in the same order, the same callbacks declared from the same lines, and the same values captured by the callbacks. A difference throws `ImpurePerimetersException`, which names the control. The check does not run when debug mode is off outside tests.

Move what `perimeters()` read when it built the perimeters into the callbacks, which run for each decision with the reader:

```php
// Before: the client of the first user is captured for the whole worker
protected function perimeters(): array
{
    $client = auth()->user()->client_id;

    return [
        ClientPerimeter::new()
            ->allowed(fn (User $user) => true)
            ->query(fn (Builder $query) => $query->where('client_id', $client)),
    ];
}

// After
protected function perimeters(): array
{
    return [
        ClientPerimeter::new()
            ->allowed(fn (User $user) => true)
            ->query(fn (Builder $query, User $user) => $query->where('client_id', $user->client_id)),
    ];
}
```

The same goes for a perimeter list that depends on the user, the request, the tenant, the configuration of the moment, the database or the clock. Captured values are compared by identity, except closures, compared by the line that declares them, and readonly objects such as `Ability`, compared by value: create any other object in a property of the control, or inside the callback.

### Removed helpers

`Control::appNamespace()` is removed. Read the namespace of the application with `app()->getNamespace()`.

### Generators

`make:control` without `--model` now names the model after the control, such as `App\Models\Post` for `PostControl`, where it used to write a placeholder that did not compile. It refuses, and writes nothing, when the model is not an Eloquent model class. `--model` also accepts a fully qualified class outside `App\Models`, such as `--model='Domain\Blog\Post'`.

`make:perimeter` now generates a perimeter class that overrides `allows()`, `includes()` and `scope()` and denies everything until you fill them. `make:control --perimeters=...` generates only the callbacks of the hooks each perimeter class does not override. The generated `control.stub` no longer carries descriptive docblocks and marks `perimeters()` with `#[\Override]`. If your application published `stubs/control.stub` or `stubs/perimeter.plain.stub`, publish them again with `php artisan stub:publish --force` or apply the same changes.

### Correspondence table

| 0.x | 1.0 |
| --- | --- |
| `'queries' => ['enabled_by_default' => true]` | `controlled()` on each query that must be filtered |
| `$control->queried($query, $user)` (removed) | `$query->controlledFor($user)` |
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
| `applyAllowedCallback(Model $user, $method)` overridden on a perimeter | `protected function allows(AccessContext $context): bool` |
| `applyShouldCallback(Model $user, Model $model)` overridden on a perimeter | `protected function includes(Model $row, AccessContext $context): bool` |
| `applyQueryCallback(Builder $query, Model $user)` overridden on a perimeter | `protected function scope(Builder $query, AccessContext $context): void` |
| `applyScoutQueryCallback(ScoutBuilder $query, Model $user)` overridden on a perimeter | `protected function scoutScope(ScoutBuilder $query, AccessContext $context): void` |
| `$perimeter->applyShouldCallback($user, $row)` called directly | `$perimeter->admits($user, $ability, $row)` |
| `$control->getPerimeters()` | Removed: `applicablePerimeters($reader, $ability)` inside the control |
| `$this->allowedCallback = ...` in a perimeter subclass | `->allowed(...)`, or the `allows()` hook |
| `$user instanceof User ? ... : $query->whereRaw('0 = 1')` in a callback | Type the reader: `fn (Builder $query, User $user) => ...` |
| `Control::appNamespace()` | `app()->getNamespace()` |
