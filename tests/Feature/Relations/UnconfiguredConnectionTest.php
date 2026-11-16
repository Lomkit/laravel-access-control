<?php

namespace Lomkit\Access\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidParentRelationException;
use Lomkit\Access\Exceptions\MissingParentControlException;
use Lomkit\Access\Exceptions\RelationInspectionQueryException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Relations\RelationInspector;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Controls\OfficeControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Invalid\MisnamedInverseOfficeControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Invalid\OrphanOfficeControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Invalid\QueryingOfficeControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Office;
use Lomkit\Access\Tests\Fixtures\Unconfigured\QueryingOffice;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Region;
use Lomkit\Access\Tests\Fixtures\Unconfigured\SchemaReadingOffice;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class UnconfiguredConnectionTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::discoverControlsIn([dirname(__DIR__, 2).'/Fixtures/Unconfigured/Controls']);
        Access::registerPolicies();
    }

    #[Test]
    public function it_builds_the_declaration_without_resolving_the_connection_of_the_models(): void
    {
        $resolver = Model::getConnectionResolver();

        $perimeters = Access::control(OfficeControl::class)
            ->declaration()
            ->perimeters()
            ->entries();

        $this->assertInstanceOf(ThroughParent::class, $perimeters[0]);
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
        $this->assertSame($resolver, Model::getConnectionResolver());
    }

    #[Test]
    public function it_still_refuses_an_inverse_that_is_not_the_has_many_on_the_same_keys(): void
    {
        $this->assertThrows(
            fn () => Access::control(MisnamedInverseOfficeControl::class)->declaration(),
            InvalidParentRelationException::class,
            sprintf('but [%s::headquarters()] is not the hasMany of the [%s] model on the same keys', Country::class, Office::class),
        );
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
    }

    #[Test]
    public function it_still_refuses_a_parent_without_control(): void
    {
        $this->assertThrows(
            fn () => Access::control(OrphanOfficeControl::class)->declaration(),
            MissingParentControlException::class,
            Region::class,
        );
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
    }

    #[Test]
    public function it_refuses_a_relation_method_that_runs_a_query_and_restores_the_resolver(): void
    {
        $resolver = Model::getConnectionResolver();

        $this->assertThrows(
            fn () => Access::control(QueryingOfficeControl::class)->declaration(),
            fn (RelationInspectionQueryException $exception): bool => str_starts_with($exception->getMessage(), sprintf('The [country] relation of the [%s] model runs a query on the [unconfigured] connection when the method is called ([select min("id") as ', QueryingOffice::class))
                && str_contains($exception->getMessage(), ' from "countries"]). The package reads the shape of a relation'),
        );
        $this->assertSame($resolver, Model::getConnectionResolver());
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
    }

    #[Test]
    public function it_refuses_a_relation_method_that_reads_the_schema(): void
    {
        $this->assertThrows(
            fn () => RelationInspector::shape(new SchemaReadingOffice, 'country'),
            RelationInspectionQueryException::class,
            RelationInspectionQueryException::for(SchemaReadingOffice::class, 'country', 'unconfigured')->getMessage(),
        );
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
    }

    #[Test]
    public function it_decides_on_the_real_connection_once_it_is_configured(): void
    {
        Access::control(OfficeControl::class)->declaration();
        $this->configureUnconfigured();
        $ada = $this->reader('Ada');
        $grace = $this->reader('Grace');
        $france = Country::query()->create(['name' => 'France']);
        $paris = Office::query()->create(['country_id' => $france->getKey(), 'city' => 'Paris']);

        $this->assertSame(
            'select * from "offices" where (((exists (select * from "countries" where "offices"."country_id" = "countries"."id"))))',
            Office::controlledFor($ada)->toSql(),
        );
        $this->assertSame(['Paris'], Office::controlledFor($ada)->pluck('city')->all());
        $this->assertSame([], Office::controlledFor($grace)->pluck('city')->all());
        $this->assertTrue(Gate::forUser($ada)->allows('view', $paris));
        $this->assertTrue(Gate::forUser($ada)->allows('create', new Office(['country_id' => $france->getKey()])));
        $this->assertFalse(Gate::forUser($grace)->allows('view', $paris));
    }

    private function configureUnconfigured(): void
    {
        config(['database.connections.unconfigured' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        Schema::connection('unconfigured')->create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::connection('unconfigured')->create('offices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->foreignId('headquarters_id')->nullable();
            $table->foreignId('region_id')->nullable();
            $table->string('city');
            $table->timestamps();
        });
    }

    private function reader(string $name): User
    {
        return User::factory()
            ->for(Client::factory()->createOne(['name' => "{$name} Ltd"]))
            ->createOne(['name' => $name, 'email' => strtolower($name).'@example.com', 'current_workspace_id' => 1]);
    }
}
