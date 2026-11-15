<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UnsupportedRestSearchException extends LogicException
{
    public static function notControlledSearchable(string $resource, string $model, string $extension): self
    {
        return new self(sprintf(
            'The [%s] rest-api extension cannot restrict the engine search of [%s]: its controlled [%s] model does not use the ControlledSearchable trait, so its documents hold no access keys. Use ControlledSearchable on the model and reindex it, or search it with the Scout database engine.',
            $extension,
            $resource,
            $model,
        ));
    }

    public static function untrustedEngine(string $resource, mixed $engine, string $extension): self
    {
        $class = get_debug_type($engine);

        return new self(sprintf(
            'The [%1$s] rest-api extension cannot restrict the engine search of [%2$s] on the [%3$s] engine: the package only knows that the Algolia, Meilisearch, Typesense and Turbopuffer engines match a list of access keys. If [%3$s] does, call Access::trustSearchEngine(%3$s::class) in a service provider.',
            $extension,
            $resource,
            $class,
        ));
    }

    public static function withoutStringLists(string $resource, mixed $engine, string $extension): self
    {
        return new self(sprintf(
            'The [%s] rest-api extension cannot restrict the engine search of [%s]: the rest-api profile of the [%s] engine does not match lists of strings, so it cannot compare the access keys. Search the resource on an engine whose profile implements Lomkit\Rest\Text\Engine\Profiles\MatchesStringLists, or with the Scout database engine.',
            $extension,
            $resource,
            get_debug_type($engine),
        ));
    }
}
