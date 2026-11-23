<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saved_search_label' => $this->savedSearch->label(),
            'created_at' => $this->created_at->toIso8601String(),
            // Only live listings are public, so one that has since left the
            // market is withheld here just as its own page is.
            'listing' => $this->listing->status === ListingStatus::Live
                ? new ListingResource($this->listing)
                : null,
        ];
    }
}
