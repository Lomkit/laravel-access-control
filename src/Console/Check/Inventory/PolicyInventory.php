<?php

namespace Lomkit\Access\Console\Check\Inventory;

use Illuminate\Contracts\Auth\Access\Gate;
use Throwable;

/** @internal */
final readonly class PolicyInventory
{
    private function __construct(
        public ?string $policy,
        public PolicyForm $form,
    ) {}

    public static function of(Gate $gate, string $model): self
    {
        try {
            $policy = $gate->getPolicyFor($model);
        } catch (Throwable) {
            return new self(null, PolicyForm::None);
        }

        $policy = is_object($policy) ? $policy : null;

        return new self($policy === null ? null : $policy::class, PolicyForm::of($policy));
    }

    public function summary(): string
    {
        return $this->policy === null ? $this->form->value : "{$this->policy}, {$this->form->value}";
    }

    /** @return array{policy: string|null, form: string} */
    public function toArray(): array
    {
        return ['policy' => $this->policy, 'form' => $this->form->value];
    }
}
