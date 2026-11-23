<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Http\Requests\Concerns\ValidatesListingCriteria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavedSearchStoreRequest extends FormRequest
{
    use ValidatesListingCriteria;

    /**
     * A saved search belongs to someone, so there has to be a user.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->criteriaRules();
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // A search with nothing to narrow it would alert its owner
                // about every listing that goes live.
                if (array_filter($this->criteria(), fn (mixed $value) => $value !== null) === []) {
                    $validator->errors()->add('criteria', 'Choose at least one filter before saving a search.');
                }
            },
        ];
    }

    /**
     * The criteria as they should be stored. A blank is null, and so is a
     * minimum of zero: "at least 0 bedrooms" filters nothing, and keeping it
     * would make two identical searches look different.
     *
     * @return array{property_type: PropertyType|null, min_price: int|null, max_price: int|null, min_bedrooms: int|null, max_bedrooms: int|null, min_bathrooms: int|null, region: string|null}
     */
    public function criteria(): array
    {
        return [
            'property_type' => $this->enum('property_type', PropertyType::class),
            'min_price' => $this->integer('min_price') ?: null,
            'max_price' => $this->filled('max_price') ? $this->integer('max_price') : null,
            'min_bedrooms' => $this->integer('min_bedrooms') ?: null,
            'max_bedrooms' => $this->filled('max_bedrooms') ? $this->integer('max_bedrooms') : null,
            'min_bathrooms' => $this->integer('min_bathrooms') ?: null,
            'region' => $this->filled('region') ? $this->string('region')->toString() : null,
        ];
    }
}
