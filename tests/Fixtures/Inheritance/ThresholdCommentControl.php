<?php

namespace Lomkit\Access\Tests\Fixtures\Inheritance;

use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;

class ThresholdCommentControl extends CommentControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $threshold = config('blog.comment_threshold');

        return [
            ThroughParent::belongsTo('post')->allowed(fn (User $user, Ability $ability): bool => $threshold > 0 && $user->can("{$ability} comments")),
        ];
    }
}
