---
name: laravel-access-control
description: Build and work with Lomkit Access Control features in Laravel, including Controls linked with #[ControlledBy], the Access facade, the Ability value object, Perimeters (fluent callbacks or protected hooks with an AccessContext, rows checked in memory or in SQL), ThroughParent (a child inherits the access of its belongsTo parent), Levels groups, boundaries, ControlledPolicy (Response codes, drafts, Access::registerPolicies(), business abilities through decide()), unrestricted perimeters, the assertLevelsNest() and assertControlAgrees() test assertions, batched decisions (withAbilities(), Access::preload(), reachableKeys()), the controlled(), controlledFor() and uncontrolled() query scopes, Laravel Scout searches through indexed access keys (ControlledSearchable, searchKeys(), reindexWhen(), the ReindexesSearch pivot trait, assertSearchAgrees()), and the laravel-rest-api extension (AccessControlExtension).
---

# Laravel Access Control

## When to use this skill

Use this skill when working with Lomkit Access Control in a Laravel project that requires `lomkit/laravel-access-control`. Activate when the user:

- Writes or modifies a `Policy` and wants authorization centralized in a single class, or mentions `Access::registerPolicies()`, a draft for `create`, `Draft::of()`, or a refusal code such as `outside_boundary`.
- Needs row-level filtering on Eloquent queries (multi-tenant, per-client, per-team, ownership), or a child model (comment, line, attachment) that has the access of its parent.
- Runs `php artisan make:control` or `php artisan make:perimeter`.
- Mentions `#[ControlledBy]`, `Access::controlFor()`, `Access::hasControl()`, or `access:cache`.
- Mentions `Ability`, `Ability::of()`, `abilityFor()`, or a business ability such as `approve` on a controlled model.
- Mentions `Control`, `Perimeter`, `AccessContext`, `decisionContext()`, `Access::decisionContextUsing()`, `Access::forgetDecisions()`, `withAbilities()`, `Access::preload()`, `reachableKeys()`, `assertControlAgrees()`, `admits()`, `applicablePerimeters()`, `ThroughParent::belongsTo()`, `delegates()`, `Levels::of()`, `Boundary`, `boundary()`, `unrestricted()`, `assertLevelsNest()`, `ControlledPolicy`, `HasControl`, `controlled()`, `controlledFor()`, or `uncontrolled()`.
- Wants `Model::search(...)` (Laravel Scout) to respect the same authorization rules, or mentions `ControlledSearchable`, `searchKeys()`, `reindexWhen()`, `ReindexesSearch`, `Access::searchFilterables()` or `assertSearchAgrees()`.
- Uses `lomkit/laravel-rest-api` with controlled models, or mentions `AccessControlExtension`, `perimeterQuery()` with `controlled()`, `disabled_extensions` or `InvalidRestReaderException`.
- Asks why a search still shows a document after a related model changed (stale access keys), or how to reindex the documents when a parent, a pivot or a mass update changes the data a key reads.

Before applying, confirm `lomkit/laravel-access-control` is in `composer.json`. If missing, suggest `composer require lomkit/laravel-access-control` and stop. If present, note that the package has no configuration file: a leftover `config/access-control.php` is ignored and can be deleted. Flag that the package is in **Beta** before recommending it for production. Full docs: https://laravel-access-control.lomkit.com

## Features

- Perimeter: fluent definition of `allowed`/`should`/`query`/`searchKeys` for one access rule. How perimeters combine is declared in the control (see Levels below), never by the class or the order of a perimeter. A perimeter is immutable: each fluent method returns a new perimeter and leaves the one it is called on unchanged, so always chain the declarations or keep the returned value. `should` and `query` receive the ability after their other arguments (`fn (User $user, Post $post, Ability $ability)`; optional, always `view` for the controlled queries). `allowed` and `should` must return a boolean, or `PerimeterCallbackResultException` is thrown. A perimeter whose callbacks type the reader (`fn (User $user, ...)`) applies only to readers of that type (class, nullable, interface, union or intersection): another reader type skips the perimeter instead of a TypeError; untyped, `Model`, `object` or `mixed` accept every reader. The `query` callback constrains the builder it receives and returns nothing (returning that same builder is accepted, e.g. from an arrow function); its return value is never used. `searchKeys(name:, row:, reader:, load:)` declares how the perimeter is searched, and `reindexWhen(Model::class, changed:, rows:)` when its documents are reindexed (see Laravel Scout search below). Example usage:

```php
use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\Perimeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

Perimeter::new()
    ->allowed(fn (Model $user, Ability $ability) => $user->can("{$ability} client models"))
    ->should(fn (Model $user, Model $model)    => $model->client_id === $user->client_id)
    ->query(function (Builder $query, Model $user): void {
        $query->where('client_id', $user->client_id);
    })
    ->searchKeys(
        name: 'client',
        row: fn (Model $model) => $model->client_id,
        reader: fn (Model $user) => $user->client_id,
    );
```

- Perimeter class: a class-based perimeter overrides protected hooks, each receiving a final `Lomkit\Access\AccessContext` (`reader(): Model`, `ability(): Ability`), built by the package: `allows(AccessContext $context): bool` (the permission), `includes(Model $row, AccessContext $context): bool` (this row), `scope(Builder $query, AccessContext $context): void` (the rows in SQL). Search keys have no hook: declare them with `searchKeys()`. The default hooks run the declared callbacks. A rule is written once: declaring the `allowed`, `should` or `query` callback of a hook the class overrides throws `OverriddenPerimeterHookException`, and `unrestricted()` on a class that overrides `includes` or `scope` throws `UnrestrictedPerimeterCallbackException`. The `apply*Callback()` plumbing is final and internal: never override or call it. The reader is typed `Model` in the hooks (Liskov), so narrow it with `instanceof`. `make:perimeter --hooks` generates the three hooks denying everything; `make:perimeter` alone generates an empty class for callbacks declared in the control. Example usage:

```php
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

class EditorialPerimeter extends Perimeter
{
    protected function allows(AccessContext $context): bool
    {
        $reader = $context->reader();

        return $reader instanceof User && $reader->can("{$context->ability()} editorial posts");
    }

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

    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        $query->where('client_id', $reader instanceof User ? $reader->client_id : 0);
    }
}
```

- Which perimeter grants a row: `Perimeter::admits(Model $reader, Ability $ability, Model $row): bool` (public, final) tells whether a perimeter applies to the reader for the ability and contains the row (in memory with `should`, otherwise one `exists` on the stored row through `query()`; an unsaved row is never admitted that way); inside a control, `applicablePerimeters(Model $reader, Ability $ability)` (protected, final) lists the perimeters that apply: the level reached in each group, then the loose perimeters that allow the reader (an unrestricted one alone). It does not check the boundary against a row: the policy does. `getPerimeters()` no longer exists. Example usage:

```php
foreach ($this->applicablePerimeters($reader, Ability::update()) as $perimeter) {
    if ($perimeter->admits($reader, Ability::update(), $post)) {
        return $perimeter;
    }
}
```

- Unrestricted perimeter: `unrestricted()` marks a perimeter that reaches every row, such as an administrator's. It takes only `allowed`; declaring `query`, `should` or `searchKeys` with it throws `UnrestrictedPerimeterCallbackException`. In a search it gives no key filter: only the boundary and the version filter remain. When it applies to the reader, the controlled query and the controlled search get no perimeter constraint, the policy grants the row without calling `should`, and the perimeters after it are not evaluated, so no other perimeter narrows it. The control boundary still applies to it. Never write a global perimeter as `query(fn ($query) => $query)`: a `query` callback that adds no constraint throws `EmptyQueryCallbackException`. Example usage:

```php
GlobalPerimeter::new()
    ->allowed(fn (Model $user, Ability $ability) => $user->can("{$ability} global models"))
    ->unrestricted();
```

- Levels: `Levels::of(...)` (`Lomkit\Access\Perimeters\Levels`) declares a hierarchy, widest level first. For each ability, only the highest level the reader reaches (the first whose `allowed` passes) applies, in the policy and in SQL; the levels below are neither evaluated nor added to the query, so a reader can be at the client level for `view` and at the own level for `update`. Every perimeter outside a group adds up with the others (OR), wherever it is written; a control may declare several groups and loose perimeters. A level must contain the levels below it (check it with `assertLevelsNest()`); a perimeter that does not nest belongs outside the group. `OverlayPerimeter` and `overlays()` no longer exist. `Levels::of()` without a level throws `EmptyLevelsException`, a group given as a level throws `NestedLevelsException`. Example usage:

```php
use Lomkit\Access\Perimeters\Levels;

protected function perimeters(): array
{
    return [
        Levels::of(
            GlobalPerimeter::new()->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} global posts"))->unrestricted(),
            ClientPerimeter::new()->allowed(...)->should(...)->query(...),
            OwnPerimeter::new()->allowed(...)->should(...)->query(...),
        ),
        SharedPerimeter::new()->allowed(...)->should(...)->query(...), // outside the group: adds up
    ];
}
```

- ThroughParent: `ThroughParent::belongsTo('post', inverse: 'comments')` (`Lomkit\Access\Perimeters\ThroughParent`) gives a child the access of its `belongsTo` parent: the control of the parent decides for the same reader and decision context, with its boundary, in the policy, in `controlled()`, in the row checks (one `exists` through `whereHas()`, the parent's global scopes included), in `withAbilities()`, `Access::preload()` and `reachableKeys()`. Only `view` is delegated by default; `->delegates(['view', 'update', 'create' => 'view'])` replaces it (a list item delegates the same ability, a string key maps a child ability to a parent ability), and a non-delegated ability does not reach the perimeter. A draft (`create`) loads its parent by the foreign key (one query) and the parent control decides on that stored parent; no key, no parent: refused. It adds up like any loose perimeter (two parents add up), may be a level of a group, and parents nest. It takes no other callback (`ThroughParentCallbackException`). When the control builds its perimeters, before any query, a relation that is missing or not a `belongsTo` (or a wrong `inverse`) throws `InvalidParentRelationException`, a parent without control `MissingParentControlException`, a cycle `ParentCycleException`, a bad `delegates()` `InvalidDelegationException`. On a searchable child, the document carries composite keys `through.<relation>.<n>.<parent boundary key>.<parent access key>` (and the boundary key alone for readers the parent does not restrict), so a search keeps the same rule as SQL; `inverse:` is then required (`UnsearchableThroughParentException`, also when `view` maps to another parent ability or the parent boundary has no search form), and the children are reindexed when the parent's keys change, when the parent is deleted or restored, and when the parent's own `reindexWhen()` fires (a parent whose model is not searchable may declare it when such a child inherits its keys through `inverse:`; with no such child, `UnwatchableReindexException`). `through` is a reserved search key name. `make:control --parent=post` writes the entry. Example usage:

```php
use Lomkit\Access\Perimeters\ThroughParent;

protected function perimeters(): array
{
    return [
        ThroughParent::belongsTo('post', inverse: 'comments')->delegates(['view', 'create' => 'view']),
        AuthorPerimeter::new()->allowed(...)->query(fn (Builder $query, User $user) => $query->where('author_id', $user->id)),
    ];
}
```

- Boundary: `boundary(): Boundary` on the control declares a condition every row must meet whatever perimeter grants it (workspace, tenant). It is an AND around the perimeters, applied before the levels, even for an unrestricted perimeter, in the controlled queries and in the policy; the default is `Boundary::none()`. Declarative form: `Boundary::where('workspace_id', fn (User $user) => $user->current_workspace_id)`, one declaration for SQL and for the policy (in memory, no query); `->readOnlyWhenNotNull('archived_at')` keeps a row readable and refuses it to every other ability while the column is not null. A `null` reader value, or a reader whose type the closure does not accept, closes the boundary: no row, every ability refused, `create` included, never an `IS NULL`; a value that is not an int, a string or null throws `InvalidBoundaryValueException`. Complex form: a class extending `Lomkit\Access\Boundaries\Boundary` that overrides `scope(Builder $query, AccessContext $context): void` (SQL) and optionally `includes(Model $row, AccessContext $context): bool` (memory). Without `includes()`, the policy checks the boundary with one `exists` on the row, even for an unrestricted perimeter; with it, without SQL. `boundary()` must be pure, like `perimeters()`. Example usage:

```php
use Lomkit\Access\Boundaries\Boundary;

protected function boundary(): Boundary
{
    return Boundary::where('workspace_id', fn (User $user) => $user->current_workspace_id)
        ->readOnlyWhenNotNull('archived_at');
}

class TenantBoundary extends Boundary
{
    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        $query->where('tenant_id', $reader instanceof User ? $reader->tenant_id : 0);
    }
}
```

- Testing: the `Lomkit\Access\Testing\InteractsWithAccess` trait provides three assertions, `assertLevelsNest()`, `assertControlAgrees()` and `assertSearchAgrees()` (see Laravel Scout search below). `assertControlAgrees(PostControl::class, $reader, $rows, abilities: ['view', 'update', 'delete'])` compares, for each ability and saved row, the Gate row by row (nothing kept), after `Access::preload()`, after `withAbilities()`, and for `view` the membership in `controlled()`; a failure lists the reader, each row key and ability, and the answer of each path (usually a `should` that describes other rows than its `query()`). No reset trait is needed: kept decisions are scoped per test, flushed by Eloquent writes; call `Access::forgetDecisions()` after `attach()`. `assertLevelsNest(PostControl::class, readers: [$author, $clientManager, $admin])`: For each reader and each pair of adjacent levels, it fails when a row of the lower level, inside the boundary, is missing from the upper one. It reads the `query` callbacks with `view` (not `allowed`), compares with `not exists` on the key (NULL-proof), and proves the nesting only on the test data.

- Control: one class per model declaring `$model` (as the property default, an Eloquent model class), its perimeters (loose perimeters and `Levels::of()` groups) and, optionally, its `boundary()`. The model names its control with `#[ControlledBy(PostControl::class)]`; a model without the attribute falls back to the control discovered in `app/Access/Controls` whose `$model` is that model (change the directories with `Access::discoverControlsIn([...])` in a service provider's `boot()`). A model has a single control: two controls for one model, declared or discovered, throw `DuplicateControlException`; a `$model` that is missing, not a class or not an Eloquent model throws `InvalidControlModelException`. Example usage:

```php
namespace App\Access\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use App\Access\Perimeters\GlobalPerimeter;
use App\Access\Perimeters\ClientPerimeter;
use App\Models\Post;

class PostControl extends Control
{
    protected string $model = Post::class;

    protected function perimeters(): array
    {
        return [
            Levels::of(
                GlobalPerimeter::new()->allowed(...)->unrestricted(),
                ClientPerimeter::new()->allowed(...)->should(...)->query(...)->searchKeys(...),
            ),
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

- HasControl trait: adds three local scopes, declared with Laravel's `#[Scope]` attribute, and exposes the model's Control via `newControl()`. A query is never controlled unless it says so: `controlled()` applies the control for `Auth::user()`, read when the query is built; `controlledFor($reader)` applies it for a reader you name, for jobs, listeners and commands, which have no signed-in user; `uncontrolled()` states that the query reads every row on purpose. Without a signed-in user, `controlled()` reads nothing, and so does `controlledFor(null)`. The perimeters are grouped in one nested `where`, so the caller's own constraints are never widened. The trait also registers `HasControlScope` as a global scope that filters nothing; the three scopes remove it, and `withoutGlobalScope(HasControlScope::class)` still works. Each scope works on relations and subqueries too. A Scout search has its own `controlled()` and `uncontrolled()` (see Laravel Scout search below). Example usage:

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

- Ability: `Lomkit\Access\Ability` is the immutable value object that every perimeter callback and hook receives (the second parameter of `allowed`, the third of `should` and `query`, `$context->ability()` in a hook). `Ability::view()`, `create()`, `update()`, `delete()`, `restore()` and `forceDelete()` name the standard abilities; `Ability::of('approve')` names any business ability and throws `InvalidAbilityException` for an empty name or one with whitespace. Compare with `$ability->is('view')` or `$ability->is(Ability::view())`, never with `===`; it converts to its name in a string (`"{$ability} posts"`), and `name()` returns it. It is not laravel-rest-api's closed `Lomkit\Rest\Authorization\Ability` enum. `Control::abilityFor(string $policyMethod)` maps a policy method to its ability: `viewAny` and `view` → `view`, the other standard methods → themselves, any other method → `Ability::of($method)`; override it on a control to change the mapping. Example usage:

```php
use Lomkit\Access\Ability;

class PostControl extends Control
{
    public function abilityFor(string $policyMethod): Ability
    {
        return match ($policyMethod) {
            'viewAny' => Ability::of('list'),
            default => parent::abilityFor($policyMethod),
        };
    }
}

$control->applies($user, Ability::update(), $post);                         // bool
$control->decide($user, $control->abilityFor('approve'), $post);           // Response, Ability::of('approve')
$control->applies($user, Ability::create(), Draft::of($post));             // a draft, judged in memory
```

- ControlledPolicy: drop-in policy that delegates every Gate method (`view`, `viewAny`, `create`, `update`, `delete`, `restore`, `forceDelete`) to the Control, passing the `Ability` that `abilityFor()` maps the method to, and returns an `Illuminate\Auth\Access\Response` (never a bool; overrides declare `: Response`). Prefer `Access::registerPolicies()` in a service provider's `boot()` to empty policy classes: every controlled model without any policy gets a `ControlledPolicy`, resolved lazily through `Gate::guessPolicyNamesUsing()`; a policy from `Gate::policy()`, `#[UsePolicy]` (on the model or a parent), a parent mapping or the naming convention always wins (set an app guesser before the call). `$control` is optional: without it a row is decided by its model's control, but `viewAny`, `create` without a draft and row-less business abilities throw `MissingPolicyControlException`. A `$control` that is not the model's registered control throws `PolicyControlMismatchException`. Refusals carry a code and a translated message (`lang/vendor/access-control/{locale}/refusals.php`, `vendor:publish --tag=access-control-lang`), checked in this order: `unsaved` (a model that is not saved, for every ability but a `create` draft), `no_perimeter`, `outside_boundary` (403, the reader can still view the row, e.g. archived) or `not_found` (404 via `denyAsNotFound()`, the boundary also refuses `view`, e.g. another tenant), `outside_rows`. Rows are decided on their stored values: after `fill()`, `should` and the boundary get a copy built from `getRawOriginal()`. A saved row is checked in memory by the perimeters that have a `should` callback or an `includes()` hook (and by an unrestricted one), and in SQL by the others: one query on the row's key, constrained by the OR of their `query()` scopes, with the boundary in it when the boundary has no in-memory form. The in-memory checks run first and the SQL only when none grants; the mode never depends on loaded relations, so query counts are predictable. Drop a `should` that only repeats its `query()`; keep it where readers create rows (drafts) or to save the query. `Access::decisionContextUsing(fn (): array => ['brand' => Brand::current()?->id])` (global, optional; named strings, ints, bools or nulls, else `InvalidDecisionContextException`) adds values outside the reader to every decision; hooks read them with `$context->decisionContext()`. The package keeps the SQL answers of a request under the reader's morph class and key, that context, the model, the row key and the ability; they are forgotten when any Eloquent model is saved or deleted, on a rollback, at the end of each request or job, and on `Access::forgetDecisions()`, which the app calls after `attach()`/`detach()`/`sync()`, `DB::table()` or mass updates, `saveQuietly()` and `withoutEvents()`. Batches: `Post::controlled()->withAbilities(['view', 'update'])` (after `controlled()`/`controlledFor()`, else `MissingControlledReaderException`; names or `Ability` objects through `abilityFor()`) decides every loaded row in the same query, one `CASE` per ability under reserved aliases stripped from the models, so the Gate checks on the page cost no SQL; it works with `get`, `paginate`, `simplePaginate`, `cursorPaginate`, `chunk`, `lazy`, `cursor` and eager loads of controlled relations. `controlled()` never decides `view` by itself: add it to `withAbilities()`. `Access::preload($reader, 'update', $rows)` does one query per ability and model for loaded rows; `$control->reachableKeys($reader, Ability::update(), $rows)` returns the keys the reader may act on, in one query. The batches hold only the SQL part; the in-memory checks always run again, so a batched answer equals the row-by-row one. An Eloquent write per row during rendering flushes them. `create(Model $user, ?Model $draft = null)`: without a draft it means "somewhere" (only `allowed`); with one (`can('create', [Post::class, $draft])`, or laravel-rest-api, which passes it), the draft must land in a perimeter, judged only in memory (`should`/`includes`, unrestricted, the boundary's in-memory form): a perimeter without `should` cannot grant it and a boundary without `includes()` refuses it. Business abilities call the protected `decide(string $method, Model $user, Model|Draft|null $subject = null)` helper. Example usage:

```php
use Illuminate\Auth\Access\Response;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Policies\ControlledPolicy;

Access::registerPolicies(); // AppServiceProvider::boot(): no empty policy files

class PostPolicy extends ControlledPolicy // only when the model needs business abilities
{
    protected string $control = PostControl::class;

    public function approve(User $user, Post $post): Response
    {
        return $this->decide(__FUNCTION__, $user, $post);
    }

    public function duplicate(User $user, Post $post, Post $copy): Response
    {
        return $this->decide(__FUNCTION__, $user, Draft::of($copy));
    }
}

$user->can('view', $post);                          // → PostControl perimeters with Ability::view()
$user->can('create', [Post::class, new Post($data)]); // the draft must land in a perimeter
Gate::inspect('update', $post)->code();             // null, or 'no_perimeter', 'outside_rows', ...
```

- Artisan generators: scaffold Controls and Perimeters. Generated files land in `app/Access/Controls` and `app/Access/Perimeters`. Example usage:

```bash
php artisan make:perimeter GlobalPerimeter
php artisan make:perimeter OwnPerimeter --hooks   # the rules in the class: allows(), includes(), scope()
php artisan make:control PostControl --model=Post --perimeters=GlobalPerimeter --perimeters=ClientPerimeter
php artisan make:control PostControl   # the model is named after the control: App\Models\Post, which must exist
```

- Laravel Scout search: replace Scout's `Searchable` with `Lomkit\Access\Search\ControlledSearchable` (it includes it). `toSearchableArray()` is untouched: the trait adds Scout metadata to each document, `_access` (the row keys of every perimeter with `searchKeys`) and `_boundary` (the boundary keys). Each perimeter declares `searchKeys(name: 'client', row: fn (Post $post) => ..., reader: fn (User $user) => ..., load: ['project:id,client_id'])`: the name is required, `[a-z0-9_]`, unique in the control, and prefixes each key (`client.7`); each side returns a value, a list (`[3, 7]`) or tuples (`[['fr', 7]]` → `client.fr.7`, for a context such as a brand); `null` gives no key. Row keys are computed on a copy of the rows with lazy loading disabled: a relation read must be in `load:` (loaded once per batch), or `UndeclaredSearchKeyRelationException`. A search must say `controlled()` (the signed-in user), `controlledFor($reader)` (a named reader, in jobs and commands; `null` finds nothing) or `uncontrolled()` (every row, on purpose), or it throws `UndeclaredSearchScopeException`. The filter is one engine query: `_boundary IN reader keys AND _access IN reader keys` (levels: only the reached level's keys; unrestricted: no `_access` filter). No reader, no allowed perimeter, no reader key or a null boundary value → an empty page, without calling the engine. `Boundary::where()` gives its keys; a boundary class overrides `protected function searchKeys(): ?SearchKeys` returning `SearchKeys::of(name:, row:, reader:)`, else a controlled search on an index engine throws `UnsearchableBoundaryException`. Engines: Algolia, Meilisearch, Typesense filter with `whereIn` (declare `Access::searchFilterables()` as filterable: Meilisearch `filterableAttributes`, Typesense `string[]` schema fields, Algolia `attributesForFaceting`); `database` applies the control's SQL inside the search (no keys computed); Turbopuffer is filtered with its native `['_access', 'ContainsAny', [...]]` filters, AND-ed with the app's `options(['filters' => ...])` and applied again when the search runs (declare `_access` and `_boundary` as `['type' => '[]string', 'filterable' => true]` in the model's `schema` under `scout.turbopuffer.model-settings` before the first write); `collection` throws `UnsupportedSearchEngineException`, and so does any other engine until the app calls `Access::trustSearchEngine(MyEngine::class)` (only for an engine that renders `whereIn` on an array attribute as "at least one element in common"; Scout's `null` engine is accepted). A model that overrides `makeSearchableUsing()` returns `$this->withAccessKeys($models)`. Reindex (`scout:import`) after changing the `row` side of a key rule or the boundary, as after changing `toSearchableArray()`: until then the documents keep their former keys (`scout:flush` first to close an access at once). The `reader` side needs no reindex. Freshness: Scout reindexes a document when its own model is saved (and when the model's `searchIndexShouldBeUpdated()` returns false, the package compares the keys before and after the save, after the commit, and reindexes if they differ). When a key reads another model, chain `->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts())` after `searchKeys()` (several models allowed, once each): on `updated` with a changed listed attribute, `created`, `deleted` and `restored`, a job queued after the commit (always, on `scout.queue`'s connection and queue, synchronous without a queue, nothing on rollback) reindexes in chunks the rows of the current state, of the previous state and of a deleted watched row (the job carries the key, the previous values of the `changed` attributes on update, and on delete the attributes without the `$hidden` columns: a `rows` callback must not depend on a hidden attribute); `rows` returns a query or relation of the controlled model. The control must be discovered (`app/Access/Controls` or `Access::discoverControlsIn()`), its model must use `ControlledSearchable` (or have a searchable `ThroughParent` child with `inverse:` that inherits its keys), and `access:cache` caches the watched models with the controls, used as is like Laravel's route and event caches: a deployment runs it again (`php artisan optimize` does), and a stale cache throws `UnwatchableReindexException` when a control with an uncached `reindexWhen()` is built; a worker that only updates the watched model still reindexes, through package listeners on every Eloquent `created`/`updated`/`deleted`/`restored` event (one lookup per event for unwatched models). A custom pivot (`belongsToMany(...)->using(PostShare::class)`) with `use Lomkit\Access\Search\ReindexesSearch;` reindexes the searchable side(s) on attach, detach, sync, toggle and updateExistingPivot: one unique job per document after the commit (wrap bulk writes in `DB::transaction()` without a queue). Declare `->using(YourPivot::class)` on both sides of the relation, otherwise attach/detach from the other side fires no event and the documents keep stale keys; the pivot keys must be the primary keys. A pivot written outside its relation is refused before the write (`UnresolvablePivotException`), so is one that links no `ControlledSearchable` model (`MissingControlledSearchableException`). A second `reindexWhen()` for the same model, a parent or a child class throws `DuplicateReindexException`. Declaration errors: `ReindexWithoutSearchKeysException`, `InvalidReindexModelException`, `InvalidReindexAttributesException`, `InvalidReindexRowsException`, `DuplicateReindexException`. `assertSearchAgrees(PostControl::class, readers: [...])` (trait `InteractsWithAccess`) compares the rows the keys find, computed in PHP, with the controlled query. Browser search: `Access::searchToken(reader: $user, model: Post::class, key: SearchTokenKey::meilisearch(uid: ..., key: ...), expiresAt: now()->addMinutes(15))` (or `SearchTokenKey::typesense(key:)`, `SearchTokenKey::algolia(key:)`, matching the model's engine, else `MismatchedSearchTokenKeyException`) returns a `SearchToken` (`token()`, `index()`, `expiresAt()`, `filter()`; `toArray()`/JSON: token, index, expires_at): a Meilisearch tenant token (`searchRules` on the model's index), a Typesense scoped search key or an Algolia secured API key (`restrictIndices`), signed with the reader's `searchFilter()`, the filter `controlled()` uses. A closed reader gets a filter on an `_access` key no document carries (finds nothing); an unrestricted reader without a boundary gets no filter. The expiry is required and must be in the future (`PastSearchTokenExpiryException`): keep it short, minutes to an hour. The key must be a search-only key, never Scout's admin key (`AdminSearchTokenKeyException`); with Typesense, limit the parent key to the model's collection, since a scoped key cannot narrow it. Meilisearch and Typesense need their official client (`MissingSearchClientException`); Algolia is signed by the package. `database`, `collection`, `null`, Turbopuffer and trusted engines throw `UnsupportedSearchTokenEngineException`. Example usage:

```php
use Lomkit\Access\Search\ControlledSearchable;

#[ControlledBy(PostControl::class)]
class Post extends Model
{
    use HasControl, ControlledSearchable;
}

OwnPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} own posts"))
    ->query(fn (Builder $query, User $user) => $query->where('author_id', $user->id))
    ->searchKeys(name: 'author', row: fn (Post $post) => $post->author_id, reader: fn (User $user) => $user->id);

ClientPerimeter::new()
    ->allowed(fn (User $user, Ability $ability) => $user->can("{$ability} client posts"))
    ->query(fn (Builder $query, User $user) => $query->whereRelation('project', 'client_id', $user->client_id))
    ->searchKeys(name: 'client', row: fn (Post $post) => $post->project->client_id, reader: fn (User $user) => $user->client_id, load: ['project:id,client_id'])
    ->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts());

class PostShare extends Pivot
{
    use ReindexesSearch; // attach / detach / sync → the post is reindexed after the commit
}

Post::search('budget')->controlled()->paginate(25);
Post::search('budget')->controlledFor($user)->get(); // jobs, listeners, commands
Post::search('budget')->uncontrolled()->get(); // back office, exports
```

- laravel-rest-api: with `lomkit/laravel-rest-api` ^3.6 installed, the service provider registers `Lomkit\Access\Integrations\RestApi\AccessControlExtension` (name `access_control`) on install; nothing to write in the resources. On every resource whose model has a control, it restricts the rows to what `controlledFor($user)` finds for `view` wherever rest-api applies a perimeter (searches, includes, relation filters and aggregates, metrics, the key lookups of writes and actions; a guest finds none), preloads the row checks of each ability with `Access::preload()` (one query per ability for the gates of a page; the policies still decide each row), adds a hash of the decision context to the key of the decisions that `authorizationCache()` keeps and, for the abilities a `ThroughParent` delegates, the `updated_at` of every parent up the chain (a parent without `updated_at` keeps no decision on its children), provides the perimeter of these resources to `rest:check` (no `perimeterQuery()` or `#[WithoutPerimeter]` needed) and reports there a control that cannot be built. A user that is not an Eloquent model throws `InvalidRestReaderException`. It is also the access filter of `ScoutEngine` text searches, which never go through `controlled()`: declare `ScoutEngine::make()->withoutPerimeter()`; on the `database` and `collection` drivers the rows are restricted in SQL, on another engine it filters `_access` and `_boundary` (declared as string lists) by the reader's keys and rest-api reads the page rows again through SQL. It refuses (`UnsupportedRestSearchException`) a model without `ControlledSearchable`, an untrusted engine and an engine whose rest-api profile matches no list of strings (Turbopuffer on rest-api 3.x for now), and `rest:check` reports them. Disable it with `'disabled_extensions' => [AccessControlExtension::class]` in `config/rest.php`; replace it with a subclass listed in `extensions` beside that entry. Example of a resource:

```php
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PostResource extends Resource // the extension restricts the rows: no perimeterQuery() that calls controlled()
{
    public static string $model = Post::class;

    public function alwaysSelect(): array
    {
        return ['archived_at', 'updated_at']; // columns the control reads in memory, updated_at for authorizationCache()
    }

    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->withoutPerimeter(); // the extension filters the engine by the access keys
    }
}
```

## Common pitfalls to flag when reviewing or writing code

  - With laravel-rest-api ^3.6, never apply a control in `perimeterQuery()` or preload in `prepareAuthorization()`: `AccessControlExtension` does both for every controlled resource and provides its perimeter to `rest:check`, so a resource whose `perimeterQuery()` only did that drops the method, without `#[WithoutPerimeter]`. List in `alwaysSelect()` the columns that the boundary and the `should()` callbacks read (rest-api throws `MissingColumnException` otherwise), and remember that a decision kept by `authorizationCache()` is only renewed with the `updated_at` of the row and, for a delegated ability, of its parents: a perimeter that reads another table through `query()`, or a parent written without touching its `updated_at`, keeps it for the whole duration.
  - No perimeter matches → query is forced to `0=1` (Eloquent), or the search returns an empty page without querying the engine (Scout). Empty results after wiring control usually mean no `allowed()` returned true.
  - Order and class no longer decide how perimeters combine: put a hierarchy in `Levels::of()`, widest first (admin grants first, declared with `unrestricted()`), and keep additive grants (shared, delegated) outside the group. Never put a perimeter that does not nest in a group: verify with `assertLevelsNest()`.
  - A tenant or workspace condition belongs in `boundary()`, not repeated in every `query`/`should`, and never in an override of `applies()` (final) or of the removed `applyQueryControl()`. A `Gate::before` that returns true bypasses the boundary (Laravel semantics).
  - A `query` callback must constrain the builder it receives: returning another builder (e.g. `Model::query()->where(...)`) throws `QueryCallbackReturnedAnotherBuilderException`. A `query` callback that adds no `where` (nothing, only `orderBy`/`limit`/`join`, or a `when()` that skips) throws `EmptyQueryCallbackException`: use `unrestricted()` for every row, or `whereRaw('0=1')` for no row. A `query` callback or a boundary `scope()` that adds a join throws `JoinInPerimeterException` (Laravel drops joins inside the nested where group): use `whereHas()` or an `exists` subquery.
  - A child with the access of its parent is a `ThroughParent::belongsTo(...)`, never a `query` callback with `whereHas('post', ...)` written by hand: that callback runs without the parent's control unless it calls `controlled()` itself, and opens every child of any parent once it is forgotten. Widen it with `delegates()` only for the abilities that truly follow the parent: delegating `delete` to the parent's `update` lets every editor of a post delete its comments.
  - `create` without a draft only runs `allowed` ("somewhere"): pass the draft (`can('create', [Post::class, $draft])`) to check where it lands, and give the perimeters readers create through a `should` callback, since a draft is judged only in memory.
  - Never `fill()` a row and authorize `update` to check the new values: the policy decides on the stored row; check new values through a `Draft`. Never decide `view`/`update` on an unsaved model: it is refused (`unsaved`).
  - A searchable child that inherits through a parent needs `inverse:` on its `ThroughParent` and a discovered control; a parent whose keys read a relation needs its own `reindexWhen()`, even when the parent itself is not searchable, or its children keep its former keys. A foreign key set to null by the database without an Eloquent event leaves the children with their former keys until they are reindexed.
  - `scoutQuery` and the `scoutScope()` hook no longer exist: declare `searchKeys()`. A perimeter reached by the reader without `searchKeys` adds nothing to the search (narrower than SQL): give every perimeter of a searchable model its keys, and check with `assertSearchAgrees()`.
  - Never reuse a search key name in a control (`DuplicateSearchKeyNameException`), and never return `null` hoping to match rows without a value: `null` gives no key.
  - The engine is the only barrier: a key that reads another model (`$post->project->client_id`, a pivot) needs `reindexWhen()` on the perimeter or `ReindexesSearch` on a custom pivot, or the documents keep their former keys after that model changes. A row whose keys changed without an Eloquent event (mass update, `DB::table()`, a plain pivot `attach()` without `using()`, `withoutEvents()`) keeps its old keys until the application reindexes it (`$model->searchable()`, `Model::where(...)->searchable()`). Even with them, the documents keep their former keys until the job has run (the queue's backlog): turn on Scout's `after_commit`. Never test controlled searches on the `collection` engine: use `database`.
  - A control that declares `reindexWhen()` must live in a discovered directory (not only `#[ControlledBy]`), and `php artisan access:cache` must run again after adding one; otherwise building the control throws `UnwatchableReindexException`. A parent that is not searchable is the exception: its searchable `ThroughParent` child must be discovered instead.
  - Plain queries, relations, lazy loads and route model binding are never controlled: every query that must be filtered says `controlled()`, or `controlledFor($reader)` where no user is signed in. Authorize a bound model through its policy.
  - `controlled()`, on a query or on a search, reads `Auth::user()` when it runs: do not call them from an event of the user model that the guard fires while it retrieves the user (such as `retrieved`), or the guard is asked for the user again.
  - A model is linked to its control by `#[ControlledBy]` or by the discovery, never by a policy: a control outside the discovered directories without the attribute leaves the model without control (`MissingControlException`).
  - Never write two controls for one model (e.g. an admin variant): `DuplicateControlException`. Put the variation in perimeters of the single control.
  - After adding, moving or deleting a control in production, run `php artisan access:cache` again (or `optimize`): the cache maps each model to its control.
  - Type the ability parameter of every callback `Ability $ability` (`fn (Model $user, Ability $ability)` for `allowed`, third parameter for `should` and `query`): an untyped or `string` parameter throws `AbilityParameterTypeException` when the perimeter is declared. `Stringable`, `object`, `mixed` and unions containing `Ability` are accepted. A callback without an ability parameter is fine.
  - Never override or call `applyAllowedCallback()`, `applyShouldCallback()` or `applyQueryCallback()`: they are final and internal. Override `allows()`, `includes()` or `scope()`, which receive an `AccessContext`.
  - `perimeters()` must be pure: the control builds its perimeters once per process (queue and Octane workers included). Never read the user, the request, the tenant, the database or the clock in `perimeters()`; read them in the callbacks, from the reader. In debug mode and in tests the registry rebuilds `perimeters()` on each lookup and throws `ImpurePerimetersException` when the result differs. It compares captured values: scalars and arrays by value, models by class and key, enums and readonly objects by value, closures recursively, any other object by class only (a service built in `perimeters()` passes). It cannot see the state inside a captured service, a model's attributes, or a value that is the same in both builds: never read the request or the tenant in `perimeters()` or in the constructor of a service it captures.
  - Perimeters are immutable: `$perimeter->query(...)` returns a new perimeter; a call whose result is dropped is lost.
  - Abilities are compared by name with `is()`: two `Ability::view()` are not the same object, and an unserialized ability is a new one, so `===` between abilities is never reliable.
  - Controls may execute `allowed()` twice in `index`-style endpoints (policy + `controlled()` scope); cache it on the user if expensive.
