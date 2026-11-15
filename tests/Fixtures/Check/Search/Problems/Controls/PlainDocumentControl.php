<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\PlainDocument;

class PlainDocumentControl extends Control
{
    protected string $model = PlainDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [];
    }
}
