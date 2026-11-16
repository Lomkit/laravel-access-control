<?php

namespace Lomkit\Access\Console\Check\Inventory;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;

/** @internal */
final readonly class DiscoveryInventory
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $models
     */
    private function __construct(
        public array $paths,
        public bool $cached,
        public array $models,
        public int $scanned,
        public ?string $driver,
    ) {}

    /** @param  list<string>  $models */
    public static function of(Application $app, ControlDiscovery $discovery, DiscoveryCache $cache, array $models, int $scanned, ?string $driver): self
    {
        $relative = fn (string $path): string => self::relative($app->basePath(), $path);

        return new self(array_map($relative, $discovery->paths()), $cache->exists(), array_map($relative, $models), $scanned, $driver);
    }

    public static function relative(string $base, string $path): string
    {
        return Str::startsWith($path, $base.DIRECTORY_SEPARATOR) ? Str::after($path, $base.DIRECTORY_SEPARATOR) : $path;
    }

    /** @return array{paths: list<string>, cached: bool, models: list<string>, scanned: int, scout_driver: string|null} */
    public function toArray(): array
    {
        return ['paths' => $this->paths, 'cached' => $this->cached, 'models' => $this->models, 'scanned' => $this->scanned, 'scout_driver' => $this->driver];
    }
}
