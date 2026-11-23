<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Jobs\MatchListingToSavedSearches;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Alerts are about new listings, so matching starts the first time a listing
 * goes live and at no other time.
 */
class ListingObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_is_queued_when_a_listing_is_created_live(): void
    {
        Queue::fake([MatchListingToSavedSearches::class]);

        $listing = Listing::factory()->live()->create();

        Queue::assertPushed(
            MatchListingToSavedSearches::class,
            fn (MatchListingToSavedSearches $job) => $job->listing->is($listing),
        );
    }

    public function test_matching_is_queued_when_a_draft_goes_live(): void
    {
        $listing = Listing::factory()->draft()->create();
        Queue::fake([MatchListingToSavedSearches::class]);

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        Queue::assertPushed(
            MatchListingToSavedSearches::class,
            fn (MatchListingToSavedSearches $job) => $job->listing->is($listing),
        );
    }

    public function test_matching_is_not_queued_when_a_draft_is_created(): void
    {
        Queue::fake([MatchListingToSavedSearches::class]);

        Listing::factory()->draft()->create();

        Queue::assertNotPushed(MatchListingToSavedSearches::class);
    }

    /**
     * A sale falling through puts a listing back on the market, but it is not
     * a new listing.
     */
    #[DataProvider('statesThatHaveAlreadyBeenLive')]
    public function test_matching_is_not_queued_when_a_listing_returns_to_live(string $state): void
    {
        $listing = Listing::factory()->{$state}()->create();
        Queue::fake([MatchListingToSavedSearches::class]);

        $listing->update(['status' => ListingStatus::Live]);

        Queue::assertNotPushed(MatchListingToSavedSearches::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statesThatHaveAlreadyBeenLive(): array
    {
        return [
            'under offer' => ['underOffer'],
            'sold' => ['sold'],
        ];
    }

    public function test_matching_is_not_queued_again_when_a_live_listing_is_edited(): void
    {
        $listing = Listing::factory()->live()->create();
        Queue::fake([MatchListingToSavedSearches::class]);

        $listing->update(['price' => 250_000]);

        Queue::assertNotPushed(MatchListingToSavedSearches::class);
    }

    /**
     * Seeded listings are existing stock, not news.
     */
    public function test_seeding_the_database_queues_no_matching(): void
    {
        Queue::fake([MatchListingToSavedSearches::class]);

        $this->seed();

        Queue::assertNotPushed(MatchListingToSavedSearches::class);
    }
}
