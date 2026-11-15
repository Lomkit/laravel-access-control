<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Console\Check\ControlledModels;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Problems\ControlFailure;
use Lomkit\Access\Console\Check\Problems\DuplicateControl;
use Lomkit\Access\Console\Check\Problems\InvalidControlledBy;
use Lomkit\Access\Console\Check\Problems\StaleControlCache;
use Lomkit\Access\Console\Check\Problems\UncontrolledModel;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\InvalidControlledByException;
use Lomkit\Access\Exceptions\InvalidControlModelException;
use Lomkit\Access\Registry\DeclaredControl;
use Lomkit\Access\Registry\DeclaredModel;

/** @internal */
final readonly class DiscoveryCheck
{
    public function __construct(
        private ControlDiscovery $discovery,
        private DiscoveryCache $cache,
    ) {}

    /** @param  list<class-string<Model>>  $scanned */
    public function run(Findings $findings, array $scanned, string $cachePath): ControlledModels
    {
        $discovered = $this->discovered($findings);
        $controls = $discovered;

        foreach (array_values(array_unique([...$scanned, ...array_keys($discovered)])) as $model) {
            try {
                $declared = DeclaredControl::of($model);
            } catch (InvalidControlledByException|InvalidControlModelException $exception) {
                $findings->problem(new InvalidControlledBy($model, $exception->getMessage()));

                continue;
            }

            if ($declared !== null && isset($controls[$model]) && $controls[$model] !== $declared) {
                $findings->problem(new DuplicateControl($model, $declared, $controls[$model]));
            } elseif ($declared !== null) {
                $controls[$model] = $declared;
            } elseif (! isset($controls[$model]) && in_array(HasControl::class, class_uses_recursive($model), true)) {
                $findings->problem(new UncontrolledModel($model));
            }
        }

        if ($this->cache->exists() && $this->sorted($this->cache->read()->toArray()) !== $this->sorted($discovered)) {
            $findings->problem(new StaleControlCache($cachePath));
        }

        return new ControlledModels($controls);
    }

    /** @return array<class-string<Model>, class-string<Control>> */
    private function discovered(Findings $findings): array
    {
        $controls = [];
        $conflicts = [];

        foreach ($this->discovery->controls() as $control) {
            try {
                $model = DeclaredModel::of($control);
            } catch (InvalidControlModelException $exception) {
                $findings->problem(new ControlFailure($control, $exception->getMessage()));

                continue;
            }

            if (isset($controls[$model])) {
                $findings->problem(new DuplicateControl($model, $controls[$model], $control));
                $conflicts[$model] = true;

                continue;
            }

            $controls[$model] = $control;
        }

        return array_diff_key($controls, $conflicts);
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function sorted(array $map): array
    {
        ksort($map);

        return $map;
    }
}
