<?php

namespace Lomkit\Access\Search;

/** @internal */
final class AlgoliaRules
{
    public const string PARAMETER = 'enableRules';

    public const string AB_TESTS = 'enableABTest';

    /** @return array{enableRules: false, enableABTest: false} */
    public static function off(): array
    {
        return [self::PARAMETER => false, self::AB_TESTS => false];
    }
}
