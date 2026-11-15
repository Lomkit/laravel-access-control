<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Search\AuthorKeys;
use Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models\Document;

class DocumentControl extends Control
{
    protected string $model = Document::class;

    #[\Override]
    protected function perimeters(): array
    {
        return AuthorKeys::perimeters();
    }
}
