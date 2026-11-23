<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Enums\ListingStatus;
use App\Jobs\MatchListingToSavedSearches;
use App\Jobs\SendAlertDigest;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MatchListingToSavedSearchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_alert_not_yet_emailed_for_each_search_that_accepts_the_listing(): void
    {
        $accepts = SavedSearch::factory()->create(['max_price' => 300_000]);
        $rejects = SavedSearch::factory()->create(['max_price' => 200_000]);
        $listing = Listing::factory()->live()->createQuietly(['price' => 250_000]);

        (new MatchListingToSavedSearches($listing, now()))->handle();

        $this->assertDatabaseHas('alerts', [
            'saved_search_id' => $accepts->id,
            'listing_id' => $listing->id,
            'emailed_at' => null,
        ]);
        $this->assertDatabaseMissing('alerts', ['saved_search_id' => $rejects->id]);
    }

    /**
     * A queue can deliver a job twice. The second run must not alert the same
     * search about the same listing again.
     */
    public function test_creates_each_alert_once_when_it_runs_twice(): void
    {
        SavedSearch::factory()->create(['max_price' => 300_000]);
        $listing = Listing::factory()->live()->createQuietly(['price' => 250_000]);

        (new MatchListingToSavedSearches($listing, now()))->handle();
        (new MatchListingToSavedSearches($listing, now()))->handle();

        $this->assertDatabaseCount('alerts', 1);
    }

    /**
     * No backfill: a search only hears about listings that go live after it
     * was saved, including one saved while this job was still waiting.
     */
    public function test_ignores_searches_saved_after_the_listing_went_live(): void
    {
        $wentLiveAt = now()->subHour();
        $savedBefore = SavedSearch::factory()->create(['max_price' => 300_000, 'created_at' => $wentLiveAt->copy()->subMinute()]);
        SavedSearch::factory()->create(['max_price' => 300_000, 'created_at' => $wentLiveAt->copy()->addMinute()]);
        $listing = Listing::factory()->live()->createQuietly(['price' => 250_000]);

        (new MatchListingToSavedSearches($listing, $wentLiveAt))->handle();

        $this->assertDatabaseCount('alerts', 1);
        $this->assertDatabaseHas('alerts', ['saved_search_id' => $savedBefore->id]);
    }

    public function test_creates_no_alert_when_the_listing_is_no_longer_live(): void
    {
        SavedSearch::factory()->create(['max_price' => 300_000]);
        $listing = Listing::factory()->live()->createQuietly(['price' => 250_000]);
        $listing->updateQuietly(['status' => ListingStatus::Sold]);

        (new MatchListingToSavedSearches($listing, now()))->handle();

        $this->assertDatabaseEmpty('alerts');
    }

    /**
     * Daily and weekly searches are emailed by the scheduler. An instant one
     * is emailed as soon as it has an alert, and a buyer with two instant
     * searches that both accept the listing still gets a single email.
     */
    public function test_queues_one_email_straight_away_for_each_buyer_with_an_instant_search(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->instant()->create(['max_price' => 300_000]);
        SavedSearch::factory()->for($user)->instant()->create(['max_price' => 400_000]);
        SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        SavedSearch::factory()->weekly()->create(['max_price' => 300_000]);
        $listing = Listing::factory()->live()->createQuietly(['price' => 250_000]);
        Queue::fake([SendAlertDigest::class]);

        (new MatchListingToSavedSearches($listing, now()))->handle();

        Queue::assertPushed(SendAlertDigest::class, 1);
        Queue::assertPushed(
            SendAlertDigest::class,
            fn (SendAlertDigest $job) => $job->user->is($user)
                && $job->frequencies === [AlertFrequency::Instant],
        );
    }

    /**
     * The whole path, with nothing faked: a listing going live alerts the
     * searches that accept it.
     */
    public function test_a_listing_going_live_alerts_the_searches_that_accept_it(): void
    {
        $search = SavedSearch::factory()->create(['max_price' => 300_000]);
        $listing = Listing::factory()->draft()->create(['price' => 250_000]);

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        $this->assertDatabaseHas('alerts', [
            'saved_search_id' => $search->id,
            'listing_id' => $listing->id,
        ]);
    }
}
