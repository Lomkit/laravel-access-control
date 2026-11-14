<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PresetPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ImmutablePerimeterTest extends PackageTestCase
{
    protected User $ada;

    protected Post $draft;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->draft = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
        Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Inventory']);
    }

    /** @return array<string, array{Closure(Perimeter): Perimeter}> */
    public static function declarations(): array
    {
        return [
            'allowed' => [fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (): bool => true)],
            'should' => [fn (Perimeter $perimeter): Perimeter => $perimeter->should(fn (): bool => true)],
            'query' => [fn (Perimeter $perimeter): Perimeter => $perimeter->query(fn () => null)],
            'scoutQuery' => [fn (Perimeter $perimeter): Perimeter => $perimeter->scoutQuery(fn () => null)],
            'unrestricted' => [fn (Perimeter $perimeter): Perimeter => $perimeter->unrestricted()],
        ];
    }

    /** @param  Closure(Perimeter): Perimeter  $declare */
    #[Test]
    #[DataProvider('declarations')]
    public function it_returns_a_new_perimeter_of_the_same_class_from_each_declaration(Closure $declare): void
    {
        $perimeter = OwnPerimeter::new();

        $declared = $declare($perimeter);

        $this->assertNotSame($perimeter, $declared);
        $this->assertInstanceOf(OwnPerimeter::class, $declared);
        $this->assertTrue($perimeter->shape()->matches(OwnPerimeter::new()->shape()));
        $this->assertFalse($declared->shape()->matches($perimeter->shape()));
    }

    #[Test]
    public function it_leaves_a_declared_perimeter_unchanged_when_it_is_declared_again(): void
    {
        $base = OwnPerimeter::new()->allowed(fn (User $user): bool => true);
        $base->query(function (Builder $query, User $user): void {
            $query->whereBelongsTo($user, 'author');
        });
        ConfiguredPostControl::bind($base);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [query] callback.', OwnPerimeter::class));

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_keeps_the_perimeters_of_a_control_when_code_declares_them_again(): void
    {
        $control = ConfiguredPostControl::bind(
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereBelongsTo($user, 'author');
                }),
        );

        foreach ($control->applicableTo($this->ada, Ability::view()) as $perimeter) {
            $perimeter
                ->allowed(fn (): bool => true)
                ->query(fn (Builder $query) => $query->whereRaw('1=1'));
        }

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        $this->assertSame(1, Post::controlledFor($this->ada)->count());
    }

    #[Test]
    public function it_ignores_the_callbacks_a_subclass_writes_in_properties_of_its_own(): void
    {
        ConfiguredPostControl::bind(PresetPerimeter::opening());

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [allowed] callback.', PresetPerimeter::class));

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_keeps_the_callbacks_and_the_unrestricted_flag_private(): void
    {
        $properties = (new ReflectionClass(Perimeter::class))->getProperties();

        $this->assertNotEmpty($properties);

        foreach ($properties as $property) {
            $this->assertTrue($property->isPrivate(), sprintf('The [%s] property of the perimeter is not private.', $property->getName()));
        }
    }

    #[Test]
    public function it_makes_the_plumbing_final_and_the_hooks_protected(): void
    {
        foreach (['admits', 'applyAllowedCallback', 'applyShouldCallback', 'applyQueryCallback', 'applyScoutQueryCallback', 'isUnrestricted'] as $method) {
            $reflection = new ReflectionMethod(Perimeter::class, $method);

            $this->assertTrue($reflection->isFinal(), "{$method}() is not final.");
            $this->assertTrue($reflection->isPublic(), "{$method}() is not public.");
        }

        foreach (['allows', 'includes', 'scope', 'scoutScope'] as $hook) {
            $reflection = new ReflectionMethod(Perimeter::class, $hook);

            $this->assertTrue($reflection->isProtected(), "{$hook}() is not protected.");
            $this->assertFalse($reflection->isFinal(), "{$hook}() is final.");
        }
    }

    #[Test]
    public function it_no_longer_hands_out_the_perimeters_of_a_control(): void
    {
        $this->assertFalse(method_exists(Control::class, 'getPerimeters'));
        $this->assertFalse(method_exists(Control::class, 'queried'));
        $this->assertFalse(method_exists(Control::class, 'appNamespace'));
        $this->assertFalse((new ReflectionMethod(Control::class, 'perimeters'))->isPublic());
        $this->assertTrue((new ReflectionMethod(Control::class, 'applicablePerimeters'))->isFinal());
        $this->assertTrue((new ReflectionMethod(Control::class, 'applicablePerimeters'))->isProtected());
        $this->assertTrue((new ReflectionProperty(Control::class, 'declaredPerimeters'))->isPrivate());
    }

    #[Test]
    public function it_keeps_the_rows_a_reader_sees_when_a_perimeter_list_is_probed(): void
    {
        Gate::define('view client posts', fn (User $user): bool => true);
        $control = ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client posts"))
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
        );
        $hank = User::query()->where('name', 'Hank')->firstOrFail();

        foreach ($control->applicableTo($this->ada, Ability::view()) as $perimeter) {
            $perimeter->query(fn (Builder $query) => $query->whereRaw('1=1'));
        }

        $this->assertSame(1, Post::controlledFor($this->ada)->count());
        $this->assertSame(2, Post::controlledFor($hank)->count());
    }
}
