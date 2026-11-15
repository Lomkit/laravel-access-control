<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

class MemoryProfileExtension extends Extension implements ProvidesEngineProfiles
{
    public static bool $matchesStringLists = true;

    #[\Override]
    public static function name(): string
    {
        return 'memory_profile';
    }

    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        if (! $context->engine instanceof MemoryEngine) {
            return null;
        }

        return self::$matchesStringLists ? new ListMatchingMemoryEngineProfile($context->engine) : new MemoryEngineProfile($context->engine);
    }
}
