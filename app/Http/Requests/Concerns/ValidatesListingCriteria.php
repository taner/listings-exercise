<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PropertyType;
use App\Models\Branch;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * The criteria a listings search can carry. The listings page filters by them
 * and a saved search stores them, so both validate them in exactly the same
 * way: anything the page accepts can be saved, and nothing else can.
 */
trait ValidatesListingCriteria
{
    /**
     * @return array<string, mixed>
     */
    protected function criteriaRules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'min_price' => [
                'nullable',
                'integer',
                'min:0',
                // Only comparable when both ends of the range were given.
                Rule::when($this->filled('max_price'), ['lte:max_price']),
            ],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'min_bedrooms' => [
                'nullable',
                'integer',
                'min:0',
                'max:20',
                Rule::when($this->filled('max_bedrooms'), ['lte:max_bedrooms']),
            ],
            'max_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'min_bathrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            // A region no branch covers can never match a listing, so it is a
            // mistake worth reporting rather than an empty result.
            'region' => ['nullable', 'string', 'max:100', Rule::exists(Branch::class, 'region')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'min_price.lte' => 'The minimum price cannot be more than the maximum price.',
            'min_bedrooms.lte' => 'The minimum bedrooms cannot be more than the maximum bedrooms.',
            'region.exists' => 'We have no branches in that area.',
        ];
    }
}
