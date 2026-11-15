<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Search\AuthorKeys;
use Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models\TaggedDocument;

class TaggedDocumentControl extends Control
{
    protected string $model = TaggedDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return AuthorKeys::perimeters();
    }
}
