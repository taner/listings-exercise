<?php

namespace App\Http\Requests;

use App\Enums\AlertFrequency;
use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SavedSearchUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $savedSearch = $this->route('savedSearch');

        return $savedSearch instanceof SavedSearch && $savedSearch->isOwnedBy($this->user());
    }

    /**
     * Someone else's search is reported as missing rather than forbidden.
     * Authorization runs before validation, so no response confirms that the
     * search exists.
     */
    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'alert_frequency' => ['required', new Enum(AlertFrequency::class)],
        ];
    }
}
