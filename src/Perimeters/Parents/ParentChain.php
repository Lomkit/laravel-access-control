<?php

namespace Lomkit\Access\Perimeters\Parents;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\ParentCycleException;

/** @internal */
#[Singleton]
final class ParentChain
{
    /** @var list<Control> */
    private array $declaring = [];

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $declare
     * @return TResult
     *
     * @throws ParentCycleException When the control is already being declared further up the chain of parents.
     */
    public function declaring(Control $control, Closure $declare): mixed
    {
        foreach ($this->declaring as $position => $declared) {
            if ($declared::class === $control::class) {
                throw ParentCycleException::through([...array_slice($this->declaring, $position), $control]);
            }
        }

        $this->declaring[] = $control;

        try {
            return $declare();
        } finally {
            array_pop($this->declaring);
        }
    }
}
