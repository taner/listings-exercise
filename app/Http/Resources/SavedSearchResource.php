<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'summary' => $this->summary(),
            // The query string of the listings page showing this search's
            // results.
            'filters' => $this->filters(),
            'alert_frequency' => $this->alert_frequency->value,
            'alert_frequency_label' => $this->alert_frequency->label(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
