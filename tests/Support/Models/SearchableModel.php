<?php

namespace Lomkit\Access\Tests\Support\Models;

use Laravel\Scout\Searchable;

class SearchableModel extends Model
{
    use Searchable;

    protected $table = 'models';

    /**
     * Get the indexable data array for the model, with the string id Typesense requires.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->getKey(),
            'name' => $this->name,
            'client_id' => $this->client_id,
        ];
    }
}
