<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Discovery\ClassFiles;
use ReflectionClass;

/** @internal */
final class ModelScan
{
    /**
     * @param  list<string>  $paths
     * @return list<class-string<Model>>
     */
    public static function in(array $paths): array
    {
        $models = [];

        foreach (ClassFiles::in($paths) as $class) {
            if (is_subclass_of($class, Model::class) && (new ReflectionClass($class))->isInstantiable()) {
                $models[] = $class;
            }
        }

        return $models;
    }
}
