<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Laravel\Scout\Builder;

class CallbackBlindEngine extends MemoryEngine
{
    /** @return array<string, mixed> */
    #[\Override]
    public function search(Builder $builder): mixed
    {
        return $this->searchDocuments($builder);
    }
}
