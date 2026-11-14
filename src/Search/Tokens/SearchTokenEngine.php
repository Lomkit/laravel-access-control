<?php

namespace Lomkit\Access\Search\Tokens;

use Illuminate\Support\Facades\Config;

enum SearchTokenEngine
{
    case Meilisearch;
    case Typesense;
    case Algolia;

    public function label(): string
    {
        return $this->name;
    }

    public function isAdminKey(string $key): bool
    {
        $admin = Config::get($this->adminKeyOption());

        return is_string($admin) && hash_equals($admin, $key);
    }

    public function adminKeyOption(): string
    {
        return match ($this) {
            self::Meilisearch => 'scout.meilisearch.key',
            self::Typesense => 'scout.typesense.client-settings.api_key',
            self::Algolia => 'scout.algolia.secret',
        };
    }

    public function keyFactory(): string
    {
        return match ($this) {
            self::Meilisearch => 'SearchTokenKey::meilisearch(uid: ..., key: ...)',
            self::Typesense => 'SearchTokenKey::typesense(key: ...)',
            self::Algolia => 'SearchTokenKey::algolia(key: ...)',
        };
    }
}
