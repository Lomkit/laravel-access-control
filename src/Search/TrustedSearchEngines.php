<?php

namespace Lomkit\Access\Search;

use Laravel\Scout\Engines\AlgoliaEngine;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Engines\MeilisearchEngine;
use Laravel\Scout\Engines\NullEngine;
use Laravel\Scout\Engines\TurbopufferEngine;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Exceptions\InvalidSearchEngineException;

/** @internal */
final class TrustedSearchEngines
{
    /** @var list<class-string> */
    private array $engines = [
        AlgoliaEngine::class,
        MeilisearchEngine::class,
        TypesenseEngine::class,
        TurbopufferEngine::class,
        NullEngine::class,
    ];

    /** @throws InvalidSearchEngineException When the class is not a Scout engine. */
    public function trust(string $engine): void
    {
        if (! is_subclass_of($engine, Engine::class)) {
            throw InvalidSearchEngineException::named($engine);
        }

        if (! in_array($engine, $this->engines, true)) {
            $this->engines[] = $engine;
        }
    }

    public function trusts(mixed $engine): bool
    {
        foreach ($this->engines as $trusted) {
            if ($engine instanceof $trusted) {
                return true;
            }
        }

        return false;
    }
}
