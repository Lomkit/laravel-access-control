<?php

namespace Lomkit\Access\Integrations\RestApi;

use Lomkit\Access\Access;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;
use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Extensions\Kinds\ContributesCacheContext;
use Lomkit\Rest\Extensions\Kinds\PreparesRowChecks;
use Lomkit\Rest\Extensions\Kinds\ProvidesPerimeter;
use Lomkit\Rest\Extensions\Kinds\VersionsRowChecks;

class AccessControlExtension extends Extension implements ChecksResources, ContributesCacheContext, PreparesRowChecks, ProvidesPerimeter, VersionsRowChecks
{
    #[\Override]
    public static function name(): string
    {
        return 'access_control';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return Access::hasControl($resource::modelClass());
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        Access::controlFor($context->resource::modelClass())->restrict($context->query, RestReader::of($context->user, static::class));
    }

    #[\Override]
    public function prepareRowChecks(PendingChecks $checks): void
    {
        (new RowCheckPreparation(static::class))->prepare($checks);
    }

    #[\Override]
    public function versionRowChecks(RowVersions $versions): void
    {
        (new ParentVersions)->add($versions);
    }

    #[\Override]
    public function contributeCacheContext(CacheContributionContext $context): void
    {
        (new DecisionCacheContext)->contribute($context);
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        (new ResourceChecks)->check($context);
    }
}
