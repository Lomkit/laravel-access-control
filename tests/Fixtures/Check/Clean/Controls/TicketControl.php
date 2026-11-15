<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Clean\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\Ticket;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\AdminPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\TeamPerimeter;

class TicketControl extends Control
{
    protected string $model = Ticket::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            Levels::of(
                AdminPerimeter::new()
                    ->allowed(fn (Model $user): bool => $user->getAttribute('admin') === true)
                    ->unrestricted(),
                TeamPerimeter::new()
                    ->allowed(fn (Model $user): bool => true)
                    ->should(fn (Model $user, Ticket $ticket): bool => $ticket->getAttribute('team_id') === $user->getAttribute('team_id'))
                    ->query(function (Builder $query, Model $user): void {
                        $query->where('team_id', $user->getAttribute('team_id'));
                    }),
            ),
            OwnPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(function (Builder $query, Model $user): void {
                    $query->where('author_id', $user->getKey());
                }),
        ];
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return Boundary::where('workspace_id', fn (Model $user): mixed => $user->getAttribute('workspace_id'));
    }
}
