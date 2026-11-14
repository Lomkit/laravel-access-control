<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder;

final class EmptySearchPage
{
    public static function callback(): Closure
    {
        return static function (mixed $search): mixed {
            if ($search instanceof Builder) {
                return $search->whereRaw('0=1');
            }

            return [
                'hits' => [],
                'nbHits' => 0,
                'totalHits' => 0,
                'estimatedTotalHits' => 0,
                'found' => 0,
                'rows' => [],
                'total' => 0,
            ];
        };
    }
}
