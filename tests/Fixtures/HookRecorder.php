<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\AccessContext;

final class HookRecorder
{
    /** @var list<array{string, AccessContext}> */
    public array $calls = [];

    public function record(string $hook, AccessContext $context): void
    {
        $this->calls[] = [$hook, $context];
    }
}
