<?php

namespace Lomkit\Access\Discovery;

/** @internal */
final readonly class DiscoveryPaths
{
    /** @param  list<string>  $paths */
    private function __construct(private array $paths) {}

    public static function of(string $path): self
    {
        return (new self([]))->with([$path]);
    }

    /** @param  list<string>  $paths */
    public function with(array $paths): self
    {
        $merged = [...$this->paths, ...array_map(self::normalized(...), $paths)];

        return new self(array_values(array_unique($merged)));
    }

    /** @return list<string> */
    public function toArray(): array
    {
        return $this->paths;
    }

    private static function normalized(string $path): string
    {
        $trimmed = rtrim($path, '/'.DIRECTORY_SEPARATOR);

        return $trimmed === '' ? $path : $trimmed;
    }
}
