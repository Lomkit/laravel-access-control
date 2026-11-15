<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

class RestrictionOnlyExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'restriction_only';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return (new AccessControlExtension)->appliesTo($resource);
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        (new AccessControlExtension)->restrict($context);
    }
}
