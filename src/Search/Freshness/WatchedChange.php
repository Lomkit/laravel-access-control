<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class WatchedChange
{
    private function __construct(
        private WatchedEvent $event,
        private int|string|null $key,
        private string $carried,
    ) {}

    public static function of(WatchedEvent $event, ReindexRule $reindex, Model $instance): self
    {
        $key = $instance->getKey();
        $carried = match ($event) {
            WatchedEvent::Updated => $reindex->previousValues($instance),
            WatchedEvent::Deleted => array_diff_key($instance->getAttributes(), array_flip($instance->getHidden())),
            WatchedEvent::Created, WatchedEvent::Restored => [],
        };

        return new self($event, is_int($key) || is_string($key) ? $key : null, base64_encode(serialize($carried)));
    }

    /** @return list<Model> */
    public function instancesOf(Model $prototype): array
    {
        if ($this->event === WatchedEvent::Deleted) {
            return [$prototype->newFromBuilder($this->carried())];
        }

        $fresh = $this->key === null ? null : $prototype
            ->newQueryWithoutScopes()
            ->whereKey($this->key)
            ->first();

        if (! $fresh instanceof Model) {
            return [];
        }

        if ($this->event !== WatchedEvent::Updated) {
            return [$fresh];
        }

        return [$fresh, $prototype->newFromBuilder([...$fresh->getAttributes(), ...$this->carried()])];
    }

    /** @return array<string, mixed> */
    public function carried(): array
    {
        $carried = unserialize((string) base64_decode($this->carried, true), ['allowed_classes' => false]);

        return is_array($carried) ? array_filter($carried, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }

    public function identity(): string
    {
        return $this->event->value."\0".$this->key."\0".$this->carried;
    }
}
