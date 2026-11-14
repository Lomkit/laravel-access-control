<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;
use Throwable;

/** @phpstan-consistent-constructor */
class Control
{
    use ControlsQueries;
    use ControlsScoutQueries;

    /** @var class-string<Model> */
    protected string $model;

    /** @var array<Perimeter>|null */
    private ?array $resolvedPerimeters = null;

    /** @param  class-string<Model>  $model */
    public function isModel(string $model): bool
    {
        return $model === $this->model;
    }

    /** @return class-string<Model> */
    public function getModel(): string
    {
        return $this->model;
    }

    /** @return array<Perimeter> */
    protected function perimeters(): array
    {
        return [];
    }

    /** @return array<Perimeter> */
    public function getPerimeters(): array
    {
        return $this->resolvedPerimeters ??= $this->perimeters();
    }

    public function applies(Model $user, string $method, Model $model): bool
    {
        /** @var string $appliesMethod */
        $appliesMethod = config(sprintf('access-control.methods.%s', $method)) ?? $method;

        $perimeters = $this->applicablePerimeters($user, $appliesMethod);

        if ($perimeters === []) {
            return false;
        }

        if (! $model->exists || $this->reachesEveryRow($perimeters)) {
            return true;
        }

        foreach ($perimeters as $perimeter) {
            if ($perimeter->applyShouldCallback($user, $model)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Perimeter> */
    final protected function applicablePerimeters(Model $user, string $method): array
    {
        $applicable = [];

        foreach ($this->getPerimeters() as $perimeter) {
            if (! $perimeter->applyAllowedCallback($user, $method)) {
                continue;
            }

            $applicable[] = $perimeter;

            if (! $perimeter->overlays()) {
                break;
            }
        }

        return $applicable;
    }

    /** @param  list<Perimeter>  $perimeters */
    final protected function reachesEveryRow(array $perimeters): bool
    {
        foreach ($perimeters as $perimeter) {
            if ($perimeter->isUnrestricted()) {
                return true;
            }
        }

        return false;
    }

    final protected function viewMethod(): string
    {
        /** @var string $viewMethod */
        $viewMethod = config('access-control.methods.view');

        return $viewMethod;
    }

    /** @return static */
    public static function new(): self
    {
        return new static;
    }

    protected static function appNamespace(): string
    {
        try {
            return Container::getInstance()
                ->make(Application::class)
                ->getNamespace();
        } catch (Throwable) {
            return 'App\\';
        }
    }
}
