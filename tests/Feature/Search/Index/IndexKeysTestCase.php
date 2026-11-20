<?php

namespace Lomkit\Access\Tests\Feature\Search\Index;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Search\IndexReaderKeys;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Index\Guide;
use Lomkit\Access\Tests\Fixtures\Index\Index;
use Lomkit\Access\Tests\Fixtures\Index\Ticket;
use Lomkit\Access\Tests\Fixtures\Testing\AccessTestCase;

abstract class IndexKeysTestCase extends AccessTestCase
{
    protected Client $acme;

    protected Client $globex;

    protected User $ada;

    protected User $grace;

    protected User $linus;

    protected User $root;

    protected User $nobody;

    protected ?int $brand = 1;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Index];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Client::factory()->createOne(['name' => 'Acme']);
        $this->globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = $this->user('Ada', $this->acme);
        $this->grace = $this->user('Grace', $this->acme);
        $this->linus = $this->user('Linus', $this->globex);
        $this->root = $this->user('Root', $this->globex);
        $this->nobody = $this->user('Nobody', $this->globex);

        $this->guide('Onboarding', 1, $this->acme, $this->ada);
        $this->guide('Billing', 1, $this->globex, $this->linus);
        $this->guide('Pricing', 2, $this->acme, $this->grace);
        $this->guide('Orphan', null, null, null);
        $this->ticket('Login fails', $this->acme, $this->linus);
        $this->ticket('Slow export', $this->globex, $this->ada);
        $this->ticket('Lost invoice', null, $this->grace);

        $this->grant([
            'view every guide' => ['Root'],
            'view own guides' => ['Ada'],
            'view client guides' => ['Grace'],
            'view every ticket' => ['Root'],
            'view client tickets' => ['Ada'],
            'view own tickets' => ['Grace', 'Linus'],
        ]);
        Access::decisionContextUsing(fn (): array => ['brand' => $this->brand]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    /** @return list<User|null> */
    protected function readers(): array
    {
        return [null, $this->nobody, $this->ada, $this->grace, $this->linus, $this->root];
    }

    /** @return array<string, array<string, list<string>>> */
    protected function collection(): array
    {
        $collection = [];

        foreach ([Guide::class, Ticket::class] as $model) {
            $rows = $model::query()->orderBy('id')->get();
            $titles = $rows->pluck('title', 'id')->all();

            foreach (Access::indexKeysOf($rows) as $key => $document) {
                $collection[class_basename($model).' '.$titles[$key]] = $document;
            }
        }

        return $collection;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $collection
     * @return list<string>
     */
    protected function naivelyFound(IndexReaderKeys $keys, array $collection): array
    {
        $found = [];

        foreach ($collection as $name => $document) {
            if (array_intersect($document['_access'], $keys->values()['_access']) !== []
                && array_intersect($document['_boundary'], $keys->values()['_boundary']) !== []) {
                $found[] = $name;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @param  class-string<Guide|Ticket>  $model
     * @return list<string>
     */
    protected function listed(string $model, ?User $reader): array
    {
        $titles = $model::query()
            ->controlledFor($reader)
            ->pluck('title')
            ->map(fn (string $title): string => class_basename($model).' '.$title)
            ->all();

        sort($titles);

        return $titles;
    }

    protected function guideKey(string $title): int|string
    {
        return Guide::query()->where('title', $title)->firstOrFail()->getKey();
    }

    protected function ticketKey(string $title): int|string
    {
        return Ticket::query()->where('title', $title)->firstOrFail()->getKey();
    }

    /** @param  array<string, list<string>>  $permissions */
    protected function grant(array $permissions): void
    {
        foreach ($permissions as $permission => $names) {
            Gate::define($permission, fn (User $reader): bool => in_array($reader->getAttribute('name'), $names, true));
        }
    }

    protected function user(string $name, Client $client): User
    {
        return User::factory()
            ->for($client)
            ->createOne(['name' => $name, 'email' => strtolower($name).'@example.com']);
    }

    protected function guide(string $title, ?int $brand, ?Client $client, ?User $author): Guide
    {
        return Guide::query()->create(['title' => $title, 'brand_id' => $brand, 'client_id' => $client?->getKey(), 'author_id' => $author?->getKey()]);
    }

    protected function ticket(string $title, ?Client $client, User $author): Ticket
    {
        return Ticket::query()->create(['title' => $title, 'client_id' => $client?->getKey(), 'author_id' => $author->getKey()]);
    }
}
