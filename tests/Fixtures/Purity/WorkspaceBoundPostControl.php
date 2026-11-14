<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class WorkspaceBoundPostControl extends PostControl
{
    #[\Override]
    protected function boundary(): Boundary
    {
        $signedIn = Auth::user();
        $workspace = $signedIn instanceof User ? $signedIn->current_workspace_id : null;

        return Boundary::where('workspace_id', fn (User $user): mixed => $workspace);
    }
}
