<?php

namespace App\Observers;

use App\Enums\ListingStatus;
use App\Jobs\MatchListingToSavedSearches;
use App\Models\Listing;

/**
 * Starts alert matching the first time a listing goes live.
 *
 * Nothing in the app publishes a listing through one dedicated action, so this
 * watches the model itself: any code path that saves a listing as live is
 * covered. A bulk `update()` on the query builder fires no model events and
 * would be missed.
 */
class ListingObserver
{
    public function created(Listing $listing): void
    {
        if ($listing->status === ListingStatus::Live) {
            MatchListingToSavedSearches::dispatch($listing, now());
        }
    }

    public function updated(Listing $listing): void
    {
        // Only a draft going live is a new listing. One returning from under
        // offer or sold has been live before, and buyers have already been
        // told about it.
        $wentLive = $listing->wasChanged('status')
            && $listing->status === ListingStatus::Live
            && $listing->getOriginal('status') === ListingStatus::Draft;

        if ($wentLive) {
            MatchListingToSavedSearches::dispatch($listing, now());
        }
    }
}
