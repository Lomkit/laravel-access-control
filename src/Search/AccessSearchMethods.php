<?php

namespace Lomkit\Access\Search;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\AdminSearchTokenKeyException;
use Lomkit\Access\Exceptions\AmbiguousSearchIndexException;
use Lomkit\Access\Exceptions\ConflictingSearchTokensException;
use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\InvalidSearchEngineException;
use Lomkit\Access\Exceptions\InvalidSearchRowException;
use Lomkit\Access\Exceptions\MismatchedSearchTokenKeyException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\MissingSearchClientException;
use Lomkit\Access\Exceptions\PastSearchTokenExpiryException;
use Lomkit\Access\Exceptions\PerimeterWithoutSearchKeysException;
use Lomkit\Access\Exceptions\ProxiedSearchTokenException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Exceptions\UndeclaredSearchTokensException;
use Lomkit\Access\Exceptions\UnguardableSearchEngineException;
use Lomkit\Access\Exceptions\UnknownSearchIndexException;
use Lomkit\Access\Exceptions\UnsearchableAbilityException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedSearchTokenEngineException;
use Lomkit\Access\Search\Guard\SearchGuards;
use Lomkit\Access\Search\Index\IndexKeys;
use Lomkit\Access\Search\Tokens\SearchToken;
use Lomkit\Access\Search\Tokens\SearchTokenDeclaration;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Search\Tokens\SearchTokens;

trait AccessSearchMethods
{
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

    /** @throws ConflictingSearchTokensException When the application already declared the other way. */
    public static function searchTokens(bool $direct): void
    {
        Container::getInstance()
            ->make(SearchTokenDeclaration::class)
            ->declare($direct);
    }

    /**
     * @param  class-string<Model>  $model
     *
     * @throws UndeclaredSearchTokensException When the application did not declare how browsers reach the engine.
     * @throws ProxiedSearchTokenException When the application declared that browsers go through its own route.
     * @throws PastSearchTokenExpiryException When the expiry is not in the future.
     * @throws MissingControlledSearchableException When the model does not use the ControlledSearchable trait.
     * @throws UnsupportedSearchTokenEngineException When the engine of the model has no browser search token.
     * @throws MismatchedSearchTokenKeyException When the key belongs to another engine.
     * @throws AdminSearchTokenKeyException When the key is the admin key that Scout uses.
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws MissingSearchClientException When the client library of the engine is not installed.
     * @throws UnsearchableAbilityException When an ability is given after the expiry.
     */
    public static function searchToken(?Model $reader, string $model, SearchTokenKey $key, DateTimeInterface $expiresAt): SearchToken
    {
        if (func_num_args() > 4) {
            throw UnsearchableAbilityException::token($model);
        }

        return SearchTokens::issue($reader, $model, $key, $expiresAt);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     *
     * @throws InvalidSearchBodyException When the body is not a multi-search body of Typesense, Meilisearch or Algolia.
     * @throws UnknownSearchIndexException When a search names an index that no ControlledSearchable model of a discovered control is searched in.
     * @throws RefusedSearchParameterException When a search sends a parameter that a guarded search refuses.
     * @throws RefusedSearchFilterException When the filter of a search cannot be ANDed with the access filter.
     * @throws AmbiguousSearchIndexException When two models are searched in the same index.
     * @throws UnguardableSearchEngineException When the model of an index searches with an engine that has no guard.
     * @throws MissingControlException When no control is registered for the model of an index.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     */
    public static function guardSearch(array $body, ?Model $reader): array
    {
        return Container::getInstance()
            ->make(SearchGuards::class)
            ->guard($body, $reader);
    }

    /**
     * @param  iterable<Model>  $rows
     * @return array<int|string, array<string, list<string>>>
     *
     * @throws InvalidSearchRowException When a row is not an Eloquent model, is unsaved, or is not of the model of the first row.
     * @throws MissingControlException When no control is registered for the model of the rows.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws PerimeterWithoutSearchKeysException When a perimeter of the control declares no search keys.
     */
    public static function searchKeysOf(iterable $rows): array
    {
        return IndexKeys::documentsOf($rows);
    }

    /**
     * @param  class-string<Model>  $model
     *
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws PerimeterWithoutSearchKeysException When a perimeter of the control declares no search keys.
     * @throws UnsearchableAbilityException When an ability is given after the reader.
     */
    public static function readerKeysFor(string $model, ?Model $reader): ReaderSearchKeys
    {
        if (func_num_args() > 2) {
            throw UnsearchableAbilityException::readerKeys($model);
        }

        return IndexKeys::readerKeysFor($model, $reader);
    }
}
