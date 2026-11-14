<?php

namespace Lomkit\Access;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Lomkit\Access\Exceptions\AdminSearchTokenKeyException;
use Lomkit\Access\Exceptions\InvalidSearchEngineException;
use Lomkit\Access\Exceptions\MismatchedSearchTokenKeyException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\MissingSearchClientException;
use Lomkit\Access\Exceptions\PastSearchTokenExpiryException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedSearchTokenEngineException;
use Lomkit\Access\Policies\AutomaticPolicies;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\Tokens\SearchToken;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Search\Tokens\SearchTokens;
use Lomkit\Access\Search\TrustedSearchEngines;

/**
 * @method static \Lomkit\Access\Controls\Control controlFor(\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model> $model)
 * @method static bool hasControl(\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model> $model)
 * @method static \Lomkit\Access\Controls\Control control(class-string<\Lomkit\Access\Controls\Control> $control)
 * @method static void discoverControlsIn(list<string> $paths)
 *
 * @see ControlRegistry
 */
final class Access extends Facade
{
    public static function registerPolicies(): void
    {
        Container::getInstance()
            ->make(AutomaticPolicies::class)
            ->register();
    }

    /** @return list<string> */
    public static function searchFilterables(): array
    {
        return SearchField::names();
    }

    /** @throws InvalidSearchEngineException When the class is not a Scout engine. */
    public static function trustSearchEngine(string $engine): void
    {
        Container::getInstance()
            ->make(TrustedSearchEngines::class)
            ->trust($engine);
    }

    /**
     * @param  class-string<Model>  $model
     *
     * @throws PastSearchTokenExpiryException When the expiry is not in the future.
     * @throws MissingControlledSearchableException When the model does not use the ControlledSearchable trait.
     * @throws UnsupportedSearchTokenEngineException When the engine of the model has no browser search token.
     * @throws MismatchedSearchTokenKeyException When the key belongs to another engine.
     * @throws AdminSearchTokenKeyException When the key is the admin key that Scout uses.
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws MissingSearchClientException When the client library of the engine is not installed.
     */
    public static function searchToken(?Model $reader, string $model, SearchTokenKey $key, DateTimeInterface $expiresAt): SearchToken
    {
        return SearchTokens::issue($reader, $model, $key, $expiresAt);
    }

    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return ControlRegistry::class;
    }
}
