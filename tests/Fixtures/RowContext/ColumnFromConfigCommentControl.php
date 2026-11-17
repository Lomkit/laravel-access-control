<?php

namespace Lomkit\Access\Tests\Fixtures\RowContext;

use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;

class ColumnFromConfigCommentControl extends CommentControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $column = config('blog.row_context_column');

        return [
            ThroughParent::belongsTo('post')->decidedInRowContext(is_string($column) ? $column : 'workspace_id'),
        ];
    }
}
