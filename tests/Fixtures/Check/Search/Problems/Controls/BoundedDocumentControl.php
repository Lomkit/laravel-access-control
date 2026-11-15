<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls;

use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Search\AuthorKeys;
use Lomkit\Access\Tests\Fixtures\Check\Search\HiddenBoundary;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\BoundedDocument;

class BoundedDocumentControl extends Control
{
    protected string $model = BoundedDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return AuthorKeys::perimeters();
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return new HiddenBoundary;
    }
}
