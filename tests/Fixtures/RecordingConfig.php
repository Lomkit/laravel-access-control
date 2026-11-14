<?php

namespace Lomkit\Access\Tests\Fixtures;

use Illuminate\Config\Repository;

class RecordingConfig extends Repository
{
    /** @var list<string> */
    public array $readKeys = [];

    #[\Override]
    public function has($key): bool
    {
        $this->record([$key]);

        return parent::has($key);
    }

    #[\Override]
    public function get($key, $default = null): mixed
    {
        $this->record(is_array($key) ? $key : [$key]);

        return parent::get($key, $default);
    }

    /** @param  array<int|string, mixed>  $keys */
    private function record(array $keys): void
    {
        foreach ($keys as $index => $key) {
            $this->readKeys[] = is_string($index) ? $index : (string) $key;
        }
    }
}
