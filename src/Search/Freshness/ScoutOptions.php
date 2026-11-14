<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Support\Facades\Config;

/** @internal */
final class ScoutOptions
{
    private const int CHUNK = 500;

    public static function queued(): bool
    {
        return (bool) Config::get('scout.queue', false);
    }

    public static function afterCommit(): bool
    {
        return (bool) Config::get('scout.after_commit', false);
    }

    public static function keepsSoftDeleted(): bool
    {
        return (bool) Config::get('scout.soft_delete', false);
    }

    public static function chunk(): int
    {
        $chunk = Config::get('scout.chunk.searchable');

        return is_numeric($chunk) && (int) $chunk > 0 ? (int) $chunk : self::CHUNK;
    }
}
