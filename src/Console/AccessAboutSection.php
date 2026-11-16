<?php

namespace Lomkit\Access\Console;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Lomkit\Access\Console\Check\Inventory\DiscoveryInventory;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Policies\AutomaticPolicies;
use Lomkit\Rest\Facades\Rest;
use Throwable;

/** @internal */
final readonly class AccessAboutSection
{
    private const string PACKAGE = 'lomkit/laravel-access-control';

    private const string NONE = 'none';

    public function __construct(
        private Application $app,
        private ControlDiscovery $discovery,
        private DiscoveryCache $cache,
    ) {}

    /** @return array<string, string> */
    public function __invoke(): array
    {
        return [
            'Version' => $this->row($this->version(...)),
            'Controls' => $this->row($this->controls(...), 'discovery failed: '),
            'Discovery paths' => $this->row($this->paths(...)),
            'Cache' => $this->row(fn (): string => $this->cache->exists() ? 'cached' : 'not cached'),
            'Automatic policies' => $this->row($this->policies(...)),
            'Scout' => $this->row($this->scout(...)),
            'laravel-rest-api' => $this->row($this->restApi(...)),
        ];
    }

    /** @param  Closure(): string  $row */
    private function row(Closure $row, string $failure = 'unavailable: '): string
    {
        try {
            $value = $row();
        } catch (Throwable $exception) {
            return $failure.Str::before($exception->getMessage(), "\n");
        }

        return $value === '' ? self::NONE : $value;
    }

    private function version(): string
    {
        return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? self::NONE;
    }

    private function controls(): string
    {
        return (string) count($this->cache->exists() ? $this->cache->read()->toArray() : $this->discovery->controls());
    }

    private function paths(): string
    {
        $inventory = DiscoveryInventory::of($this->app, $this->discovery, $this->cache, [], 0, null);

        return implode(', ', $inventory->paths);
    }

    private function policies(): string
    {
        return AutomaticPolicies::registeredIn($this->app->make(GateContract::class)) ? 'registered' : 'not registered';
    }

    private function scout(): string
    {
        $driver = $this->app->make('config')->get('scout.driver');

        return trait_exists(Searchable::class) ? 'installed, '.(is_string($driver) ? $driver : self::NONE).' driver' : 'not installed';
    }

    private function restApi(): string
    {
        if (! class_exists(Rest::class)) {
            return 'not installed';
        }

        $disabled = $this->app->make('config')->get('rest.disabled_extensions');

        return is_array($disabled) && in_array(AccessControlExtension::class, $disabled, true)
            ? 'installed, AccessControlExtension listed in rest.disabled_extensions'
            : 'installed, AccessControlExtension enabled';
    }
}
