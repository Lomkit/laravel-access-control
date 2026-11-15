<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Search\AuthorKeys;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\RewrittenDocument;

class RewrittenDocumentControl extends Control
{
    protected string $model = RewrittenDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return AuthorKeys::perimeters();
    }
}
