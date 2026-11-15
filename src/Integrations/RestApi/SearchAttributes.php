<?php

namespace Lomkit\Access\Integrations\RestApi;

use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\UsesControlledSearchable;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\StringListAttribute;

/** @internal */
final readonly class SearchAttributes
{
    /**
     * @param  class-string<Resource>  $resource
     * @return list<StringListAttribute>
     */
    public function of(string $resource): array
    {
        if (! UsesControlledSearchable::check($resource::modelClass())) {
            return [];
        }

        return array_map(fn (SearchField $field): StringListAttribute => StringListAttribute::make($field->value), SearchField::cases());
    }
}
