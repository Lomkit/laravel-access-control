<?php

namespace Lomkit\Access\Discovery;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Lomkit\Access\Registry\ControlMap;
use Lomkit\Access\Registry\WatchMap;

final readonly class DiscoveryCache
{
    public function __construct(
        private Application $app,
        private Filesystem $files,
    ) {}

    public function exists(): bool
    {
        return $this->files->isFile($this->path());
    }

    public function read(): ControlMap
    {
        $cached = $this->files->getRequire($this->path());
        $controls = [];

        foreach (is_array($cached) ? $cached : [] as $model => $control) {
            if (is_string($model) && is_string($control)) {
                $controls[$model] = $control;
            }
        }

        return new ControlMap($controls);
    }

    public function write(ControlMap $map): void
    {
        $this->files->put(
            $this->path(),
            '<?php return '.var_export($map->toArray(), true).';'.PHP_EOL,
        );
    }

    public function readWatched(): ?WatchMap
    {
        if (! $this->files->isFile($this->watchedPath())) {
            return null;
        }

        return WatchMap::fromCache($this->files->getRequire($this->watchedPath()));
    }

    public function writeWatched(WatchMap $map): void
    {
        $this->files->put(
            $this->watchedPath(),
            '<?php return '.var_export($map->toArray(), true).';'.PHP_EOL,
        );
    }

    public function forget(): void
    {
        $this->files->delete([$this->path(), $this->watchedPath()]);
    }

    private function path(): string
    {
        return $this->app->bootstrapPath('cache/access-controls.php');
    }

    private function watchedPath(): string
    {
        return $this->app->bootstrapPath('cache/access-reindex.php');
    }
}
