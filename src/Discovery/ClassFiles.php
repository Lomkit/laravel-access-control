<?php

namespace Lomkit\Access\Discovery;

use SplFileInfo;
use Symfony\Component\Finder\Finder;

/** @internal */
final class ClassFiles
{
    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function in(array $paths): array
    {
        $classes = [];

        foreach (self::directories($paths) as $directory) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->sortByName()
                ->in($directory);

            foreach ($files as $file) {
                $classes[] = self::classFromFile($file);
            }
        }

        return $classes;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function directories(array $paths): array
    {
        $directories = [];

        foreach ($paths as $path) {
            foreach (glob($path, GLOB_ONLYDIR) ?: [] as $directory) {
                $directories[] = $directory;
            }
        }

        return array_values(array_unique($directories));
    }

    private static function classFromFile(SplFileInfo $file): string
    {
        $contents = (string) file_get_contents($file->getRealPath());

        $namespace = preg_match('/^\s*namespace\s+([^;\s]+)\s*;/m', $contents, $matches) === 1
            ? $matches[1].'\\'
            : '';

        return $namespace.$file->getBasename('.php');
    }
}
