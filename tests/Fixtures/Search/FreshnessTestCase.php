<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Lomkit\Access\Access;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;

abstract class FreshnessTestCase extends SearchTestCase
{
    protected Client $acme;

    protected Client $globex;

    protected User $ada;

    protected User $grace;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::delete([$this->app->bootstrapPath('cache/access-controls.php'), $this->app->bootstrapPath('cache/access-reindex.php')]);
        Access::discoverControlsIn([__DIR__]);

        $this->acme = Client::factory()->createOne(['name' => 'Acme']);
        $this->globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = $this->user('Ada', $this->acme);
        $this->grace = $this->user('Grace', $this->globex);
    }

    protected function bindFolderKeys(): ConfiguredArticleControl
    {
        return ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereRelation('folder', 'client_id', $user->client_id);
                })
                ->searchKeys(
                    name: 'client',
                    row: fn (Article $article): mixed => $article->folder?->client_id,
                    reader: fn (User $user): mixed => $user->client_id,
                    load: ['folder:id,client_id'],
                )
                ->reindexWhen(Folder::class, changed: ['client_id'], rows: fn (Folder $folder) => $folder->articles()),
        );
    }

    protected function folder(Client $client, string $name = 'Handbook'): Folder
    {
        return Folder::query()->create(['client_id' => $client->getKey(), 'name' => $name]);
    }

    protected function article(Folder $folder, string $title = 'Scout primer'): Article
    {
        return Article::factory()->createOne(['folder_id' => $folder->getKey(), 'title' => $title]);
    }

    /** @return list<string> */
    protected function accessOf(Article $article): array
    {
        $access = $this->document($article)['_access'];

        return is_array($access) ? array_values(array_filter($access, is_string(...))) : [];
    }

    protected function forgetRegistry(): void
    {
        $this->app->forgetInstance(ControlRegistry::class);
        Access::clearResolvedInstance(ControlRegistry::class);
    }
}
