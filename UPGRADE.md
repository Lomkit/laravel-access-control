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

### The control scope

`HasControlScope` is removed. A model that uses `HasControl` registers no global scope anymore, and `controlled()`, `controlledFor()` and `uncontrolled()` remove no scope from their query. Remove `withoutGlobalScope(HasControlScope::class)` from your queries, which read every row without it, or write `uncontrolled()` to state it on purpose, and remove the imports of the class:

```php
// Before
$posts = Post::withoutGlobalScope(HasControlScope::class)->get();

// After
$posts = Post::uncontrolled()->get();
```

`HasControlScope::resolveUser()` and `HasControlScope::retrievingUser()` go with the class. Controlled queries and controlled searches read the user with `Auth::user()` when they run. Replace `HasControlScope::resolveUser()` with `Auth::user()`, and remove the calls to `HasControlScope::retrievingUser()`:

```php
// Before
$user = HasControlScope::resolveUser();

// After
$user = Auth::user();
```

A controlled query no longer skips its control while the guard retrieves the user, where it used to read every row. Do not run a controlled query from an event of your user model that the guard fires while it retrieves the user, such as `retrieved`: the query asks the guard for the user again.

`controlled()` and `uncontrolled()` are now local scopes of the `HasControl` trait, declared with Laravel's `#[Scope]` attribute, instead of builder macros registered by `HasControlScope::extend()`. Remove the code that extends `HasControlScope` or calls its `extend()` method, and rename any `controlled`, `controlledFor` or `uncontrolled` method that a controlled model declares itself.

### Named readers

`controlledFor()` applies the control for a reader you name, where `controlled()` reads the signed-in user: use it in jobs, listeners and commands, which have no signed-in user. `Control::queried()` is removed: replace its calls with `controlledFor()`:

```php
// Before
$posts = Access::control(PostControl::class)->queried(Post::query(), $recipient)->get();

// After
$posts = Post::controlledFor($recipient)->get();
```

`controlledFor(null)` reads no row, as `controlled()` does without a signed-in user: a missing reader never means every row.

Both scopes list the `view` rows by default, and take the ability to list as their last argument, a name or an `Ability`. Use it to paginate or count the rows a reader may act on, in one query:

```php
Post::controlled('update')->paginate();
Post::controlledFor($user, Ability::delete())->count();
Comment::whereIn('post_id', Post::controlledFor($user, 'update')->select('id'))->get();
```

The list is the policy's own resolution for that ability: the perimeters that allow the reader for it, the highest level reached for it in each group, the boundary checked for it (so `readOnlyWhenNotNull('archived_at')` keeps an archived row in the `view` list and leaves it out of the `update` list), and the abilities a `ThroughParent` delegates (a child ability it does not delegate lists nothing). A name goes through `abilityFor()`, so `viewAny` lists `view`, a business ability such as `approve` lists its rows, and a name the control cannot map throws `InvalidAbilityException`. Without a reader, the list is empty whatever the ability. `create` throws `UnlistableAbilityException`, since the policy judges it only on a draft, in memory; a second ability throws `IgnoredArgumentException`: list one ability per query, and decide the others with `withAbilities()`. The list is what the control grants, like the `view` list already was: a rule that an overridden policy method adds, or a `Gate::before()`, does not change it, and `assertControlAgrees()` fails for that ability.

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

A `query` callback that adds no constraint to the builder it receives now throws `EmptyQueryCallbackException`. Laravel drops an empty group, so the other perimeters of the reader stopped applying, and the reader saw more or fewer rows than intended. A callback that only orders, limits or joins adds no constraint either: Laravel keeps only the `where` clauses of the group. A callback that adds its constraint under a condition throws for the readers it skips: add a constraint that matches no row for them, such as `$query->whereRaw('0=1')`, or refuse them in `allowed()`.

A `query` callback, or the `scope()` of a boundary class, that adds a join now throws `JoinInPerimeterException`, which names the perimeter or the boundary: Laravel drops a join added inside the nested `where` group these rules run in, so the rule broke the SQL or filtered nothing. Replace the join with `whereHas()` or an `exists` subquery.

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

An unrestricted perimeter takes no `query` or `should` callback and no search keys: declaring one of them with `unrestricted()` throws `UnrestrictedPerimeterCallbackException`. When an unrestricted perimeter applies to the reader, a controlled query and a controlled search get no perimeter constraint, the policy grants the row without calling any `should` callback, and the perimeters after it are not evaluated. The [boundary](#boundaries) of the control still applies.

This fixes a reader with both a global and an overlay permission, such as an administrator who also has "view shared posts": in 0.x the empty global group was dropped and the administrator only saw the shared posts. Such a reader now sees every row of the boundary, which may widen what your tests expect.

### Combining perimeters

`OverlayPerimeter` is removed, and so is `Perimeter::overlays()`. In 0.x, the order of the perimeters and the class of each one decided how they combined: the first perimeter that allowed the reader stopped the search, unless it extended `OverlayPerimeter`, and an overlay written after a perimeter that stopped was never read. In 1.0, two explicit rules replace them:

- a hierarchy is declared in a `Levels::of()` group, widest level first. For each ability, only the highest level the reader reaches applies: the levels below it are neither evaluated nor added to the SQL;
- every perimeter outside a group adds up with the others (OR), wherever it is written. A control can declare several groups next to loose perimeters.

Convert each control. The perimeters that stopped, the ones that did not extend `OverlayPerimeter`, go into a `Levels::of()` group, in the same order. The overlays stay outside the group, as plain perimeters:

```php
// Before
protected function perimeters(): array
{
    return [
        SharedPerimeter::new()->allowed(...)->should(...)->query(...),   // extends OverlayPerimeter
        GlobalPerimeter::new()->allowed(...)->unrestricted(),             // stops
        ClientPerimeter::new()->allowed(...)->should(...)->query(...),   // stops
        OwnPerimeter::new()->allowed(...)->should(...)->query(...),      // stops
    ];
}

class SharedPerimeter extends OverlayPerimeter {}

// After
use Lomkit\Access\Perimeters\Levels;

protected function perimeters(): array
{
    return [
        Levels::of(
            GlobalPerimeter::new()->allowed(...)->unrestricted(),
            ClientPerimeter::new()->allowed(...)->should(...)->query(...),
            OwnPerimeter::new()->allowed(...)->should(...)->query(...),
        ),
        SharedPerimeter::new()->allowed(...)->should(...)->query(...),
    ];
}

class SharedPerimeter extends Perimeter {}
```

Replace `extends OverlayPerimeter` with `extends Perimeter` in your perimeter classes, and remove the overrides of `overlays()`.

Review the controls one by one: a control that relied on a perimeter that stops to block an overlay written after it now widens access. In 0.x, a reader allowed by `ClientPerimeter` never reached an overlay written below it; in 1.0 that perimeter sits outside the group and adds its rows. When the overlay must not apply to the readers of a level, move it into the group as a level, or refuse those readers in its `allowed()`.

A level contains the levels below it: a reader who reaches the client level must see at least the rows of the own level. A perimeter that does not nest belongs outside the group. `assertLevelsNest()` checks it on your test data, and is the safety net of the conversion (see [Testing the levels](#testing-the-levels)).

`Levels::of()` throws `EmptyLevelsException` without a level, and `NestedLevelsException` when a level is itself a group.

`applicablePerimeters($reader, $ability)` returns the level the reader reaches in each group, followed by the loose perimeters that allow the reader, in the order they are written. When an unrestricted perimeter applies, it returns that perimeter alone.

### Boundaries

A control can declare a boundary: a condition that every row must meet, whatever the perimeter that grants it, such as the workspace or the tenant of the reader. The boundary applies as an AND around the perimeters, before the levels, even for an unrestricted perimeter, in the controlled queries and in the policy. Declare it in `boundary()`:

```php
use Lomkit\Access\Boundaries\Boundary;

protected function boundary(): Boundary
{
    return Boundary::where('workspace_id', fn (User $user) => $user->current_workspace_id)
        ->readOnlyWhenNotNull('archived_at');
}
```

`Boundary::where($column, $readerValue)` keeps the rows whose column equals the value the closure reads from the reader, an int or a string. `readOnlyWhenNotNull($column)` keeps a row readable while the column is not null, and refuses it to every other ability, such as `update` or `approve`: an archived post stays visible and can no longer be edited. The same declaration gives the SQL of the controlled queries and the check of the policy, which runs in memory, without a query.

A reader value of `null` closes the boundary: the reader reads no row and the policy refuses every ability, `create` included; the SQL never compares with `IS NULL`. A reader whose type the closure does not accept, such as a `Member` for `fn (User $user)`, is refused the same way. Any other value than an int, a string or `null` throws `InvalidBoundaryValueException`.

A boundary that a column cannot express is a class that extends `Boundary`. It overrides `scope()`, the SQL form, and may override `includes()`, the form in memory; both receive the `AccessContext`:

```php
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class TenantBoundary extends Boundary
{
    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        $query->whereIn('team_id', $reader instanceof User ? $reader->teams()->select('teams.id') : []);

        if (! $context->ability()->is('view')) {
            $query->where('locked', false);
        }
    }
}

protected function boundary(): Boundary
{
    return new TenantBoundary;
}
```

Without `includes()`, the policy checks the boundary with an `exists` query on the row; with it, in memory. Even an unrestricted perimeter grants a row without SQL only when the boundary is empty or checks in memory: otherwise the policy runs that `exists` on the boundary alone. The policy checks the boundary before the `should` callbacks of the perimeters. `boundary()` follows the rule of `perimeters()`: it is built once per process and must not read the user, the request or the tenant while it builds the boundary (see [Pure perimeter lists](#pure-perimeter-lists)).

Replace the overrides of `applies()` and `applyQueryControl()` that added such a condition by hand with a boundary:

```php
// Before
protected function applyQueryControl(Builder $query, Model $user): Builder
{
    return parent::applyQueryControl($query->where('workspace_id', $user->current_workspace_id), $user);
}

public function applies(Model $user, string $method, Model $model): bool
{
    return $model->workspace_id === $user->current_workspace_id && parent::applies($user, $method, $model);
}

// After
protected function boundary(): Boundary
{
    return Boundary::where('workspace_id', fn (User $user) => $user->current_workspace_id);
}
```

`Control::applies()` is now `final`, and `applyQueryControl()` and `noResultQuery()` are removed: an override left in place is a fatal error when the class loads.

A controlled search applies the boundary too: `Boundary::where()` gives its search keys, and a boundary class declares them in `searchKeys()` (see [Search](#search)).

### Inheriting the access of a parent

A child row often has exactly the access of its parent: a comment is visible to whoever may view its post. In 0.x each application wrote it by hand, in a `query` callback that repeated the parent's rules or called `controlled()` inside a `whereHas()`. Forgetting that inner `controlled()`, or the parent's control, opened every child row that had a parent. `Lomkit\Access\Perimeters\ThroughParent` declares it once:

```php
// Before
CommentPerimeter::new()
    ->allowed(fn (User $user) => true)
    ->query(fn (Builder $query) => $query->whereHas('post', fn (Builder $posts) => $posts->controlled()));

// After
use Lomkit\Access\Perimeters\ThroughParent;

protected function perimeters(): array
{
    return [
        ThroughParent::belongsTo('post', inverse: 'comments'),
        AuthorPerimeter::new()
            ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} own comments"))
            ->query(fn (Builder $query, User $user) => $query->where('author_id', $user->id)),
    ];
}
```

The control of the parent decides for the same reader and the same decision context, in the policy, in `controlled()`, in the row checks, in `withAbilities()`, `Access::preload()` and `reachableKeys()`. The parent's boundary applies to the parent, and the child's own boundary still applies to the child (AND).

**Only `view` is delegated by default.** Reusing every ability would let whoever may update a post delete any comment on it. `->delegates([...])` replaces the default with the abilities it lists: a list item delegates the same ability, a string key maps a child ability to an ability of the parent. Creating a comment on a post the reader may view is the usual mapping:

```php
ThroughParent::belongsTo('post')->delegates(['view', 'update', 'create' => 'view']);
```

For an ability it does not delegate, the perimeter does not apply. For a delegated one, it applies when its own `allowed()` callback, if it declares one, returns true, and the control of the parent reaches at least one perimeter for the reader and the mapped ability, the parent's boundary `admits()` included. The parent's `allowed()` callbacks may query: they run once per saved reader, ability and decision context, like any `allowed()` (see [Kept decisions](#kept-decisions)).

**A permission of the child as well (AND).** A child is often writable when the reader holds a permission of the child itself and reaches the parent. Declare that permission with `allowed()` on the `ThroughParent`:

```php
ThroughParent::belongsTo('vehicle')
    ->delegates(['view', 'create' => 'view'])
    ->allowed(fn (User $user, Ability $ability) => match ($ability->name()) {
        'view' => $user->can('vehicles.view'),
        'create' => $user->can('vehicles.manage'),
    });
```

- An ability the perimeter does not delegate is refused first, without calling `allowed`.
- When `allowed` returns false, the perimeter does not apply, and the parent is not touched: no query, and a draft's parent is not loaded.
- Otherwise the parent decides, as without `allowed`.

The callback receives the ability **of the child** (`create` above, not the parent's `view`), like any `allowed()`, and, like the other perimeters, runs once per saved reader, ability and decision context, until the next write forgets the kept decisions. A callback typed on another reader class leaves the perimeter out for other readers, as elsewhere. A second `allowed()` throws `DuplicatePerimeterCallbackException`. `access:check` prints `allowed` on the line of such a `ThroughParent`, and `--json` gives `parent.allowed`. `allowed()` can only narrow what the parent grants.

**Another rule next to it (OR).** A perimeter declared next to the `ThroughParent` grants other rows: the comments the reader wrote, whatever the post. `ThroughParent` takes no other callback: `should()`, `query()`, `searchKeys()`, `reindexWhen()` and `unrestricted()` throw `ThroughParentCallbackException`. So a permission of the reader goes in `allowed()` on the `ThroughParent`, a condition on the child row in the boundary of the child, and other rows in a perimeter next to it.

**The parent's policy is never called.** A rule that the parent's policy adds on top of its control (an update allowed on drafts only, a closed intervention) is not inherited, and delegating that ability would grant more than the Gate does on the parent. Move the rule into the parent's control, usually its boundary (`readOnlyWhenNotNull('closed_at')`, or a boundary class whose `scope()` and `includes()` refuse the other abilities than `view` on such rows): the parent's own lists become right too. Check it with `assertControlAgrees()` on the parent for that ability: it fails while the parent's policy adds a rule.

A rule that no query can express stays in the policies, from policy to policy: the child policy's `update()` calls `decideByControl()`, then `Gate::inspect('update', $child->parent)` if the control grants.

```php
class CommentPolicy extends ControlledPolicy
{
    protected string $control = CommentControl::class;

    public function update(Model $user, Model $comment): Response
    {
        $response = $this->decideByControl(__FUNCTION__, $user, $comment);

        return $response->allowed() ? Gate::forUser($user)->inspect('update', $comment->post) : $response;
    }
}
```

The parent check then applies to every grant of the child, including the ones of the perimeters next to the `ThroughParent`. The child's controlled lists (`controlled()`, `withAbilities()`, `Access::preload()`, the search) do not include that rule, so `assertControlAgrees()` on the child fails for that ability, on purpose. Only the policy may call the Gate: a perimeter, a boundary or a control never does. Otherwise the lists, the batches and the search would disagree with the policy, `Gate::before` would widen the access, a parent policy that checks its children would loop, and every row would cost a query.

**Migrating a hand-written write delegation.** First move the business rules of the parent's policy into the parent's control, as above. Then replace the hand-written perimeter, keeping the same parent ability for each child ability:

```php
// Before: view follows the parent's view, update and create follow the parent's update
VehicleItemPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => in_array($ability->name(), ['view', 'update', 'create'], true) && $user->can('vehicles.manage'))
    ->query(fn (Builder $query, User $user, Ability $ability) => $query->whereHas(
        'vehicle',
        fn (Builder $vehicles) => $vehicles->controlledFor($user, $ability->is('view') ? 'view' : 'update'),
    ));

// After
ThroughParent::belongsTo('vehicle')
    ->delegates(['view', 'update', 'create' => 'update'])
    ->allowed(fn (User $user) => $user->can('vehicles.manage'));
```

Compare the abilities one by one: an ability missing from `delegates()` is refused (`delete` above), and mapping `view` to the parent's `view` lets every reader of the vehicle see its items. A helper that required the parent's `update` for every ability maps each of them to `update` (`'view' => 'update'`).

**Drafts.** A draft is not in the database, so `create` with a draft loads its parent by the foreign key (one query) and asks the control of the parent to decide on that stored parent for the mapped ability. A draft without a parent key, or whose parent does not exist, is refused. A relation loaded on the draft is ignored: only the foreign key counts. A draft of a business ability, such as `record`, is judged the same way, but only when `delegates()` lists that ability (`->delegates(['view', 'record' => 'update'])`): otherwise the perimeter does not apply.

**Decisions without a row.** `viewAny`, `create` without a draft and a business ability without a row have no row to check: the control grants them as soon as the reader reaches at least one perimeter, and a `ThroughParent` counts only under the conditions above. Without a row, the child through the parent is therefore the parent for the mapped ability, AND the child's own `allowed()`; the parent's policy is never called. For a reader who holds the permission of the child, reaches nothing on the parent, and reaches no other perimeter of the child:

| Decision | Answer |
| --- | --- |
| `viewAny` | 403 `no_perimeter`, not 200 with an empty list |
| `controlled()` | No row (`0=1`) |
| `create` without a draft | 403 `no_perimeter`, before the 422 of laravel-rest-api's validation |
| `create` with a draft | 403 `no_perimeter`: nothing is reached, so the draft is never judged |

When another perimeter of the child is reached, `viewAny` and `create` without a draft are granted, and a draft that no reached perimeter takes is refused with `outside_rows`, or `outside_boundary` when the child's boundary refuses it. A reader who reaches the parent but has no row there gets 200 with an empty list: `viewAny` is granted and `controlled()` lists nothing.

Where the child must be listable, grant the parent ability too. A `ControlledPolicy` may also override `viewAny()`: the lists stay controlled, so nothing opens, but `viewAny` then no longer says what the control says. It works; it is not recommended.

It is on purpose:

- **Levels.** A group keeps the first level whose `allowed` accepts. A `ThroughParent` reached without access to the parent would, as the top level, hide the levels below it, and the reader's own rows would vanish.
- **No `allowed()` of its own.** A `ThroughParent` without `allowed()` would be reached by every reader, so `viewAny` and `create` would answer yes for everyone.
- **`create` without a draft means "somewhere".** A yes with no reachable parent would make laravel-rest-api's `authorized` flags and create buttons lie, and the creation would end in `outside_rows` anyway.
- **An empty list means no row yet.** A reader who reaches the parent gets 200 and an empty list while the parent has no row, and sees the rows as soon as they exist. With nothing reached, `controlled()` is `0=1` whatever the rows: a 200 would present a list the reader can never fill as one that is only empty for now.

**Validation.** The control validates its `ThroughParent` perimeters when it builds its perimeters, at its first use, or when the registry resolves it in debug mode and in tests, before any query:

| Mistake | Exception |
| --- | --- |
| The relation does not exist on the child model | `InvalidParentRelationException` |
| The relation is not a `belongsTo` (`hasMany`, `morphTo`, `belongsToMany`...) | `InvalidParentRelationException`: other relations come in 1.1 |
| `inverse` is not the `hasMany` of the parent on the same keys | `InvalidParentRelationException` |
| No control is registered for the parent model | `MissingParentControlException` |
| The parents lead back to the child, directly or through several hops | `ParentCycleException`, naming the cycle |
| `delegates()` gets an empty list, an ability twice, or something else than an ability | `InvalidDelegationException` |
| The relation method runs a query or reads the schema while it builds the relation | `RelationInspectionQueryException`, naming the model, the relation, the connection and the query |

`inverse` names the `hasMany` from the parent to its children. The search uses it to reindex the children when the parent's keys change, so a model that uses `ControlledSearchable` requires it (see [Searching through a parent](#searching-through-a-parent)). On a model that is not searchable it is optional, and validated when given.

**A model on a connection configured later.** The control reads the relation of a `ThroughParent`, and its `inverse`, without resolving a database connection: Eloquent gets a connection without PDO for the call of the relation method, and the control keeps only the related model and the key names. So a model on a connection configured at runtime, such as a tenant connection before the tenant is initialized or a connection opened with `DB::connectUsing()`, is validated outside that context, the same way: by `access:check`, `access:cache` (so `php artisan optimize`) and `make:control --parent`, and when the listeners that keep the search keys fresh build the controls on the save of a model of another connection. The decisions still use the real relation on the real connection, so the declaration is the same inside and outside that context. A relation method that runs a query while it builds the relation throws `RelationInspectionQueryException`, which names the model, the relation and the connection: build the relation from its keys alone, and move the query out of the method. The connection resolver is swapped for the call of the relation method only, which is safe under Octane since a worker serves one request at a time; a relation method that reads `getConnection()->getDriverName()` gets a placeholder name during that call, and a related model that the call instantiates for the first time boots against the same placeholder connection: a `booted()` that runs a query throws `RelationInspectionQueryException`.

`php artisan make:control CommentControl --parent=post` writes the `ThroughParent::belongsTo('post')` entry, and refuses a relation that is not a `belongsTo` of the model.

### Testing the levels

The `Lomkit\Access\Testing\InteractsWithAccess` trait adds three assertions to your tests: `assertLevelsNest()` for the levels (below), `assertControlAgrees()` for the decisions (see [Testing the decisions](#testing-the-decisions)) and `assertSearchAgrees()` for the search (see [Testing the search](#testing-the-search)). `assertLevelsNest()` checks, for each reader and each pair of adjacent levels of every group, that no row of the lower level, inside the boundary, is missing from the upper level:

```php
use Lomkit\Access\Testing\InteractsWithAccess;

class PostControlTest extends TestCase
{
    use InteractsWithAccess;

    public function test_its_levels_nest(): void
    {
        $this->assertLevelsNest(PostControl::class, readers: [$this->author, $this->clientManager, $this->admin]);
    }
}
```

It reads the rows of the `query` callbacks with the `view` ability, not the `allowed` callbacks (an ability passed after the readers throws `IgnoredArgumentException`), and compares them with `not exists` on the key, so a `NULL` in a column cannot hide a missing row. It proves the nesting on the rows of the test only: seed the rows each level reaches.

### Testing the decisions

`assertControlAgrees()` checks that every way of deciding gives the same answer for a reader. For each ability and row it compares four paths:

- the Gate row by row, with no decision kept;
- the Gate after `Access::preload()`;
- the Gate after `withAbilities()`;
- for every ability but `create`, whether `controlledFor($reader, $ability)` lists the row.

```php
public function test_its_decisions_agree(): void
{
    foreach ([$this->author, $this->clientManager, $this->admin] as $reader) {
        $this->assertControlAgrees(PostControl::class, $reader, Post::uncontrolled()->get());
    }
}
```

The abilities default to `view`, `update` and `delete`; pass others as the fourth argument, such as `['view', 'update', 'approve']`. A failure names the reader, then each row key and ability that disagree, with the answer of each path: `granted`, a refusal code, or `denied without a code` when no policy decided. The usual cause is a `should` callback or an `includes()` hook that describes other rows than its `query()`. A policy method that adds its own rule to an ability, such as an `update()` override that refuses some rows, makes the assertion fail for that ability on purpose: the Gate grants it on fewer rows than `controlled('update')` lists, so move the rule into the control. `create` is compared row by row only, since it is judged on drafts, and `viewAny` too, since it decides without a row. The rows must be saved rows of the control's model, since a draft is judged only in memory; it proves the agreement on the rows of the test only. The kept decisions are flushed before each path and after the last one.

The tests need no reset trait for the kept decisions: they live in a scoped instance of the container, so each test starts with none, and every write through a Laravel connection flushes them, `attach()` and writes under `Event::fake()` included, as long as the connection was opened before `Event::fake()` (a connection opened after it sends its events to the fake and does not flush). After a permission changed in memory, such as a `Gate::define()` between two checks, call `Access::forgetDecisions()` before the next check.

### Search

A controlled search is one query to the search engine, with all the filtering inside it. In 0.x, `controlled()` chained the `scoutQuery` callbacks of the perimeters with AND, so a reader with two perimeters found only the rows both opened, and the boundary was ignored. In 1.0, each indexed document carries the keys that open it, each reader has keys, and the search keeps the documents that share at least one key with the reader: one filter gives the union of the perimeters, levels and boundary included.

#### The model

Replace Scout's `Searchable` trait with `ControlledSearchable`, which includes it. `toSearchableArray()` does not change: the trait adds the keys to each document as Scout metadata, the way Scout adds `__soft_deleted`.

```php
// Before
use Laravel\Scout\Searchable;

class Post extends Model
{
    use HasControl, Searchable;
}

// After
use Lomkit\Access\Search\ControlledSearchable;

#[ControlledBy(PostControl::class)]
class Post extends Model
{
    use HasControl, ControlledSearchable;
}
```

Each document then carries two more attributes:

- `_access`: the keys of every perimeter that declares search keys, such as `["client.7", "author.42"]`;
- `_boundary`: the keys of the boundary, such as `["workspace_id.3"]`.

Remove from `toSearchableArray()` the attributes you added only for the `scoutQuery` callbacks. A model that overrides `makeSearchableUsing()` must return `$this->withAccessKeys($models)`, or its documents carry no keys and no controlled search finds them:

```php
public function makeSearchableUsing(Collection $models): Collection
{
    return $this->withAccessKeys($models->load('tags'));
}
```

Calling `controlled()` on the search of a model that still uses `Searchable` throws `MissingControlledSearchableException`.

#### Search keys instead of `scoutQuery`

`Perimeter::scoutQuery()` is removed, with the `scoutScope()` hook. Each perimeter that searches declares its keys with `searchKeys()`: a name, the keys of a row, and the keys of a reader. A row is found when one of its keys is one of the reader's:

```php
// Before
ClientPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
    ->query(fn (Builder $query, User $user) => $query->where('client_id', $user->client_id))
    ->scoutQuery(fn (ScoutBuilder $query, User $user) => $query->where('client_id', $user->client_id)),

// After
ClientPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
    ->query(fn (Builder $query, User $user) => $query->where('client_id', $user->client_id))
    ->searchKeys(
        name: 'client',
        row: fn (Post $post) => $post->client_id,
        reader: fn (User $user) => $user->client_id,
    ),
```

- The name is required, made of lowercase letters, digits and underscores, and unique in the control: the package prefixes each key with it, such as `client.7`, so two perimeters never open each other's rows. An invalid name throws `InvalidSearchKeyNameException`, a duplicate `DuplicateSearchKeyNameException`.
- Each side returns a value, a list of values, or a list of tuples: `[3, 7]` gives `client.3` and `client.7`, `[['fr', 7]]` gives `client.fr.7`. Use a tuple when a key depends on the decision context, such as the brand of the request (see below). A value is an int, a string or a backed enum; anything else throws `InvalidSearchKeyValueException`.
- `null` gives no key, never a key that matches `null`. A reader with no key finds nothing.
- A row callback that reads a relation declares it in `load:`. The relations are loaded once per indexed batch, on a copy of the rows, and the keys are computed with lazy loading disabled: a relation read without `load:` throws `UndeclaredSearchKeyRelationException`.

```php
->searchKeys(
    name: 'shared',
    row: fn (Post $post) => $post->sharedWith->modelKeys(),
    reader: fn (User $user) => $user->id,
    load: ['sharedWith:id'],
)
```

The `reader:` closure may take the `AccessContext` as its second parameter, the same object the hooks of a perimeter class receive, so a key that depends on the brand of the request reads it from the [decision context](#the-decision-context), never from a second source such as a global that a job, a command or a test could set differently. Its ability is always `view`, since a search only finds the rows the reader may view. The `row:` closure never receives a context, since the rows are indexed outside any request: the context dimension of a key comes from a column of the row.

```php
use Lomkit\Access\AccessContext;

->searchKeys(
    name: 'author',
    row: fn (Post $post) => [[$post->brand_id, $post->author_id]],
    reader: fn (User $user, AccessContext $context) => [[$context->decisionContext()['brand'], $user->id]],
)
```

A second parameter whose type does not accept an `AccessContext`, or a third parameter without a default value, throws `InvalidSearchKeyReaderException` when the keys are declared, naming the perimeter or the boundary and the key name. A `reader:` closure of one parameter is called with the reader alone.

The levels apply as in the queries: only the highest level the reader reaches gives its reader keys. An unrestricted perimeter gives no key filter: the search keeps every row of the boundary. A perimeter that applies to the reader but declares no search keys adds no row to the search, which is then narrower than the query, never wider. Search keys on an unrestricted perimeter throw `UnrestrictedPerimeterCallbackException`.

#### The boundary

`Boundary::where()` gives its search keys from the same declaration. A boundary class declares its own in `searchKeys()`:

```php
use Lomkit\Access\Search\SearchKeys;

class TenantBoundary extends Boundary
{
    protected function searchKeys(): ?SearchKeys
    {
        return SearchKeys::of(
            name: 'tenant',
            row: fn (Model $row) => $row->tenant_id,
            reader: fn (User $user) => $user->tenant_id,
        );
    }
}
```

The `reader:` closure of `SearchKeys::of()` may take the `AccessContext` as its second parameter too, like the `scope()` of the boundary class, so a boundary that reads the brand of the request finds the same rows in SQL and in a search. The reader value of `Boundary::where()` receives the reader alone, in SQL as in a search.

A controlled search on a search engine whose control has a boundary class without `searchKeys()` throws `UnsearchableBoundaryException`, which replaces the exception of the same name that 0.x threw for every boundary. A reader whose boundary value is `null` finds nothing.

#### `controlled()` or `uncontrolled()`

The search of a `ControlledSearchable` model must say whether it is controlled. A search that runs without `controlled()` or `uncontrolled()` throws `UndeclaredSearchScopeException`, and declaring both throws `ConflictingSearchScopeException`:

```php
Post::search('budget')->controlled()->paginate(25);   // the rows the signed-in user may view
Post::search('budget')->controlledFor($user)->get();   // the rows a named reader may view (jobs, listeners, commands)
Post::search('budget')->uncontrolled()->paginate(25); // every row, on purpose (back office, exports)
Post::search('budget')->paginate(25);                  // throws UndeclaredSearchScopeException
```

Without a signed-in user, with `controlledFor(null)`, or for a reader that no perimeter allows, a controlled search returns an empty page without calling the engine.

A search always finds the rows the reader may view: `controlled()` and `controlledFor()` take no ability, and one given after the reader throws `UnsearchableAbilityException`, as does one given to `Access::searchToken()` or `assertSearchAgrees()`. The search keys of a reader do not depend on the ability, so a search for `update` would be wider than its SQL. Search, then decide the other ability on the results with `reachableKeys()` or `Access::preload()`.

#### Engines

Declare the two attributes as filterable in the settings of each searchable model. `Access::searchFilterables()` returns `['_access', '_boundary']`:

```php
// config/scout.php, Meilisearch
'meilisearch' => [
    'index-settings' => [
        Post::class => ['filterableAttributes' => [...Access::searchFilterables(), 'status']],
    ],
],

// Typesense: in the collection schema
['name' => '_access', 'type' => 'string[]'],
['name' => '_boundary', 'type' => 'string[]'],

// Algolia: in attributesForFaceting
'attributesForFaceting' => ['filterOnly(_access)', 'filterOnly(_boundary)'],

// Turbopuffer: in the schema of the model, which Scout sends with each write
'turbopuffer' => [
    'model-settings' => [
        Post::class => [
            'schema' => [
                '_access' => ['type' => '[]string', 'filterable' => true],
                '_boundary' => ['type' => '[]string', 'filterable' => true],
            ],
        ],
    ],
],
```

- Algolia, Meilisearch and Typesense filter the documents with `whereIn`. Scout's `null` engine is accepted too, since it finds nothing.
- Any other engine is refused with `UnsupportedSearchEngineException`: an engine that ignored `whereIn` would return every document. If your engine renders `whereIn` on an array attribute as "at least one element in common", trust it in a service provider:

```php
Access::trustSearchEngine(ElasticsearchEngine::class);
```

A trusted engine must:

- render `whereIn` on an array attribute as "at least one element in common", and AND the fields together, `_boundary` with `_access`;
- apply the `whereIns` of the search on every read path: `get()`, `paginate()`, `simplePaginate()`, `keys()`, `raw()` and `cursor()`;
- write `scoutMetadata()` with the document, as the Meilisearch and Typesense engines do: otherwise no key is indexed and a controlled search finds nothing;
- for laravel-rest-api, have an engine profile that implements `MatchesStringLists`, or the search throws `UnsupportedRestSearchException` (see [laravel-rest-api](#laravel-rest-api)).

It needs nothing for a closed reader, since the package never calls the engine for one. On Algolia, Meilisearch, Typesense, Turbopuffer and a trusted engine alike, a search callback such as `Post::search('budget', fn ($client, $query, $options) => ...)` receives the options that hold the access filter: pass them on to the engine, never rebuild them.

- The `database` engine searches with SQL: a controlled search applies the control's SQL inside the search query, through Scout's `query()` callback, which still runs the callback you pass to `query()`. No keys are computed for it.
- The `collection` engine paginates before it filters: a controlled search throws `UnsupportedSearchEngineException`. Use the `database` engine, in tests too.
- Turbopuffer is filtered with its native `ContainsAny` operator, since Scout renders `whereIn` as `In`, which compares the whole array. A controlled search adds `['_access', 'ContainsAny', [...]]` (and the same for `_boundary`) to the native filters in `options['filters']`, joined with `And` to the filters you pass there, before or after `controlled()`; the filter is applied again when the search runs, so a later `options()` or `whereIn()` cannot remove or widen it. Declare the schema above before the first write: Turbopuffer sets the type of an attribute on its first write and cannot change it, so a namespace written without it must be deleted (`scout:flush`) and imported again.

The search engine is the only barrier: an index that lags behind can show a row that just left the reader's perimeters until it is indexed again. The same holds when a key rule changes: documents keep the keys of the former rule until they are indexed again. Declare what reindexes the documents when a key reads another model in [Keeping the keys fresh](#keeping-the-keys-fresh).

#### Reindex

After you deploy the new search keys, push the new settings and reindex every `ControlledSearchable` model:

```bash
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Post"
```

With Typesense, delete the collection first (`php artisan scout:delete-index posts`), so that it is created again with the new fields. Reindex the same way each time the `row` side of a search key rule or the boundary changes, as you would after changing `toSearchableArray()`: `scout:import` updates the documents in place, and until it has run they keep the keys of the former rule. When you narrow a rule to close an access, run it right away; to close it at once, run `scout:flush` before `scout:import`. A change of the `reader` side needs no reindex: the reader keys are computed at each search.

#### Keeping the keys fresh

A document's keys are computed when it is indexed. Scout reindexes a document when its own model is saved. When a key reads another model, such as `post → project → client_id`, a change of that model used to leave the documents with their former keys until the next `scout:import`: a former key can show a document to a reader who lost access. Declare when to reindex with `reindexWhen()`:

```php
// Before: the posts of a project kept the keys of its former client until the next scout:import
ClientPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
    ->query(fn (Builder $query, User $user) => $query->whereRelation('project', 'client_id', $user->clients->modelKeys()))
    ->searchKeys(
        name: 'client',
        row: fn (Post $post) => $post->project->client_id,
        reader: fn (User $user) => $user->clients->modelKeys(),
        load: ['project:id,client_id'],
    ),

// After: when a project changes client, its posts are reindexed by a job, in chunks, after the commit
ClientPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
    ->query(fn (Builder $query, User $user) => $query->whereRelation('project', 'client_id', $user->clients->modelKeys()))
    ->searchKeys(
        name: 'client',
        row: fn (Post $post) => $post->project->client_id,
        reader: fn (User $user) => $user->clients->modelKeys(),
        load: ['project:id,client_id'],
    )
    ->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts()),
```

- The rows are reindexed when a `Project` is updated and one of the `changed` attributes changed (as Eloquent's `wasChanged()` compares them), and when a `Project` is created, deleted (soft or forced) or restored. A save that changes no listed attribute queues nothing.
- `rows` receives the project and returns a query or a relation of the controlled model. The rows of the project's previous state are reindexed too, which matters when the watched model holds the link, such as an `Assignment` moved from one post to another; so are the rows of a deleted project.
- The job carries no full copy of the project, the way `SerializesModels` carries only a key. For a created or restored project it carries the key, and loads the row when it runs. For an update it carries the key and the previous values of the `changed` attributes only, and builds the previous state from the fresh row; an update whose row is gone when the job runs is skipped, since its deletion queued its own job. For a deleted project it carries the attributes without the model's `$hidden` columns, so a `rows` callback must not depend on a hidden attribute.
- The job is queued after the commit, always, whatever Scout's `after_commit` option, on Scout's queue connection and queue (`scout.queue`). Without a queue it runs synchronously after the commit. A rolled back transaction queues nothing. It reindexes the rows in chunks of `scout.chunk.searchable` through Scout, whose `makeSearchableUsing()` recomputes the keys, and removes from the index the rows whose `shouldBeSearchable()` is now false. Inside `withoutSyncingToSearch()` of the controlled model, nothing is queued.
- `reindexWhen()` comes after `searchKeys()` on the same perimeter, or throws `ReindexWithoutSearchKeysException`. A class that is not an Eloquent model throws `InvalidReindexModelException`, an empty or blank attribute `InvalidReindexAttributesException`, a `rows` callback that does not accept the model, or returns anything but a query or relation of the controlled model, `InvalidReindexRowsException`. A perimeter may watch several models, once each: a second `reindexWhen()` for the same model, or for a parent or child class of a watched model, throws `DuplicateReindexException`.
- A queue worker that only updates a project must know that posts depend on it, without ever building `PostControl`. The package listens to every Eloquent `created`, `updated`, `deleted` and `restored` event and looks up the controls that watch the model: about a microsecond for a model nobody watches. The watched models are read from the discovered controls, so a control that declares `reindexWhen()` must be discovered (`app/Access/Controls`, or `Access::discoverControlsIn()`): one linked only by `#[ControlledBy]` throws `UnwatchableReindexException` when it is built. So does one whose model does not use `ControlledSearchable`, unless a searchable child inherits its keys (see [Searching through a parent](#searching-through-a-parent)): only that child must then be discovered. `php artisan access:cache` caches the watched models with the controls, and the cache is used as is, the way Laravel uses its route and event caches: a deployment runs it again, which `php artisan optimize` does. A stale cache throws `UnwatchableReindexException` when a control whose `reindexWhen()` it does not know is built.

A custom pivot reindexes the documents it links with the `ReindexesSearch` trait:

```php
public function sharedWith(): BelongsToMany
{
    return $this->belongsToMany(User::class)->using(PostShare::class);
}

class PostShare extends Pivot
{
    use ReindexesSearch;
}
```

- `attach()`, `detach()`, `sync()`, `toggle()` and `updateExistingPivot()` on a relation that uses the pivot fire its model events. Declare `->using(PostShare::class)` on both sides of the relation, otherwise `attach()` and `detach()` from the other side write the pivot table with a plain query, fire no event, and the documents keep stale keys. Each event reindexes the linked models that use `ControlledSearchable`: both sides when both are searchable. The job is unique per document and queued after the commit: fifty attaches queue one job per post. Without a queue, the jobs run as the pivots are written, so wrap a bulk change in `DB::transaction()` to reindex each document once.
- A pivot written outside its relation, such as `PostShare::create([...])`, does not know the models it links: the write is refused with `UnresolvablePivotException` before it reaches the database. So is a pivot that links no `ControlledSearchable` model, with `MissingControlledSearchableException`. The pivot keys must reference the primary keys of the models: a relation with a custom `parentKey` or `relatedKey` is not supported.

What Eloquent cannot see stays with the application, which calls Scout:

```php
Post::where('project_id', $id)->update(['author_id' => $newAuthor]); // mass update: no event
Post::where('project_id', $id)->searchable();                       // Scout reindexes in chunks

$post->sharedWith()->attach($bob); // plain pivot, without ReindexesSearch: no event
$post->searchable();
```

The same holds for `DB::table()` writes, `Model::withoutEvents()`, `withoutSyncingToSearch()`, pivot queries such as `newPivotStatement()` and cascades done by the database.

The document's own model needs nothing: Scout reindexes it when it is saved. When the model overrides `searchIndexShouldBeUpdated()` and it returns `false`, the package compares the keys of the row before and after the save, after the commit, each state loading its own `load:` relations (so a changed `project_id` counts), and reindexes the document when they differ: keep your override for the other attributes.

The engine stays the only barrier, and freshness has a limit: between the change and the end of the job, the documents keep their former keys. Without a queue the window closes right after the commit; with one it lasts as long as the queue's backlog, and a failed job leaves the keys stale until it is retried or the model reindexed. Turn on Scout's `after_commit` option so that Scout's own indexing never writes the keys of a change that is rolled back.

#### Searching through a parent

A searchable child is found through its parent with the same rule as in SQL: the parent's boundary AND the parent's access keys must both open the parent for the reader. The child's own filter is `_boundary` AND `_access`, and `_access` is a union, so each pair of a parent boundary key and a parent access key becomes one composite key in the child's `_access`:

| Parent document | Keys written on the child |
| --- | --- |
| `_boundary: [workspace_id.1]`, `_access: [author.7, client.3]` | `through.thread.2.workspace_id.1`, `through.thread.2.workspace_id.1.author.7`, `through.thread.2.workspace_id.1.client.3` |
| No boundary, `_access: [client.3]` | `through.thread.0`, `through.thread.0.client.3` |

The reader builds the same keys from the parent's filter for the reader: `through.thread.2.workspace_id.1.author.7` for an author, and the boundary key alone, `through.thread.2.workspace_id.1`, for a reader the parent does not restrict. The number after the relation counts the components of the boundary key (`0` when the parent has no boundary), so a key always splits back into one boundary key and one access key and two pairs never share a key. Nested parents compose: a reply through its thread through its forum carries `through.thread.0.through.forum.0.client.3`. A child carries, per parent, the parent's boundary keys times its access keys plus one: usually one boundary key, so one key more than the parent has. `through` is reserved: a perimeter of a control with a `ThroughParent` that names its keys `through` throws `DuplicateSearchKeyNameException`.

The relation of the parent is loaded with its own `load:` relations when the child is indexed. A soft-deleted parent is not loaded, so its children lose the keys they inherit from it. On a searchable child, the control throws `UnsearchableThroughParentException` when it is built:

- when a `ThroughParent` has no `inverse`, or a parent further up the chain has none;
- when it delegates `view` to another ability of the parent: a search only knows the keys that open the parent for `view`;
- when the parent's boundary has no search form.

`assertSearchAgrees()` compares the search with the SQL on a searchable child as on any model.

The children follow their parent, through the watch map, jobs queued after the commit, unique jobs and chunks, as for `reindexWhen()`:

- **The parent's keys change.** On each update of the parent, the package compares the parent's keys before and after it, each state loading its own relations, and reindexes the children through `inverse` when they differ. An update that leaves the keys unchanged queues nothing, and creating a parent queues nothing.
- **The parent is reindexed by its own `reindexWhen()`.** When a grandparent changes, the children of the rows the parent's rule reindexes are reindexed too, through every level of nesting. The parent needs neither to be searchable nor to be discovered: a control whose model does not use `ControlledSearchable` may declare `reindexWhen()` when a searchable child inherits its keys through a `ThroughParent` with `inverse`, and the job then reindexes only the children. Without such a child, building that control still throws `UnwatchableReindexException`, since nothing would use its keys.
- **The parent is deleted, soft-deleted or restored.** Its children are reindexed, and lose or regain the keys they inherit.
- **A child moves to another parent.** Scout reindexes the child itself, as any saved model.

The control of a searchable child watches its parent and the models its parent watches, so it must be discovered, as a control that declares `reindexWhen()`. A foreign key that the database sets to null on its own, without an Eloquent event, is not seen: reindex those children yourself, as for the other writes Eloquent cannot see.

#### Search from the browser

A front end that queries Meilisearch, Typesense or Algolia directly needs a key that finds only what its reader may see. `Access::searchToken()` signs the reader's filter into a key derived from a search-only key, and the browser cannot widen it:

```php
use Lomkit\Access\Access;
use Lomkit\Access\Search\Tokens\SearchTokenKey;

class PostSearchTokenController
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = Access::searchToken(
            reader: $request->user(),
            model: Post::class,
            key: SearchTokenKey::meilisearch(uid: config('services.meilisearch.search_key_uid'), key: config('services.meilisearch.search_key')),
            expiresAt: now()->addMinutes(15),
        );

        return response()->json($token); // {"token": "...", "index": "posts", "expires_at": "2026-11-14T10:15:00+00:00"}
    }
}
```

- The engine is the one the model searches with (`searchableUsing()`): a tenant token for Meilisearch, a scoped search key for Typesense, a secured API key for Algolia. Pass the key of that engine, `SearchTokenKey::meilisearch(uid:, key:)`, `SearchTokenKey::typesense(key:)` or `SearchTokenKey::algolia(key:)`, or `MismatchedSearchTokenKeyException` is thrown. The package has no configuration file: read the key from your own, such as `config/services.php`.
- The filter in the token is the one `controlled()` applies, rendered in the engine's syntax from the same `searchFilter()`, with the same meaning: a document is found when it shares at least one key with the reader, inside the boundary. A reader that `controlled()` would show an empty page (no reader, no perimeter that allows them, no key) gets a token whose filter asks for an `_access` key that no document can carry, so it finds nothing. An unrestricted reader without a boundary gets a token with no filter, over the whole index of the model.
- The token keeps the keys of its reader until it expires: a permission change reaches the browser at the next token. The expiry is required, and one that is not in the future throws `PastSearchTokenExpiryException`. Keep it short, from a few minutes to an hour, and fetch a new token when it expires.
- Never sign with the admin key: a key equal to the one Scout uses (`scout.meilisearch.key`, `scout.typesense.client-settings.api_key`, `scout.algolia.secret`) throws `AdminSearchTokenKeyException`. Create a search-only key for each engine:
  - Meilisearch: `POST /keys` with the master key and `{"actions": ["search"], "indexes": ["posts"], "expiresAt": null}`; pass the `uid` and the `key` it returns. The token is limited to the model's index in its `searchRules`.
  - Typesense: `POST /keys` with the admin key and `{"description": "Search posts", "actions": ["documents:search"], "collections": ["posts"]}`; pass the `value`, which Typesense returns only once. A scoped key cannot narrow the collections of its parent key, so limit the parent key to the model's collection, as here: a parent key over every collection gives a token over every collection, filtered by fields that the other collections may not have.
  - Algolia: in the dashboard, under API Keys, create a key with the `search` ACL and the model's index, or use the search-only API key. The token is limited to the model's index with `restrictIndices`.
- `scout.prefix` is part of the index name, and the token restricts to the prefixed name. `SearchToken::index()` returns it, for the front end to query.
- Meilisearch and Typesense sign with their official client, `meilisearch/meilisearch-php` or `typesense/typesense-php`, which Scout already needs for those engines; without it `MissingSearchClientException` names the package. Algolia keys are signed by the package, with the documented HMAC-SHA256 of the restrictions, so the Algolia client is not needed.
- The `database`, `collection` and `null` engines, Turbopuffer and the engines passed to `Access::trustSearchEngine()` have no browser token: `UnsupportedSearchTokenEngineException`. A model that does not use `ControlledSearchable` throws `MissingControlledSearchableException`, a boundary class without `searchKeys()` `UnsearchableBoundaryException`.
- `SearchToken::filter()` returns the rendered filter, or `null` without one, for an application that proxies the search on its server. `toArray()` and the JSON form hold the token, the index and the expiry only.
- The engine applies the token's filter with any filter the browser sends, joined with AND. The browser can narrow its search, never widen it, and the index's lag applies as on the server.

#### Testing the search

`assertSearchAgrees()`, in the `InteractsWithAccess` trait with `assertLevelsNest()` and `assertControlAgrees()`, checks for each reader that the rows the keys find, computed from the same metadata without any engine, are the rows of the controlled query:

```php
public function test_its_search_says_what_its_sql_says(): void
{
    $this->assertSearchAgrees(PostControl::class, readers: [$this->admin, $this->clientManager, $this->author]);
}
```

It proves the agreement on the rows of the test only: seed the rows each perimeter reaches.

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

A model without the attribute falls back to the discovery: the control found in `app/Access/Controls`, or in a directory added with `Access::discoverControlsIn()`, whose `$model` is that model. A control outside the discovered directories is only found through the attribute: in 0.x it was registered when a policy asked for it, so queries could run without it.

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

**No list of the controls.** There is no runtime list of the controls. At runtime the package only knows the discovered controls: `#[ControlledBy]` is resolved model by model, when a model is asked for, and cannot be listed without scanning the model directories. A list would miss those models, a guard built on it would skip them silently, and it would contradict `Access::hasControl()`. A package that fails closed cannot allow that.

- For a guard, call `Access::hasControl($model)` on the model at hand. It covers the discovery and `#[ControlledBy]`, and the registry keeps its answer for each model class.
- For an inventory, in CI or in a review, run `php artisan access:check --json` with `--models` covering every directory that holds a controlled model: `controls[].model` and `controls[].control` give each control and its model, and the `uncontrolled_model` problem names the scanned models that use `HasControl` without a control (see [Checking the controls](#checking-the-controls)).
- `ControlDiscovery`, `ControlMap` and `ControlRegistry` are internal, whatever the visibility of their methods, and may change in any release: do not build a list on them, and reach the registry through the methods of the `Access` facade only.

`Access::$controlDiscoveryPaths` is replaced by `Access::discoverControlsIn()`, called from the `boot()` method of a service provider:

```php
// Before
Access::$controlDiscoveryPaths = [app_path('Domain/*/Controls')];

// After
Access::discoverControlsIn([app_path('Domain/*/Controls')]);
```

`Access::discoverControlsIn()` **adds** directories to `app/Access/Controls`, which is always scanned. Call it from the provider of each module; the same directory twice is scanned once, and two controls for one model throw `DuplicateControlException` whatever the order. In 0.x, `$controlDiscoveryPaths` replaced the default directory.

```php
// app/Providers/BillingServiceProvider.php
Access::discoverControlsIn([base_path('modules/Billing/src/Access/Controls')]);

// vendor/acme/crm/src/CrmServiceProvider.php
Access::discoverControlsIn([__DIR__.'/Access/Controls']);
```

The paths accept glob patterns (`app_path('Domain/*/Controls')`), and a missing directory finds nothing. Pass absolute paths (`app_path()`, `base_path()`, `__DIR__`): a relative one depends on the working directory, which differs between artisan and a web request. A path is compared as written, without its trailing separator. There is no way to remove a directory, including `app/Access/Controls`: `discoverControlsIn([])` adds nothing. A call made after a control was resolved is honoured: the next lookup discovers again, and the controls already built stay valid. `php artisan access:cache` runs once every provider has booted, so it caches the controls of every directory; `access:check` reports a cache written before a directory was added (`stale_control_cache`).

`addControl()`, `addControls()` and `discoverControls()` are removed: declare the control on its model with `#[ControlledBy]`. `Access::flush()` is removed with every other static state: each container, so each test, gets a new registry. Remove the `Access::flush()` calls from your tests. `Access::cache()`, `Access::cachePath()`, `Access::discoverControlClasses()` and `discoverControlsWithin()` are removed: run `php artisan access:cache` and `php artisan access:clear`.

`Control::new()` and `Access::control(PostControl::class)` return the control instance of the registry, resolved through the container: one instance per control class and per container. A control can take constructor dependencies, and a test can swap one with `$this->app->instance(PostControl::class, $control)`.

`access:cache` now writes the control of each model instead of a list of controls. Run `php artisan access:cache` again after upgrading, or `php artisan optimize` in your deployment. A cached control class that was deleted since is skipped instead of failing.

`MissingControlException` is now final.

### Policies

#### Automatic policies

An empty policy that only names the control is no longer needed. Call `Access::registerPolicies()` in the `boot()` method of a service provider, and delete the empty policy classes:

```php
use Lomkit\Access\Access;

public function boot(): void
{
    Access::registerPolicies();
}
```

Every controlled model that has no policy at all then gets a `ControlledPolicy` for its control. A model keeps its own policy when it has one: registered with `Gate::policy()`, before or after the call, declared with `#[UsePolicy]` on the model or on a parent class, registered for a parent class, or found by Laravel's naming convention. The policies are resolved when the gate asks for them, through `Gate::guessPolicyNamesUsing()`, so the call does not discover the controls at boot. If the application sets its own `Gate::guessPolicyNamesUsing()` callback, set it before `Access::registerPolicies()`: the package keeps the names it guesses, and a callback set after replaces the package's.

#### The control of a policy

`$control` is now optional on a `ControlledPolicy`. Without it, the policy decides a row with the control of the row's model. A policy without `$control` cannot decide `viewAny`, `create` without a draft, or a business ability without a row: it throws `MissingPolicyControlException`. Keep `$control` on a policy that decides those, or let `Access::registerPolicies()` create it.

A policy whose `$control` is not the control registered for the model it decides on throws `PolicyControlMismatchException`, which names both controls; so does a draft of another model. In 0.x the decision and the controlled queries could use two different controls. Name the model's control in the policy:

```php
#[ControlledBy(PostControl::class)]
class Post extends Model {}

class PostPolicy extends ControlledPolicy
{
    protected string $control = PostControl::class;
}
```

#### Responses with a code

The methods of `ControlledPolicy` return an `Illuminate\Auth\Access\Response` instead of a boolean. `can()`, `@can`, `authorize()` and `Gate::inspect()` work as before; a refusal now carries a code and a translated message:

| Code | Refusal | Status |
| --- | --- | --- |
| `unsaved` | The model is not saved, for any ability but `create` with a draft | 403 |
| `no_perimeter` | No perimeter allows the reader for this ability | 403 |
| `outside_boundary` | The row is outside the boundary for this ability, but the reader may still view it, such as an archived row | 403 |
| `not_found` | The row is outside the boundary for this ability and for `view`, such as a row of another tenant | 404 |
| `outside_rows` | No perimeter that allows the reader contains the row | 403 |

The checks run in this order. A row outside the boundary answers `Response::denyAsNotFound()` only when the boundary also refuses `view` to that reader, so a row of another tenant is never revealed; a row the reader can read but not change, such as an archived row, answers a 403. With a boundary that has no in-memory form, telling them apart costs one more `exists` query, on refusals only.

Overridden policy methods declare `Response` as their return type. Replace `bool` and `bool|Response`:

```php
// Before
public function update(Model $user, Model $model): bool
{
    return $model->locked_at === null && parent::update($user, $model);
}

// After
public function update(Model $user, Model $model): Response
{
    if ($model->locked_at !== null) {
        return Response::deny('This post is locked.');
    }

    return parent::update($user, $model);
}
```

The messages live in the package's `lang` files, in English and French. Publish them to translate or change them, then edit `lang/vendor/access-control/{locale}/refusals.php`:

```bash
php artisan vendor:publish --tag=access-control-lang
```

#### Drafts

`ControlledPolicy::create()` takes the model about to be created, the draft, as an optional second parameter: `create(Model $user, ?Model $draft = null)`. With a draft, the policy checks that it lands in one of the reader's perimeters, inside the boundary. Without a draft, `create` still means "allowed to create somewhere", and only the `allowed` callbacks run:

```php
// Before: client_id was never checked
$this->authorize('create', Post::class);
$post = Post::create($request->validated());

// After: the draft must land in one of the user's perimeters
$draft = new Post($request->validated());
$this->authorize('create', [Post::class, $draft]);
$draft->save();
```

A draft is not in the database, so only the in-memory checks judge it: the `should` callback or the `includes()` hook of a perimeter, an unrestricted perimeter, and the in-memory form of the boundary (`Boundary::where()`, or the `includes()` hook of a boundary class). A perimeter without an in-memory form cannot grant a draft, and a boundary without one refuses it. `Perimeter::checksInMemory()` and `Boundary::checksInMemory()` tell which ones can, and `php artisan access:check` notes a control that no perimeter lets grant a draft (`ungrantable_drafts`) and a boundary that refuses every draft (`boundary_refusing_drafts`). Add a `should` callback to the perimeters through which the readers create rows.

A policy whose `create()` ignores the draft means "allowed to create anywhere" again: a reader who may only create their own posts could create one for another author or another client, as long as the validation lets `author_id` or `client_id` through. Keep the draft in the decision.

A policy that overrides `create(Model $user)` must accept the draft, or PHP throws a fatal error:

```php
// Before
public function create(Model $user): bool|Response

// After
public function create(Model $user, ?Model $draft = null): Response
```

#### Creating through laravel-rest-api

laravel-rest-api always passes the draft: it checks `create` on the class, then on the draft of each row it creates. Its applications therefore judge every creation on its draft once they upgrade, and a creation that no perimeter can judge is refused with `outside_rows`, the code of a draft outside the perimeters, so the refusal reads like a missing permission.

- The perimeters that can grant a draft are the ones with a `should()` callback or an `includes()` hook, an unrestricted perimeter, and a `ThroughParent` that delegates `create` (`->delegates(['view', 'create' => 'update'])`), which loads the parent through the foreign key of the draft, one query per created row, then asks the control of the parent to decide it.
- A perimeter checked in SQL never grants a draft. Give it a `should()` callback that reads the foreign keys of the draft, such as `fn (User $user, Post $post): bool => $post->author_id === $user->id`. The `should()` callback then decides the saved rows too, in memory: it must say exactly what `query()` says; check it with `assertControlAgrees()`. A rule two relations away may load the relation on the draft, one query per created row, the same count in tests and in production.
- A boundary without an in-memory form refuses every creation: declare it with `Boundary::where()`, or override `includes()` on the boundary class.

`php artisan access:check` lists these controls and boundaries in its notes.

#### Unsaved models

`view`, `update`, `delete`, `restore`, `forceDelete` and every business ability refuse a model that is not saved, with the `unsaved` code, before any other check. In 0.x they granted it. `Control::applies()` follows the same rule: decide `viewAny` and `create` without a row, or with a draft:

```php
// Before
$control->applies($user, Ability::view(), new Post);
$control->applies($user, Ability::create(), new Post);

// After
$control->applies($user, Ability::view());
$control->applies($user, Ability::create());
$control->applies($user, Ability::create(), Draft::of($post));
```

`Control::applies(Model $reader, Ability $ability, Model|Draft|null $subject = null): bool` takes a saved row, a `Lomkit\Access\Decisions\Draft` or nothing. `Control::decide()`, with the same parameters, returns the `Response` the policy returns.

`Draft::of()` is not reserved to `create`: `Control::decide()`, `Control::applies()` and `ControlledPolicy::decideByControl()` accept it for any ability. A business ability on a row about to be created, such as `record` on a visit built in memory, takes `Draft::of($visit)`: it is judged where the row lands, in memory, like a `create` draft (`decideByControl(__FUNCTION__, $user, Draft::of($visit))`, `can('record', [Visit::class, Draft::of($visit)])`). Without a row, it means "somewhere" and only `allowed` runs. Passing the unsaved model itself is refused (`unsaved`): the package never guesses which of the two you meant.

The caller says which one it means, and the policy passes it on as it is:

```php
public function record(User $user, Visit|Draft|null $subject = null): Response
{
    return $this->decideByControl(__FUNCTION__, $user, $subject);
}

$this->authorize('record', [Visit::class, Draft::of($visit)]); // a visit about to be created
$this->authorize('record', $visit);                            // a saved visit, judged on its stored values
$this->authorize('record', Visit::class);                      // somewhere
```

Never wrap the argument in `Draft::of()` inside the policy: a saved visit changed with `fill()` would then be judged on its new values.

A `ThroughParent` judges such a draft only when `delegates()` lists that ability.

#### Decisions on the stored row

The policy decides a row on the values it has in the database. After `fill()`, the `should` callbacks and the boundary receive a copy built from the original attributes (`getRawOriginal()`), without the loaded relations; a row without changes is passed as it is. In 0.x, `$post->fill(['author_id' => $me->id])` before `authorize('update', $post)` granted the update in memory. Authorize the new values through a draft, not through the modified row.

#### Row checks in SQL

A perimeter without a `should` callback, and whose class does not override `includes()`, now decides a saved row in SQL instead of throwing `MissingPerimeterCallbackException`. The check is derived from its `query()`: one query on the model's table, constrained to the row's key and to the OR of the `query()` scopes of every reached perimeter that has no in-memory form. The mode never depends on runtime state, such as loaded relations, so a decision costs the same number of queries in tests and in production:

| The reader reaches | Queries for one row |
| --- | --- |
| No perimeter | None: `no_perimeter` |
| An unrestricted perimeter, with no boundary or a boundary with an in-memory form | None |
| A perimeter with an in-memory form that contains the row | None, or one on the boundary when it has no in-memory form |
| Only perimeters without an in-memory form | One, with the boundary in it when it has no in-memory form |

The in-memory form of the boundary is checked first, then the perimeters that have one (`should`, `includes()`, `unrestricted()`); the SQL runs only when none of them grants. The codes and their order do not change. A query callback that adds no constraint or a join throws `EmptyQueryCallbackException` or `JoinInPerimeterException`, as for the controlled queries. A draft is still judged only in memory: a perimeter checked in SQL never grants it.

Remove the `should` callbacks that only repeated their `query()`:

```php
// Before
OwnPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} own posts"))
    ->should(fn (User $user, Post $post) => $post->author_id === $user->id)
    ->query(fn (Builder $query, User $user) => $query->where('author_id', $user->id)),

// After: the row is checked through the query
OwnPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} own posts"))
    ->query(fn (Builder $query, User $user) => $query->where('author_id', $user->id)),
```

Keep a `should` callback on the perimeters through which readers create rows, since a draft needs it, and where checking in memory saves a query. `Perimeter::admits()` follows the same rule: in memory through the `should` callback or the `includes()` hook, otherwise one `exists` on the stored row through `query()`, and never for an unsaved row.

#### The decision context

`Access::decisionContextUsing()` declares, once and for the whole application, the values outside the reader that change a decision, such as the current brand or tenant:

```php
use Lomkit\Access\Access;

public function boot(): void
{
    Access::decisionContextUsing(fn (): array => ['brand' => Brand::current()?->id]);
}
```

The resolver returns an array of strings, ints, booleans or nulls keyed by name; anything else, such as a model or a float, throws `InvalidDecisionContextException`. It runs each time the package builds an `AccessContext`, and the hooks of a perimeter class and the `reader:` closures of search keys read it with `$context->decisionContext()`, sorted by key. In a `reader:` closure, the ability of the context is always `view`. Without a resolver, the context is `[]`. The package files the decisions it keeps for a request under this context, so a decision made for one brand is never reused for another.

#### Several brands in one list

A decision runs under one decision context: the one the resolver returns when the package builds it. Switching the context between two decisions is supported, since each decision reads the resolver again. Combining in one query branches built under different contexts is not: `controlledFor()` called once per brand, with the resolver switched in between and the calls joined by `orWhere()`, only lists the rows. Everything else runs under the context in force when it runs, not under the brand of each row: `withAbilities()`, the policy, the kept decisions, `Access::preload()` and the row checks of laravel-rest-api, whose kept decisions are filed under the same context. A post of brand B listed through the rights of brand B would be decided for `update` with the rights of brand A, granted or refused, and that decision kept under brand A, in laravel-rest-api's authorization cache too, and a branch that forgets its brand condition would list the posts of another brand with the rights of the first.

When one list spans several brands, the brand is a value of the row, not of the request. Write perimeters that read the brand of the row and filter by the brands where the reader holds the right; `brandsWhere()` stands for a method of your application that returns them:

```php
protected function perimeters(): array
{
    return [
        BrandPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->brandsWhere("{$ability} brand posts") !== [])
            ->should(fn (User $user, Post $post, Ability $ability): bool => in_array($post->brand_id, $user->brandsWhere("{$ability} brand posts"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->whereIn('brand_id', $user->brandsWhere("{$ability} brand posts"));
            }),
        OwnPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->brandsWhere("{$ability} own posts") !== [])
            ->should(fn (User $user, Post $post, Ability $ability): bool => $post->author_id === $user->id && in_array($post->brand_id, $user->brandsWhere("{$ability} own posts"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->whereBelongsTo($user, 'author')->whereIn('brand_id', $user->brandsWhere("{$ability} own posts"));
            }),
    ];
}
```

`Post::controlled()` then lists the posts of every brand of the reader in one query, and the policy, `withAbilities()` and `Access::preload()` decide each row from its own brand, whatever the current one. Keep these perimeters out of `Levels::of()`: a group applies only the highest level the reader reaches, in any brand, so a reader who sees every post of brand A and only their own posts of brand B would lose their posts of brand B. Loose perimeters add up, each one filtered by its own brands. Check the result with `assertControlAgrees()` on posts of several brands.

Keep the brand out of the boundary: `Boundary::where()` holds one value per reader, so a boundary that reads the current brand cuts the list to that brand. Keep in the boundary what is the same for every row the reader may reach, such as the tenant. On a searchable model, give each of these perimeters search keys built from the brand of the row and the brands of the reader.

#### Kept decisions

The package keeps two things for the length of a request, a queued job or a console command:

- the perimeters a reader reaches, under the reader (its morph class, connection and key), the decision context, the control instance and the ability: what every `allowed()` callback and `allows()` hook answered, what the boundary's `admits()` answered, the level reached in each group, and, for a `ThroughParent`, the perimeters the reader reaches on the parent, kept the same way under the parent's control;
- the SQL check of each row, under the reader, the decision context, the model, the row key and the ability.

A reader without a key, or not saved, is never kept: its perimeters are evaluated again on every decision. For the others, an `allowed()` that reads the database runs once per reader, ability and decision context in a request, not once per row: a page of 100 posts decided through `withAbilities()` costs the page query plus the queries of each `allowed()` once, and so does a page of 100 comments whose `ThroughParent` reaches the posts. The policy, `controlled()`, `controlledFor()`, the batches, the search filter and `applicablePerimeters()` read the same kept perimeters, so they always agree within a request. The in-memory checks (`should`, `includes()` and the in-memory form of the boundary) run on every decision, on the row as stored.

Everything kept is forgotten at once, the perimeters and the SQL checks: after any SQL statement that is not a read, on any connection, when a transaction rolls back, once Laravel has handled a request (so two requests of one test share nothing), at the end of each job, and when the application calls `Access::forgetDecisions()`. Until then, what `allowed()` answered stands. Each kind is capped at 10,000 entries, and the oldest is dropped first.

A read is a single statement that starts with `select` or `show`, after spaces and opening parentheses: a `select` followed by `;` and another statement forgets. Every other statement that runs through a Laravel connection forgets: Eloquent saves and deletes, with or without events (`saveQuietly()`, `withoutEvents()`, and `Event::fake()` in tests), mass updates and deletes, `attach()`, `detach()` and `sync()`, `DB::table()`, `DB::statement()` and `DB::unprepared()`. So do the statements that might write without starting with a write: `with` (a common table expression can write, in PostgreSQL and in other databases), `explain` (`explain analyze` runs the statement), `pragma`, `call`, `set`, a statement that starts with a comment, and the schema statements. Eloquent's `saved` and `deleted` events still forget too.

The writes Laravel makes to its own tables do not forget. The package reads them from the configuration each time it classifies a write:

- the `table` of each cache store whose driver is `database`, on its `connection`, and its `lock_table` (`cache_locks` when unset, as Laravel does) on its `lock_connection`;
- `session.table`, on `session.connection`, when `session.driver` is `database`;
- the `table` of each queue connection whose driver is `database`, on its `connection`;
- `queue.failed.table`, on `queue.failed.database`, when its driver is `database` or `database-uuids`;
- `queue.batching.table` (`job_batches` when unset), on `queue.batching.database`, unless batching uses DynamoDB.

A connection left `null` is the default connection. A write is one of them only when it is a single `insert` (`insert ignore`, `insert or ignore`, `replace`, an upsert), an `update ... set` that reads no other table, a `delete from` (with or without `where`) or a SQL Server `merge` on one of these tables, quoted the way the query builder quotes it, table prefix and schema included, on the connection the configuration names, and without a second statement. An application table that has the name and the connection of one of these tables, such as a `Job` model on the `jobs` table that `config/queue.php` declares, never forgets: give it another name or another connection. A cache entry, a lock, the rate limiter, a session or a queued job therefore costs no query in production, where those drivers write to the database, as in tests, where they are `array` and `sync`. The list cannot be extended: write a log of each row after the response (`defer()`, `dispatch()->afterResponse()`), not while the page renders.

Call `Access::forgetDecisions()` after a write that no Laravel connection runs and that changes a decision or a permission that `allowed()` reads: a write through another PDO handle or a database client outside Laravel, a write by another process (a queue worker, a scheduled command, another application) that the current request or job must see, or a write hidden in a statement read as a `select`, such as a function that writes, or `select ... into` in PostgreSQL and SQL Server. A statement that fails after writing part of its rows does not forget either. A permission changed without any write, such as a `Gate::define()` in a test, needs it too. An attribute of the reader changed in memory, without saving it, does not change what was kept either.

Everything `allowed()` reads besides the reader must be in the decision context: the current team of spatie/laravel-permission (`setPermissionsTeamId()`), a tenant or a guard set during the request. A value that changes without being in it reuses the answer given for the previous value until the next flush. A long-running console command keeps the kept decisions for its whole run, and never sees a permission revoked by another process: call `Access::forgetDecisions()` at the start of each unit of work, such as each tenant or each batch of users. A job run with the `sync` connection or `dispatchSync()` shares the kept decisions of the request that runs it, while a queue worker starts each job empty.

#### Batched decisions

A page that checks `@can('update', $post)` on each row runs one query per row and ability for the perimeters checked in SQL. Decide the whole page in the query that loads it with `withAbilities()`, after `controlled()` or `controlledFor()`:

```php
$posts = Post::controlled()
    ->withAbilities(['view', 'update', 'delete'])
    ->paginate();
```

For each ability, the query selects one `CASE` that returns the verdict code of the row. The checks run in this order: the boundary when it has no in-memory form, then the perimeters checked in SQL. The answers are kept for the request under the reader chosen by `controlledFor()`, or the signed-in user for `controlled()`, and under the current decision context. `can()`, `@can`, `authorize()`, `Gate::inspect()` and the policy methods do not change, and answer without SQL. The extra columns use reserved aliases, never `id`, so a join that overwrites `posts.id` cannot file an answer under the wrong row. They are removed from every model right after the query: they never show in `getAttributes()`, `toArray()` or the JSON. `withAbilities()` works with `get()`, `first()`, `paginate()`, `simplePaginate()`, `cursorPaginate()`, `chunk()`, `lazy()`, `cursor()` and the eager loads of a controlled relation:

```php
$users = User::with(['posts' => fn ($posts) => $posts->controlled()->withAbilities(['update'])])->get();
```

- The abilities are names or `Ability` objects; a name goes through `abilityFor()`, so `viewAny` is `view`, and a name the control cannot map throws `InvalidAbilityException`.
- `withAbilities()` without `controlled()` or `controlledFor()` before it throws `MissingControlledReaderException`.
- For a guest, `controlled()` loads no row, so there is nothing to decide.
- `controlled()` does not decide `view` by itself: Laravel only groups the scope's conditions, so an `orWhere()` added after it could bring rows from outside the perimeters. Write `withAbilities(['view'])` when the page checks it.
- Select the columns with `select()`, since the columns passed to `get()` are ignored once `withAbilities()` adds its own.

For rows that are already loaded, `Access::preload($reader, 'update', $rows)` runs one query per ability and per model, in chunks of 1000 keys, and keeps the answers the same way. The rows can belong to several controlled models; unsaved rows are skipped, since they are refused as `unsaved` without SQL. A reader without a key, or not saved, is never kept, so preloading for it runs no query.

```php
Access::preload($user, 'update', $posts);
Access::preload($user, Ability::delete(), $posts);
```

`$control->reachableKeys($reader, Ability::update(), $rows)` returns, in one query, the keys of the rows the reader may act on, in the order of `$rows`:

```php
$editable = Access::controlFor(Post::class)->reachableKeys($user, Ability::update(), $posts);
```

To paginate or count the rows a reader may act on, rather than decide the rows of a page, list them with `controlled('update')` (see [Named readers](#named-readers)); `reachableKeys()` stays the way to filter rows that are already loaded. The list keeps no decision: chain `withAbilities()` after it to decide the rows it loads.

```php
$editable = Post::controlledFor($user, 'update')
    ->withAbilities(['view', 'delete'])
    ->paginate();
```

The batches hold only what the SQL decides, under the perimeters kept for the reader (see [Kept decisions](#kept-decisions)). The in-memory checks still run on every decision, on the row as stored: the in-memory form of the boundary, and the perimeters with a `should` callback or an `includes()` hook. A perimeter checked in memory can grant a row that the batch answered `outside_rows`. A batched answer is therefore always the answer of the row-by-row decision. With a boundary that has no in-memory form, `withAbilities(['view', ...])` or a preload of `view` also gives the `not_found` answers of the other abilities without a query.

A batch is only worth its query while nothing flushes the kept decisions: a write for each row while the page renders, such as an access log, forgets them, and the next checks query again. Write such logs after the rendering (`defer()`, `dispatch()->afterResponse()`).

#### Business abilities

A policy declares a business ability, such as `approve` or `attach`, by calling the protected `decideByControl()` helper with the name of its method. The control maps the method to an ability through `abilityFor()`, and the helper returns the same `Response` as the standard methods:

```php
use Illuminate\Auth\Access\Response;
use Lomkit\Access\Decisions\Draft;

class PostPolicy extends ControlledPolicy
{
    protected string $control = PostControl::class;

    public function approve(User $user, Post $post): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, $post);
    }

    public function export(User $user): Response
    {
        return $this->decideByControl(__FUNCTION__, $user);
    }

    public function duplicate(User $user, Post $post, Post $copy): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, Draft::of($copy));
    }
}
```

Replace the business methods that called the control by hand:

```php
// Before
public function approve(User $user, Post $post): bool
{
    $control = Access::controlFor($post);

    return $control->applies($user, $control->abilityFor('approve'), $post);
}

// After
public function approve(User $user, Post $post): Response
{
    return $this->decideByControl(__FUNCTION__, $user, $post);
}
```

The Gate calls the policy method named after the ability. The public methods of `ControlledPolicy` are the seven standard abilities, and a policy inherits only two other methods, the protected `decideByControl()` and `declaredControl()`: these two names, and `decide-by-control` and `declared-control`, are the only abilities a policy cannot declare. Any other business ability, `decide` included, can be declared. Keep `declaredControl()` protected. A business ability on a row about to be created takes `Draft::of($row)`, like `duplicate` above, and is judged in memory like a `create` draft (see [Unsaved models](#unsaved-models)). The 0.x `getControl()` helper is removed: call `decideByControl()`, or override `declaredControl()` to pick the control.

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

The same rule applies to the ability that the `should` and `query` callbacks receive as their third parameter (see [Perimeter callbacks](#perimeter-callbacks)).

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
$control->applies($user, $control->abilityFor('viewAny'));
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

Rename each override, make it `protected`, and read the reader and the ability from the context. A rule is written once: either as a hook in the perimeter class, or as a callback in the control. Declaring the `allowed`, `should` or `query` callback of a hook that the class overrides now throws `OverriddenPerimeterHookException`, which names the perimeter and the hook: in 0.x, an override that did not call its parent silently ignored the callback. Move the logic of the callback into the hook, or remove the override and keep the callback. A parent hook that has no callback throws `MissingPerimeterCallbackException`, so an override no longer calls it.

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

A hook receives the reader as `Model`, the type of every reader: a perimeter class cannot narrow it to `User` in its signature, so it checks the reader with `instanceof`, as above. The perimeter controls the rows of one model, so `$row` and `$query` are rows of that model.

For PHPStan, the `scope()` hook of a perimeter class and of a boundary class receives a `Builder<covariant Model>`, the type that Laravel gives `Scope::apply()`: one class may serve several models. Laravel declares the model of `Builder` invariant, so PHPStan refuses to pass this builder to a helper typed `Builder<Post>`, `Builder<Model>` or `@template TModel of Model` with `Builder<TModel>`. Type the helpers that only add conditions `Builder<covariant Model>`, such as this method of an application class `PostRows`:

```php
/** @param  Builder<covariant Model>  $query */
public static function visible(Builder $query): void
{
    $query->whereNull('archived_at');
}
```

A helper that needs the local scopes of `Post` receives a builder narrowed by `PostRows::of()`, a check that throws when the builder reads another model; `RowsOfAnotherModelException` stands for an exception of your application:

```php
/**
 * @template TModel of Model
 *
 * @param  class-string<TModel>  $model
 * @param  Builder<covariant Model>  $query
 * @return Builder<TModel>
 */
public static function of(string $model, Builder $query): Builder
{
    if (! $query->getModel() instanceof $model) {
        throw RowsOfAnotherModelException::for($model, $query->getModel());
    }

    /** @var Builder<TModel> $query */
    return $query;
}

protected function scope(Builder $query, AccessContext $context): void
{
    PostRows::visible($query);
    PostRows::of(Post::class, $query)->published();
}
```

A `query()` callback declared in the control is not typed by the package: PHPStan reads the `Builder` of its parameter without a model, and accepts it in a helper typed `Builder<Post>`.

`unrestricted()` throws `UnrestrictedPerimeterCallbackException` on a perimeter class that overrides `includes()` or `scope()`: an unrestricted perimeter never runs them.

A perimeter class that must be narrowed for one use, such as the entities of the reader but only the invoices, keeps the variant as state of the class, which every hook reads. Declaring `query()` on a class that overrides `scope()` throws `OverriddenPerimeterHookException`, and a second `query()` throws `DuplicatePerimeterCallbackException`: neither narrows a class. The nature of the state picks the form:

- **Required state**, without which the perimeter means nothing, such as the kind of document or a read or write path: a constructor with `readonly` promoted properties, and a named static constructor for each variant. PHP refuses to build the perimeter without its state, and a hook that writes to it fails at once.
- **Optional narrowing** of a class that has a sensible default without it: `X::new()` and a typed *wither* that clones the perimeter and sets private state.

The constructor form:

```php
final class OwnedByMyEntities extends Perimeter
{
    private function __construct(private readonly string $kind) {}

    public static function ofKind(string $kind): self
    {
        return new self($kind);
    }

    protected function includes(Model $row, AccessContext $context): bool
    {
        return $row->kind === $this->kind && /* the entities of $context->reader() contain $row->entity_id */;
    }

    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('kind', $this->kind)->whereIn('entity_id', /* the entities of $context->reader() */);
    }
}

OwnedByMyEntities::ofKind('invoice')
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} entity invoices"))
    ->searchKeys(
        name: 'entity_invoices',
        row: fn (Document $document) => $document->kind === 'invoice' ? $document->entity_id : null,
        reader: fn (User $user) => $user->entities()->pluck('id')->all(),
    );
```

- The named constructor starts the chain, and the fluent methods keep the state of the constructor: `allowed()`, `should()`, `query()`, `searchKeys()`, `reindexWhen()` and `unrestricted()` clone the perimeter and never write to its properties.
- Never rebuild the perimeter after a fluent method: an instance method that returns `new self(...)` after `->allowed()` drops the callbacks, the search keys and `unrestricted()`. A `readonly` property is written once, and a clone already carries it, so a wither cannot assign it after `clone` (on PHP 8.3 and 8.4): give each variant its own named constructor instead. Give the required state no default: a constructor whose arguments are all optional is built by `X::new()` with its defaults, so a default that means "every kind" comes back silently.
- `X::new()` on an abstract class, or on a class whose constructor requires arguments or is not public, throws `UnbuildablePerimeterException`, which names the class and the cause (the required arguments, for instance): build it with its named constructor. `make:control --perimeters` leaves such a class out of the generated control and warns: add it to `perimeters()` by hand.
- `Perimeter` no longer declares `@phpstan-consistent-constructor`, so PHPStan accepts a constructor with required arguments. A class of the application that builds itself with `new static`, such as an override of `new()` or a factory on a base perimeter class, now gets *Unsafe usage of new static()*: add `/** @phpstan-consistent-constructor */` to that class.

The wither form, where no kind means every kind:

```php
final class OwnedByMyEntities extends Perimeter
{
    private ?string $kind = null;

    public function ofKind(string $kind): static
    {
        $perimeter = clone $this;
        $perimeter->kind = $kind;

        return $perimeter;
    }

    protected function includes(Model $row, AccessContext $context): bool
    {
        if ($this->kind !== null && $row->kind !== $this->kind) {
            return false;
        }

        return /* the entities of $context->reader() contain $row->entity_id */;
    }

    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->whereIn('entity_id', /* the entities of $context->reader() */);

        if ($this->kind !== null) {
            $query->where('kind', $this->kind);
        }
    }
}

OwnedByMyEntities::new()
    ->ofKind('invoice')
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} entity invoices"))
    ->searchKeys(
        name: 'entity_invoices',
        row: fn (Document $document) => $document->kind === 'invoice' ? $document->entity_id : null,
        reader: fn (User $user) => $user->entities()->pluck('id')->all(),
    );
```

In both forms:

- The state is private and pure: build it from scalars, enums or readonly objects that come from a constant or a value that does not change in the process, never from the request, the tenant or the signed-in user (see [Pure perimeter lists](#pure-perimeter-lists)).
- Every path reads it: `scope()` for the controlled queries and the row checks in SQL, `includes()` for the in-memory checks and the drafts, and the `row:` closure of the search keys, which returns no key for a row outside the variant. The policy, the controlled queries, the batches and the search then agree, which `assertControlAgrees()` and `assertSearchAgrees()` check.
- Two variants of one class in one control are two perimeters: with search keys, give them distinct key names, or the control throws `DuplicateSearchKeyNameException`.
- Do not wrap a perimeter in another one to narrow it: the package recognizes a `ThroughParent` and an unrestricted perimeter by their own class, which a wrapper hides.

`applyScoutQueryCallback()` has no hook in 1.0: a perimeter is searched through its search keys (see [Search](#search)). Remove the override and declare `searchKeys()` on the perimeter.

`Perimeter::admits(Model $reader, Ability $ability, Model $row): bool` tells whether the perimeter applies to the reader for the ability and contains the row, through its hooks or its callbacks. A perimeter without a `should` callback checks the stored row through its `query()`, in one query. Inside a control, `applicablePerimeters(Model $reader, Ability $ability)` returns the perimeters that apply to the reader for the ability: together, they tell which perimeters grant a row.

A perimeter has no query form of its own: a single perimeter, or a single level, evaluated in SQL would ignore the boundary, the other levels and `allowed()`, so it is not an access list, and paginating it leaks across tenants. To act on behalf of someone, or to explain why a row is listed, keep the rule in an object of the application and intersect it with the controlled list:

```php
Post::controlledFor($principal, 'update')->where(fn (Builder $query) => (new OwnPostsRule)->apply($query, $principal));
```

The boundary, the levels, `allowed()` and `ThroughParent` are all inside `controlledFor()` by construction, so the rule can only narrow the list, never widen it.

`applyAllowedCallback()`, `applyShouldCallback()` and `applyQueryCallback()` are internal: they take an `AccessContext` and are called by the control. Replace your calls with `admits()`, or with the policy and the controlled queries. `applyScoutQueryCallback()` is removed. `UntypedAbilityHookException` is removed, since `allows()` reads the ability from its context.

### Perimeter callbacks

The `should` and `query` callbacks receive the ability after their other arguments. It is optional: the closures of 0.x keep working.

```php
OwnPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
    ->should(fn (User $user, Post $post, Ability $ability) => $ability->is('view')
        ? $post->client_id === $user->client_id
        : $post->author_id === $user->id)
    ->query(fn (Builder $query, User $user) => $query->where('client_id', $user->client_id)),
```

As for `allowed`, the parameter must accept an `Ability`, or the declaration throws `AbilityParameterTypeException`. The controlled queries pass the ability they list: `view`, unless `controlled()` or `controlledFor()` names another one.

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

A perimeter cannot change once it is declared. `allowed()`, `should()`, `query()`, `searchKeys()` and `unrestricted()` return a new perimeter and leave the one they are called on unchanged, and return `static`. Chain the declarations, or keep the returned perimeter:

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

Each callback is declared once. A second `allowed()`, `should()`, `query()` or `searchKeys()` on the same perimeter throws `DuplicatePerimeterCallbackException`, which names the perimeter and the callback, where the second declaration used to replace the first silently: `->query(A)->query(B)` kept only `B`, which is usually wider than the author meant, and a second `searchKeys()` dropped the `reindexWhen()` rules declared after the first. Write one callback that checks both conditions, or give a perimeter class a named constructor or a wither (see [Perimeter classes](#perimeter-classes)). `->searchKeys(...)->reindexWhen(...)` keeps working.

The callbacks are private properties of `Perimeter`. A subclass that assigned `$this->allowedCallback`, `$this->shouldCallback`, `$this->queryCallback` or `$this->scoutQueryCallback` now writes a property of its own, which the package never reads: declare the callbacks with the fluent methods, or override the hooks.

`Control::getPerimeters()` is removed. The perimeters of a control are built once by `perimeters()` and kept by the control; nothing can read or change them from outside. Inside a control, use `applicablePerimeters($reader, $ability)`; elsewhere, use the policy, `admits()` or the controlled queries.

### Pure perimeter lists

A control builds its perimeters once and keeps them for the whole process: a request, but also a queue worker or an Octane worker that serves many requests. `perimeters()` must therefore return the same perimeters every time it runs. In debug mode and in tests, each time the registry hands out a control, it builds `perimeters()` again and compares the result with the perimeters it keeps: the same classes in the same order, the same callbacks declared from the same lines, and the same values captured by the callbacks. A difference throws `ImpurePerimetersException`, which names the control. The same goes for `boundary()`: the boundary is built once with the perimeters, and the check compares its class, its columns and the values its closure captures. The check does not run when debug mode is off outside tests.

Move what `perimeters()` read when it built the perimeters into the callbacks, which run with the reader:

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

The same goes for a perimeter list that depends on the user, the request, the tenant, the configuration of the moment or the clock.

The check compares the values the callbacks capture this way:

- scalars and arrays by value and type;
- Eloquent models by class and key, so a user captured at build time is caught as soon as another user signs in;
- enums by identity;
- dates (`DateTimeInterface`, Carbon included) by their instant, so a date read from the clock while `perimeters()` builds is caught;
- closures by the lines that declare them and, recursively, by the values they capture;
- objects of a `readonly` class, such as `Ability`, property by property with these same rules, at any depth: a closure they hold by its lines and its captures, a model by its key, a scalar by value and type, so `'1'` and `1` differ. An object that refers to itself is compared once. An object whose class is not declared `readonly` is any other object, even when all its properties are `readonly`;
- any other object by its class only: a service built in `perimeters()`, such as `$rules = new PostRules`, is pure and passes.

The properties that a perimeter class declares, the `readonly` properties its constructor promotes as well as the state a wither sets, are compared with the same rules, so `OwnedByMyEntities::ofKind(config('documents.kind'))` and `OwnedByMyEntities::new()->ofKind(config('documents.kind'))` are caught when the configuration changes, as the same value captured by a closure is. A hook that changes a property of its perimeter, such as a cache of the readers it allowed, is caught too: the perimeters are shared by every request of the process, so a hook must never write to them. No such cache is needed: the package keeps what `allowed()` and `allows()` answer for each reader, ability and decision context until the next flush (see [Kept decisions](#kept-decisions)). The properties of `Perimeter` and of `ThroughParent` are compared by the package on their own: the callbacks, the search keys, and the relation, inverse and delegation of a parent.

The check only sees what changes between two builds of the same process. It cannot see:

- the state inside a captured service or model: a service that reads the request or the tenant when it is built, or whose properties change later, compares equal as long as its class is the same; a model is compared by its key, not by its attributes; Inside a `readonly` object too, an object of another class, such as a collection, is compared by its class only;
- a value that is the same in both builds, such as the tenant of a test that never switches tenant, or a row read from the database that does not change;
- what the callbacks read when they run: that is not a purity problem, since the callbacks are where the reader, the request and the tenant belong.

Read request and tenant state in the callbacks, never in `perimeters()` or in the constructor of a service it captures.

### Checking the controls

`php artisan access:check` builds every discovered control, declares and verifies its perimeters whatever the environment, and reports what would throw, open or hide rows, or leave search documents stale, before production meets it. It lists each control with its model, its levels, the mode of each perimeter (`memory` with a `should()` callback, `sql` without), its boundary, its `ThroughParent` relations and their delegated abilities, the search engine of a `ControlledSearchable` model, and its policy: the policy class and its form, `controlled` (a policy that extends `ControlledPolicy`), `automatic` (registered by `Access::registerPolicies()`), `own` (a policy that does not extend `ControlledPolicy`), `none`, or `unresolvable` when the Gate throws while it resolves the policy (reported as `unresolvable_policy`). It exits with `1` when it finds a problem, `0` otherwise; notes never change the exit code. Run it in CI with the Scout driver of production (`SCOUT_DRIVER=meilisearch php artisan access:check`): the engine checks use the driver of the environment the command runs in.

```
  controls  2 built from app/Access/Controls
  cache ................................................................. not cached
  models ................................................ 2 scanned in app/Models
  scout driver ........................ meilisearch, from the config of this environment
  pivots ............ 1 BelongsToMany relation with a declared return type scanned
  App\Access\Controls\PostControl  App\Models\Post
  #1.1 GlobalPerimeter ........................................ memory, unrestricted
  #1.2 ClientPerimeter .................................... sql, search keys client
  #2 SharedPerimeter .................................................... memory
  boundary ...................... Lomkit\Access\Boundaries\ColumnBoundary, memory, searchable
  search ................ ControlledSearchable on Laravel\Scout\Engines\MeilisearchEngine
  policy ................................................ App\Policies\PostPolicy, controlled
  App\Access\Controls\CommentControl  App\Models\Comment
  #1 ThroughParent [post] ....... sql, update from the parent's update, view from the parent's view
  boundary ...................................................................... none
  search ................................................................ not searchable
  policy ............................. Lomkit\Access\Policies\AutomaticPolicy, automatic
  problems .................................................................. 1 FAILED
  ⇂ The [App\Access\Perimeters\SharedPerimeter] perimeter of the [App\Access\Controls\PostControl] control declares no search keys, so a reader that only this perimeter reaches finds none of its [App\Models\Post] rows in a controlled text search. Declare searchKeys() on the perimeter, or unrestricted() if it reaches every row
  notes ........................................................................ none

   ERROR  1 problem found.
```

Problems:

| Kind | Reported when |
|---|---|
| `control_failure` | Building a control, its declaration or the verification of its perimeters throws: impure perimeters, empty or nested levels, a missing callback, a `ThroughParent` on a missing relation, a cycle, a parent without control, an invalid delegation, a relation method that runs a query (`RelationInspectionQueryException`), a duplicate search key name, a control without model. The message quotes the exception. A discovered control that throws while the cached watched models are compared is reported too, even when `--only` leaves it out. |
| `duplicate_control` | Two discovered controls, or a discovered control and `#[ControlledBy]`, declare the same model. |
| `uncontrolled_model` | A model of the scanned directories uses `HasControl` and no control is discovered or declared for it. |
| `invalid_controlled_by` | `#[ControlledBy]` names a class that is not a control, or the control of another model. |
| `stale_control_cache` | `bootstrap/cache/access-controls.php` maps the models to other controls than the discovered ones. |
| `stale_watch_cache` | The controls are cached, and `bootstrap/cache/access-reindex.php` is missing, unreadable, or lists other watched models than the `reindexWhen()` rules of the discovered controls: the documents whose rule it misses keep stale access keys. |
| `unresolvable_policy` | The Gate throws while it resolves the policy of a controlled model. |
| `policy_without_control` | The policy of a controlled model extends `ControlledPolicy` without `$control`: `viewAny()` and `create()` without a draft throw `MissingPolicyControlException`. |
| `policy_for_another_control` | That policy names another control than the one of its model (`PolicyControlMismatchException`). |
| `plain_searchable` | A controlled model uses Scout's `Searchable` instead of `ControlledSearchable`. |
| `unsupported_search_engine` | The engine of a `ControlledSearchable` model cannot filter a controlled search: not trusted (`Access::trustSearchEngine()`), the `collection` engine, or an engine that throws when it is built. |
| `unsearchable_boundary` | On an engine that stores documents, the boundary has no search form. |
| `perimeter_without_search_keys` | On an engine that stores documents, a perimeter that is neither unrestricted nor a `ThroughParent` declares no `searchKeys()`: the readers it reaches find none of its rows. |
| `search_freshness` | What would leave documents stale: Scout's `after_commit` off, `makeSearchableUsing()` overridden without `withAccessKeys()`, a relation the search keys read that nothing reindexes. |
| `pivot_without_using` | A `BelongsToMany` relation reaches the table of a pivot that uses `ReindexesSearch` without `->using()` it: attaching or detaching through it fires no event. |
| `unreadable_relation` | A relation that the command reads throws when it is read without a database connection: a scanned `BelongsToMany` relation, or a relation that the search keys load. It is reported instead of skipped, since the command cannot check it. |

Notes:

| Kind | Reported when |
|---|---|
| `inherited_memory_perimeters` | Two discovered controls or more inherit `perimeters()` from a base class whose perimeters have a `should()` callback, so they all check them in memory. |
| `policy_outside_control` | The policy of a controlled model does not extend `ControlledPolicy`, so the package cannot check that it names the model's control (`policy_for_another_control`) nor refuse a mismatch at runtime (`PolicyControlMismatchException`). A base policy that calls `Control::decide()` can extend `ControlledPolicy` instead and override its methods; prove the agreement with `assertControlAgrees()`. |
| `model_without_policy` | No policy is registered for a controlled model (`Access::registerPolicies()` registers one). |
| `ungrantable_drafts` | No perimeter of a control can grant a draft: none has a `should()` callback or an `includes()` hook, none is unrestricted, and no `ThroughParent` delegates `create`. Every creation judged on its draft is refused with `outside_rows` (see [Drafts](#drafts)). |
| `boundary_refusing_drafts` | The boundary of a control has no in-memory form, so it refuses every draft with `outside_boundary`. |

The human output lists the notes kind by kind. When a kind has more than 5 notes, it prints them on one line: their count, the number of distinct subjects when several notes share one (`7 notes on 1 policy`, a policy guessed for 7 models), up to three base classes of the application that at least two subjects extend, at any depth (classes of `vendor` and of the package are left out), and the first three subjects:

```
  notes ........................................................................ 325
  ⇂ policy_outside_control: 325 policies, 325 extending App\Access\BasePolicy (first 3: App\Policies\InvoicePolicy, App\Policies\OrderPolicy, App\Policies\VisitPolicy); --json lists them all
```

Problems are never grouped, and `--json` always lists every note. A problem that two checks find, with the same kind, subject and message, is listed once. No attribute, interface or option silences a note: fix what it reports, or read it in the JSON.

Options:

- `--json` prints the same report as one JSON document: `passed`, `discovery` (`paths`, `cached`, `models`, `scanned`, the number of models found there, `scout_driver`), `controls` (each with `control`, `model`, `perimeters` as `position`, `perimeter`, `mode`, `unrestricted`, `search_keys`, `parent` with `relation`, `delegates` and `allowed`, then `boundary` with `boundary`, `mode`, `searchable`, `search` with `searchable` and `engine`, and `policy` with `policy`, the class or `null`, and `form`), `pivots` (`relations`), and `problems` and `notes`, each a list of `kind`, `subject` and `message`.
- `--only=App\Models\Post` (repeatable, a model or a control class) checks only these controls: their declaration, policy and search. The discovery, the cache and the pivots are always checked. A name that matches no control exits with `2`.
- `--models=app/Domain/Models` (repeatable) sets the directories scanned for models that use `HasControl` or `#[ControlledBy]`, `app/Models` by default. It accepts globs: `--models='modules/*/src/Models'`, resolved from the working directory. The models of the discovered controls are always checked, wherever they live; the header prints how many models the scan found and where (`0 scanned in app/Models`). The scan adds what discovery cannot see:
  - `uncontrolled_model`: a scanned model that uses `HasControl` without a control;
  - the control that a scanned model names with `#[ControlledBy]` when no discovered control covers that model: it is built and checked like the others (`invalid_controlled_by`, `duplicate_control`, `control_failure`, policy, drafts, search);
  - `invalid_controlled_by` for a scanned model whose `#[ControlledBy]` names the control of another model, even a discovered one;
  - the `BelongsToMany` relations of the scanned models for `pivot_without_using`.

The command is read-only: it never writes or rewrites the discovery cache (a stale one is reported, not fixed), runs no query and dispatches no job. It builds the controls, resolves the policies and the Scout engines, and reads the relations without a database connection, so a model on a connection configured later is checked like the others. The pivot check only sees the public relation methods that declare a `BelongsToMany` return type (or a subclass, such as `MorphToMany`): a relation method without a return type is not scanned. It cannot tell whether a `should()` callback reads a relation: that is up to the code of the callback.

### The about command

`php artisan about` prints an "Access Control" section (`php artisan about --only=access_control`, also with `--json`, under the `access_control` key), registered when the application runs in the console:

```
  Access Control ................................................................
  Automatic policies ................................................. registered
  Cache .............................................................. not cached
  Controls ................................................................... 12
  Discovery paths .................................................. app/Access/Controls
  Scout ...................................................... installed, meilisearch driver
  Version ........................................................................ 1.0.0
  laravel-rest-api ............................... installed, AccessControlExtension enabled
```

- `Version`: the installed version of the package.
- `Controls`: the number of controls, read from the `access:cache` file when it exists, else from the discovery paths. No control is built. When discovery fails, the row reads `discovery failed:` and the first line of the error, and the other rows still print.
- `Discovery paths`: every directory the controls are discovered in, relative to the base path: `app/Access/Controls` first, then the ones added with `Access::discoverControlsIn()`.
- `Cache`: `cached` when `php artisan access:cache` wrote its file, `not cached` otherwise.
- `Automatic policies`: `registered` after `Access::registerPolicies()`, `not registered` otherwise, or when the application replaced the policy guesser afterwards.
- `Scout`: `not installed`, or the driver of the environment.
- `laravel-rest-api`: `not installed`, `AccessControlExtension enabled`, or `AccessControlExtension listed in rest.disabled_extensions`.

An empty value prints `none`, and a row that cannot be read prints `unavailable:` with the first line of the error: the section never makes `about` fail.

### laravel-rest-api

With laravel-rest-api 3.6 or later installed, the package registers `Lomkit\Access\Integrations\RestApi\AccessControlExtension` from the `register()` method of its service provider. The extension is named `access_control`: rest-api prefixes its cache keys with this name. It applies to every rest-api resource whose model has a control, and leaves the others alone. `composer.json` conflicts with laravel-rest-api before 3.6.0, which has no extensions.

- Rows: the rows of the resource are the rows that `controlledFor($user)` finds for `view`, wherever rest-api applies a perimeter: searches, includes, relation filters, sorts and aggregates, metrics and the keys of their cache, the key lookups of `mutate`, `destroy`, `restore`, `forceDestroy` and actions, the rows of queued actions and the rows read again after a write. A guest finds no row, as with `controlled()`. A user that is not an Eloquent model throws `InvalidRestReaderException`, which names its class.
- Text search: a resource searched through `ScoutEngine` declares `withoutPerimeter()`, and the extension restricts the search. rest-api builds its own Scout query, so `controlled()` never runs there: the extension is the access filter of these searches.
  - On the Scout `database` and `collection` drivers, the rows are restricted in the SQL of the search, with an exact total.
  - On another engine, the extension declares `_access` and `_boundary` as lists of strings for models that use `ControlledSearchable`, and keeps the documents that share a key with the reader in each list, as `controlled()` does (on Turbopuffer, rest-api sends each list as a native `ContainsAny` filter); a guest or a reader that no perimeter reaches gets an empty page without calling the engine. The engine is the only barrier: rest-api loads the rows of the page by key without the SQL restriction, so a row that a stale index still matches is shown until it is indexed again, which is why `reindexWhen()` matters (see [Keeping the keys fresh](#keeping-the-keys-fresh)). The `dropped` of a page counts only the rows deleted since they were indexed and the rows that a global scope or `search.trashed` leaves out. Declare the two attributes in the index settings, as in [Engines](#engines).
  - It refuses the search with `UnsupportedRestSearchException` when the model does not use `ControlledSearchable`, when the engine is not trusted (`Access::trustSearchEngine()`), and when the rest-api profile of the engine matches no list of strings, such as a profile that an extension provides without `MatchesStringLists`. A boundary without a search form throws `UnsearchableBoundaryException`. `rest:check` reports the first two and the boundary, with the Scout driver of the environment it runs in.
- Row checks: before the policies check rows for an ability, the extension preloads the decisions of these rows (`Access::preload()`), so the gates of a page cost one query per ability, not one per row and ability. It decides nothing and changes no row: the policy still decides each row. The ability goes through `abilityFor()` of the control, as in `ControlledPolicy`, so a business gate such as `approve` is preloaded too.
- Kept decisions: rest-api keeps the decisions of the abilities that a resource lists in `authorizationCache()`. The extension adds a hash of the decision context (`Access::decisionContextUsing()`) to their key, so a decision kept for one tenant is never read for another. rest-api files a kept decision under the `updated_at` of the row, which a change of its parent does not touch, so for an ability that a `ThroughParent` delegates, the extension adds to the key the `updated_at` of the parent, and of each parent above it that the delegated ability reaches (one query per level for the rows of a page, never one per row). When a parent changes, the decisions on its children are made again. A parent row without `updated_at`, or whose model has no timestamps, keeps no decision on its children. Like the row itself, a parent only changes its `updated_at` through Eloquent: a parent written with `DB::table()` or inside `Model::withoutTimestamps()` keeps the old decisions until the duration of `authorizationCache()` runs out. A perimeter that reads another table through its `query()` keeps the duration of `authorizationCache()` too: shorten it, or call `Rest::forgetAuthorizations()` when that table changes.
- Columns: rest-api loads the columns of the fields and of `alwaysSelect()`, and a policy that reads another column throws `MissingColumnException`. List in `alwaysSelect()` the columns that the control reads in memory: the column and the read-only columns of the boundary, the columns of the `should()` callbacks, and `updated_at` for the kept decisions.
- `php artisan rest:check` lists the extension, with origin `runtime`, and accepts a controlled resource without `perimeterQuery()` or `#[WithoutPerimeter]`: the extension provides its perimeter (`perimeter from access_control`, the `rows.extension_perimeter` key of `--json`). It reports a control that cannot be built. rest-api itself reports a model without a policy: call `Access::registerPolicies()` or give the model a policy. `rest:check` reads the config of its environment: where the extension is disabled, it reports again every resource without a perimeter.

Remove the controls from the `perimeterQuery()` of your resources. A resource whose `perimeterQuery()` only applied the control loses the method, and needs no `#[WithoutPerimeter]`:

```php
// Before
class PostResource extends Resource
{
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->controlledFor(request()->user());
    }
}

// After
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PostResource extends Resource
{
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->withoutPerimeter();
    }
}
```

Keep in `perimeterQuery()` the conditions that are not access rules, such as a published flag. Remove a `prepareAuthorization()` override that preloads the decisions of a controlled model.

To turn the extension off, list it in `disabled_extensions` of `config/rest.php`: rest-api then leaves the rows to `perimeterQuery()` and the decisions to the policies alone.

```php
'disabled_extensions' => [
    \Lomkit\Access\Integrations\RestApi\AccessControlExtension::class,
],
```

`AccessControlExtension` is final, so that no subclass can drop the restriction of the rows, the filter of the search engines or the decision context of the kept decisions. Keep the conditions that are not access rules, such as a published flag, in `perimeterQuery()` for the SQL lists and in `ScoutEngine::perimeter()` for the text searches, the `database` and `collection` drivers included: rest-api applies both, and the extension adds its restriction to them.

```php
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PostResource extends Resource
{
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->perimeter(
            fn (EngineQuery $query) => $query->where('published', '=', true),
            [Boolean::make('published')],
        );
    }
}
```

If you extended `AccessControlExtension`, move the conditions of its `restrict()` to these two methods and delete the subclass: PHP refuses to load it. To replace the access rules of rest-api instead, list `AccessControlExtension` in `disabled_extensions` and register an extension of your own: the package then restricts nothing in rest-api, text searches included, preloads no row check and adds nothing to the key of the kept decisions. That extension is the responsibility of your application.

### Removed helpers

`Control::appNamespace()` is removed. Read the namespace of the application with `app()->getNamespace()`.

### Generators

`make:control` without `--model` now names the model after the control, such as `App\Models\Post` for `PostControl`, where it used to write a placeholder that did not compile. It refuses, and writes nothing, when the model is not an Eloquent model class. `--model` also accepts a fully qualified class outside `App\Models`, such as `--model='Domain\Blog\Post'`.

`make:perimeter` still generates an empty perimeter class, whose rules are declared as callbacks in the control. The new `--hooks` option generates a class that overrides `allows()`, `includes()` and `scope()` and denies everything until you fill them, from the new `perimeter.hooks.stub`, which `stub:publish` publishes too. `make:control --perimeters=...` generates only the callbacks of the hooks each perimeter class does not override. The generated `control.stub` no longer carries descriptive docblocks and marks `perimeters()` with `#[\Override]`. `make:control` no longer generates a `scoutQuery` callback, and the `--hooks` perimeter no longer overrides `scoutScope()`: a published `stubs/perimeter.hooks.stub` that still does is a fatal error on generated classes, so publish it again or remove the method and the `ScoutBuilder` import. If your application published `stubs/control.stub`, publish it again with `php artisan stub:publish --force` or apply the same changes.

`make:perimeter --overlay` and the `perimeter.overlay.stub` are removed, with `OverlayPerimeter`: `stub:publish` no longer publishes the stub, and a published `stubs/perimeter.overlay.stub` is no longer read, so delete it. Generate a plain perimeter and place it outside the `Levels::of()` group of the control. `make:control --perimeters=...` lists the perimeters as loose perimeters, which add up: group the levels of a hierarchy in `Levels::of()` by hand. The generated control declares no boundary; add a `boundary()` method when the model needs one.

### Correspondence table

| 0.x | 1.0 |
| --- | --- |
| `'queries' => ['enabled_by_default' => true]` | `controlled()` on each query that must be filtered |
| `$control->queried($query, $user)` (removed) | `$query->controlledFor($user)` |
| `HasControlScope::resolveUser()` | `Auth::user()` |
| `HasControlScope::retrievingUser()` | Removed, no replacement |
| `withoutGlobalScope(HasControlScope::class)` | Nothing: `HasControlScope` is removed and the query reads every row; `uncontrolled()` to state it on purpose |
| `->query(fn ($query) => $query)`, `->should(fn () => true)` on a global perimeter | `->unrestricted()` |
| `->query(fn ($query) => Model::query()->where(...))` | `->query(function ($query): void { $query->where(...); })` |
| `->query(A)->query(B)`, or `allowed()`, `should()` or `searchKeys()` declared twice | One callback with both conditions: a second declaration throws `DuplicatePerimeterCallbackException` |
| `->query()` on a class that overrides `scope()`, to narrow it | A named constructor, such as `OwnedByMyEntities::ofKind('invoice')`, or a wither, such as `->ofKind('invoice')`, read by every hook |
| `Access::controlForModel($model)` (returns `?Control`) | `Access::controlFor($model)` (throws `MissingControlException`) and `Access::hasControl($model)` |
| `(new Access)->addControl($control)`, `addControls()`, `discoverControls()` | `#[ControlledBy(PostControl::class)]` on the model |
| `Access::$controlDiscoveryPaths = [...]` (replaced `app/Access/Controls`) | `Access::discoverControlsIn([...])` in the `boot()` of each provider: adds to `app/Access/Controls`, which is always scanned |
| `Access::flush()` | Removed: the registry is a container singleton |
| `Access::cache()`, `Access::cachePath()`, `Access::discoverControlClasses()` | `php artisan access:cache`, `php artisan access:clear` |
| A misconfigured control found at its first use in production | `php artisan access:check` in CI, with the production Scout driver |
| `config/access-control.php`, `--tag=access-control-config` | Removed: delete the published file |
| `config('access-control.methods.view')`, `$this->viewMethod()` | `Ability::view()` (and `create()`, `update()`, `delete()`, `restore()`, `forceDelete()`) |
| A custom `methods` map | `abilityFor()` overridden on the control |
| `->allowed(fn (User $user, string $method) => ...)`, or an untyped `$method` | `->allowed(fn (User $user, Ability $ability) => ...)`: an untyped or `string` parameter throws `AbilityParameterTypeException` |
| `$control->applies($user, 'update', $post)` | `$control->applies($user, Ability::update(), $post)` |
| `$ability === 'view'` | `$ability->is('view')` |
| `applyAllowedCallback(Model $user, $method)` overridden on a perimeter | `protected function allows(AccessContext $context): bool` |
| `applyShouldCallback(Model $user, Model $model)` overridden on a perimeter | `protected function includes(Model $row, AccessContext $context): bool` |
| `applyQueryCallback(Builder $query, Model $user)` overridden on a perimeter | `protected function scope(Builder $query, AccessContext $context): void` |
| `applyScoutQueryCallback(ScoutBuilder $query, Model $user)` overridden on a perimeter | `->searchKeys(name: ..., row: ..., reader: ...)` on the perimeter |
| `$perimeter->applyShouldCallback($user, $row)` called directly | `$perimeter->admits($user, $ability, $row)` |
| `$control->getPerimeters()` | Removed: `applicablePerimeters($reader, $ability)` inside the control |
| `$this->allowedCallback = ...` in a perimeter subclass | `->allowed(...)`, or the `allows()` hook |
| `$user instanceof User ? ... : $query->whereRaw('0 = 1')` in a callback | Type the reader: `fn (Builder $query, User $user) => ...` |
| `Control::appNamespace()` | `app()->getNamespace()` |
| `class SharedPerimeter extends OverlayPerimeter` | `class SharedPerimeter extends Perimeter`, written outside the `Levels::of()` group |
| Perimeters that stop at the first allowed one | `Levels::of(...)`, widest first, in the same order; checked by `assertLevelsNest()` |
| `overlays()` overridden on a perimeter | Removed: a perimeter outside a group always adds up |
| `make:perimeter --overlay`, `stubs/perimeter.overlay.stub` | `make:perimeter`, the perimeter written outside the group |
| `applies()` or `applyQueryControl()` overridden on a control to add a condition | `boundary()`, returning `Boundary::where(...)` or a `Boundary` class |
| `class PostPolicy extends ControlledPolicy { protected string $control = PostControl::class; }` with no other method | Deleted, with `Access::registerPolicies()` in a service provider's `boot()` |
| `ControlledPolicy` methods returning `bool` | `Response`, with a code: `unsaved`, `no_perimeter`, `outside_boundary`, `not_found` (404), `outside_rows` |
| `create(Model $user)` overridden on a policy | `create(Model $user, ?Model $draft = null): Response` |
| `$this->authorize('create', Post::class)` before `Post::create($data)` | `$this->authorize('create', [Post::class, new Post($data)])` |
| `$control->applies($user, $ability, new Post)` | `$control->applies($user, $ability)`, or `Draft::of($post)` for a creation |
| `$control->applies($user, $control->abilityFor('approve'), $post)` in a policy | `$this->decideByControl(__FUNCTION__, $user, $post)` |
| `$this->getControl()->applies($user, 'approve', $post)` in a policy | `$this->decideByControl(__FUNCTION__, $user, $post)` |
| `getControl()` overridden on a policy | `protected function declaredControl(): ?Control` (`getControl()` is removed; an override is no longer called) |
| `$post->fill($data)` then `authorize('update', $post)` to check the new values | Decided on the stored row: check the new values through a draft |
| A `should` callback that repeats the `query()` of its perimeter | Removed: the row is checked in SQL through the `query()` |
| `MissingPerimeterCallbackException` for a saved row on a perimeter without `should` | The row is checked in SQL; keep `should` for drafts |
| A brand or tenant read inside the callbacks, with decisions cached by hand | `Access::decisionContextUsing(fn () => ['brand' => ...])`, read in hooks with `$context->decisionContext()` |
| An `allowed()` that reads the current team (spatie `setPermissionsTeamId()`) or tenant | Add it to `Access::decisionContextUsing()`: the answer of `allowed()` is kept per reader, ability and decision context for the request |
| `->addSelect(['can_update' => ...])` or a helper that collects the editable keys | `->withAbilities(['update'])`, then `can()`; `Access::preload()` or `reachableKeys()` for loaded rows |
| A home-made editable-posts helper that pages or counts the rows a user may update | `Post::controlled('update')->paginate()`, `Post::controlledFor($user, 'update')->count()` |
| `perimeterQuery()` on a rest-api resource that calls `controlled()` or `controlledFor()` | Removed: `AccessControlExtension` restricts the rows and provides the perimeter to `rest:check` |
| `prepareAuthorization()` on a rest-api resource that calls `Access::preload()` | Removed: `AccessControlExtension` preloads the row checks |
| `->query(fn ($query) => $query->whereHas('post', fn ($posts) => $posts->controlled()))` on a child | `ThroughParent::belongsTo('post', inverse: 'comments')`, with `->delegates([...])` beyond `view` |
| A test that compares `can()` with `controlled()` by hand | `assertControlAgrees(PostControl::class, $reader, $rows)` |
| A listener on the parent that reindexes its children's documents | `ThroughParent::belongsTo('post', inverse: 'comments')` on the child's control |
| `use Searchable` on a controlled model | `use ControlledSearchable`, then reindex |
| `->scoutQuery(fn ($query, $user) => $query->where('client_id', $user->client_id))` | `->searchKeys(name: 'client', row: fn ($post) => $post->client_id, reader: fn ($user) => $user->client_id)` |
| `Post::search($terms)->get()` on a controlled model | `Post::search($terms)->controlled()->get()`, or `->uncontrolled()` on purpose |
| `UnsearchableBoundaryException` for every boundary | `Boundary::where()` searches; a boundary class declares `searchKeys()` |
| The `collection` Scout engine with `controlled()` | The `database` engine |
| A Meilisearch tenant token, a Typesense scoped key or an Algolia secured API key whose filter is written by hand | `Access::searchToken($user, Post::class, SearchTokenKey::meilisearch(uid: ..., key: ...), now()->addMinutes(15))` |
| A `saved` listener on `Project` that calls `$project->posts()->searchable()` | `->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts())` |
| A custom pivot whose `saved` / `deleted` listeners reindex the post | `use ReindexesSearch` on the pivot |
| `->query(fn ($query) => $query->join('projects', ...)->where('projects.client_id', ...))` | `->query(fn ($query) => $query->whereHas('project', fn ($q) => $q->where('client_id', ...)))`: a join throws `JoinInPerimeterException` |
