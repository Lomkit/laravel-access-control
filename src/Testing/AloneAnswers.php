<?php

namespace Lomkit\Access\Testing;

/** @internal */
final class AloneAnswers
{
    /** @var array<string, string> */
    private array $answers = [];

    /** @param  array<string, string>  $answers */
    public function keep(array $answers): void
    {
        $this->answers = $answers;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->answers;
    }
}
