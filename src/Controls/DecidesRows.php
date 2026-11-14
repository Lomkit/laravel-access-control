<?php

namespace Lomkit\Access\Controls;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Decisions\StoredRow;
use Lomkit\Access\Decisions\Verdict;
use Lomkit\Access\Perimeters\ReachedPerimeters;

/** @phpstan-require-extends Control */
trait DecidesRows
{
    final public function decide(Model $reader, Ability $ability, Model|Draft|null $subject = null): Response
    {
        $verdict = $this->verdict($reader, $ability, $subject);

        if ($verdict === Verdict::OutsideBoundary && $subject instanceof Model && $this->hidesFrom($reader, $ability, $subject)) {
            return Verdict::notFound();
        }

        return $verdict->response();
    }

    final public function applies(Model $reader, Ability $ability, Model|Draft|null $subject = null): bool
    {
        return $this->verdict($reader, $ability, $subject) === Verdict::Granted;
    }

    private function verdict(Model $reader, Ability $ability, Model|Draft|null $subject): Verdict
    {
        if ($subject instanceof Model && ! $subject->exists) {
            return Verdict::Unsaved;
        }

        $context = AccessContext::of($reader, $ability);
        $perimeters = $this->declaration()->reachedBy($context);

        if ($perimeters->isEmpty()) {
            return Verdict::NoPerimeter;
        }

        return match (true) {
            $subject instanceof Draft => $this->draftVerdict($subject->model(), $perimeters, $context),
            $subject instanceof Model => $this->rowVerdict(StoredRow::of($subject), $perimeters, $context),
            default => Verdict::Granted,
        };
    }

    private function rowVerdict(Model $row, ReachedPerimeters $perimeters, AccessContext $context): Verdict
    {
        if (! $this->declaration()->boundary()->contains($row, $context)) {
            return Verdict::OutsideBoundary;
        }

        return $perimeters->includes($row, $context) ? Verdict::Granted : Verdict::OutsideRows;
    }

    private function draftVerdict(Model $draft, ReachedPerimeters $perimeters, AccessContext $context): Verdict
    {
        if (! $this->declaration()->boundary()->containsDraft($draft, $context)) {
            return Verdict::OutsideBoundary;
        }

        return $perimeters->includesDraft($draft, $context) ? Verdict::Granted : Verdict::OutsideRows;
    }

    private function hidesFrom(Model $reader, Ability $ability, Model $row): bool
    {
        if ($ability->is(Ability::view())) {
            return true;
        }

        $context = AccessContext::of($reader, Ability::view());
        $boundary = $this->declaration()->boundary();

        return ! $boundary->applyAdmits($context) || ! $boundary->contains(StoredRow::of($row), $context);
    }
}
