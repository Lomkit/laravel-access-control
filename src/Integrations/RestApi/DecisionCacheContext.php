<?php

namespace Lomkit\Access\Integrations\RestApi;

use Illuminate\Container\Container;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\DecisionContextResolver;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;

/** @internal */
final readonly class DecisionCacheContext
{
    private const string CONTEXT_KEY = 'decision_context';

    /**
     * @throws InvalidDecisionContextException When the decision context resolver returns an invalid context.
     * @throws MissingControlException When no control is registered for the model of the resource.
     * @throws InvalidAbilityException When the control cannot map a cached ability.
     */
    public function contribute(CacheContributionContext $context): void
    {
        $decision = Container::getInstance()
            ->make(DecisionContextResolver::class)
            ->current();

        $context->put(self::CONTEXT_KEY, hash('sha256', serialize($decision->values())));

        $control = Access::controlFor($context->resource::modelClass());
        $perimeters = $control->declaration()->perimeters();

        foreach (array_keys($context->resource->authorizationCache()) as $ability) {
            if ($perimeters->inheritsFromParent($control->abilityFor((string) $ability))) {
                $context->forget((string) $ability);
            }
        }
    }
}
