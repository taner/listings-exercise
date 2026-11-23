<?php

namespace App\Jobs;

use App\Enums\AlertFrequency;
use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Creates an alert for every saved search that accepts a listing that has just
 * gone live.
 *
 * The alert is on the alerts page as soon as this has run. The email about it
 * follows on the search's own timing. Queued so that saving a listing never
 * waits on every saved search, and after commit so the listing is in the
 * database before a worker looks for it.
 */
class MatchListingToSavedSearches implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * A listing deleted before the job runs has nothing left to match.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Listing $listing,
        public CarbonInterface $wentLiveAt,
    ) {}

    public function handle(): void
    {
        // It may have sold or been withdrawn while the job was waiting.
        if ($this->listing->status !== ListingStatus::Live) {
            return;
        }

        // Collected across every chunk, so a buyer with two instant searches
        // that both accept the listing is emailed once, not once per search.
        $buyersToEmailNow = [];

        SavedSearch::query()
            ->matching($this->listing)
            // No backfill: a search hears only about listings that go live
            // after it was saved. Comparing with the moment the listing went
            // live, not with now, keeps that true however long the job waits.
            ->where('created_at', '<=', $this->wentLiveAt)
            ->chunkById(500, function (Collection $savedSearches) use (&$buyersToEmailNow): void {
                $now = now();

                // The unique index on search and listing makes a repeat run,
                // from a retry or a double delivery, add nothing.
                Alert::query()->insertOrIgnore(
                    $savedSearches->map(fn (SavedSearch $savedSearch) => [
                        'saved_search_id' => $savedSearch->id,
                        'listing_id' => $this->listing->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );

                foreach ($savedSearches->where('alert_frequency', AlertFrequency::Instant) as $savedSearch) {
                    $buyersToEmailNow[$savedSearch->user_id] = true;
                }
            });

        // Daily and weekly searches are emailed by the scheduler. Instant ones
        // are emailed now. On a repeat run this queues again, and the send
        // finds nothing waiting and does nothing.
        User::query()
            ->whereKey(array_keys($buyersToEmailNow))
            ->each(fn (User $user) => SendAlertDigest::dispatch($user, [AlertFrequency::Instant]));
    }
}
