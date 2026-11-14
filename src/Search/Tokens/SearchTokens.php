<?php

namespace Lomkit\Access\Search\Tokens;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\AdminSearchTokenKeyException;
use Lomkit\Access\Exceptions\MismatchedSearchTokenKeyException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\MissingSearchClientException;
use Lomkit\Access\Exceptions\PastSearchTokenExpiryException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedSearchTokenEngineException;
use Lomkit\Access\Search\Tokens\Signers\SearchTokenSigners;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final class SearchTokens
{
    /**
     * @throws PastSearchTokenExpiryException When the expiry is not in the future.
     * @throws MissingControlledSearchableException When the model does not use the ControlledSearchable trait.
     * @throws UnsupportedSearchTokenEngineException When the engine of the model has no browser search token.
     * @throws MismatchedSearchTokenKeyException When the key belongs to another engine.
     * @throws AdminSearchTokenKeyException When the key is the admin key that Scout uses.
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws MissingSearchClientException When the client library of the engine is not installed.
     */
    public static function issue(?Model $reader, string $model, SearchTokenKey $key, DateTimeInterface $expiresAt): SearchToken
    {
        $expiry = DateTimeImmutable::createFromInterface($expiresAt);

        if ($expiry <= Date::now()) {
            throw PastSearchTokenExpiryException::for($model, $expiry);
        }

        $instance = is_subclass_of($model, Model::class) && UsesControlledSearchable::check($model) ? new $model : null;

        if (! $instance instanceof Model || ! method_exists($instance, 'searchableUsing') || ! method_exists($instance, 'searchableAs')) {
            throw MissingControlledSearchableException::forSearchToken($model);
        }

        $signer = SearchTokenSigners::for($instance->searchableUsing(), $instance);

        if ($key->engine() !== $signer->engine()) {
            throw MismatchedSearchTokenKeyException::for($key->engine(), $signer->engine(), $model);
        }

        if ($signer->engine()->isAdminKey($key->key())) {
            throw AdminSearchTokenKeyException::for($signer->engine(), $model);
        }

        $control = Access::controlFor($instance);

        if (! $control->searchRules()->isSearchable()) {
            throw UnsearchableBoundaryException::for($control);
        }

        $filter = $signer->syntax()->render($control->searchFilter($reader));
        $index = $instance->searchableAs();

        if (! is_string($index)) {
            throw MissingControlledSearchableException::forSearchToken($model);
        }

        return new SearchToken($signer->sign($key, $index, $filter, $expiry), $index, $expiry, $filter);
    }
}
