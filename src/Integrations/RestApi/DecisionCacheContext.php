<?php

namespace Lomkit\Access\Integrations\RestApi;

use Illuminate\Container\Container;
use Lomkit\Access\Decisions\DecisionContextResolver;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;

/** @internal */
final readonly class DecisionCacheContext
{
    private const string CONTEXT_KEY = 'decision_context';

    /** @throws InvalidDecisionContextException When the decision context resolver returns an invalid context. */
    public function contribute(CacheContributionContext $context): void
    {
        $decision = Container::getInstance()
            ->make(DecisionContextResolver::class)
            ->current();

        $context->put(self::CONTEXT_KEY, hash('sha256', serialize($decision->values())));
    }
}
