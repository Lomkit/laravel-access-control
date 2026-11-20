<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UnsearchableAbilityException extends LogicException
{
    private const string FIX = 'A search finds the rows the reader may view, since the search keys of a reader do not depend on the ability: search, then decide the other ability on the results with reachableKeys() or Access::preload().';

    public static function search(string $model, string $method): self
    {
        return new self(sprintf(
            'A controlled search of the [%s] model received an argument after the reader of %s(), such as an ability. %s',
            $model,
            $method,
            self::FIX,
        ));
    }

    public static function token(string $model): self
    {
        return new self(sprintf(
            'Access::searchToken() for the [%s] model received an argument after the expiry, such as an ability. %s',
            $model,
            self::FIX,
        ));
    }

    public static function indexReaderKeys(string $model): self
    {
        return new self(sprintf(
            'Access::indexReaderKeys() for the [%s] model received an argument after the model, such as an ability. %s',
            $model,
            self::FIX,
        ));
    }

    public static function indexAgreement(string $control): self
    {
        return new self(sprintf(
            'assertIndexKeysAgree() for the [%s] control received an argument after the readers, such as an ability. It compares the keys of an index with the view rows of each reader. %s',
            $control,
            self::FIX,
        ));
    }

    public static function agreement(string $control): self
    {
        return new self(sprintf(
            'assertSearchAgrees() for the [%s] control received an argument after the readers, such as an ability. It compares the search with the view rows of each reader. %s',
            $control,
            self::FIX,
        ));
    }
}
