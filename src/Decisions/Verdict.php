<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Lang;

/** @internal */
enum Verdict: string
{
    case Granted = 'granted';
    case NoPerimeter = 'no_perimeter';
    case OutsideRows = 'outside_rows';
    case OutsideBoundary = 'outside_boundary';
    case Unsaved = 'unsaved';

    private const string NOT_FOUND = 'not_found';

    public static function notFound(): Response
    {
        return Response::denyAsNotFound(self::message(self::NOT_FOUND), self::NOT_FOUND);
    }

    public function response(): Response
    {
        if ($this === self::Granted) {
            return Response::allow();
        }

        return Response::deny(self::message($this->value), $this->value);
    }

    private static function message(string $code): string
    {
        $key = "access-control::refusals.{$code}";
        $message = Lang::get($key);

        return is_string($message) ? $message : $key;
    }
}
