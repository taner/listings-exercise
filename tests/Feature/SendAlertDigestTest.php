<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Enums\ListingStatus;
use App\Jobs\SendAlertDigest;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\AlertDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The searches here are daily or weekly, so a listing going live creates an
 * alert and leaves it unemailed for the job under test to pick up.
 */
class SendAlertDigestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One email per buyer, however many of their searches have something new.
     */
    public function test_sends_one_email_covering_every_search_the_buyer_has_alerts_waiting_for(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->daily()->create(['max_price' => 300_000]);
        SavedSearch::factory()->for($user)->daily()->create(['max_price' => null, 'min_bedrooms' => 4]);
        $cheap = Listing::factory()->live()->create(['price' => 250_000, 'bedrooms' => 2]);
        $large = Listing::factory()->live()->create(['price' => 600_000, 'bedrooms' => 5]);

        (new SendAlertDigest($user, [AlertFrequency::Daily]))->handle();

        Notification::assertSentToTimes($user, AlertDigest::class, 1);
        Notification::assertSentTo(
            $user,
            fn (AlertDigest $digest) => $digest->alerts->pluck('listing_id')->sort()->values()->all() === [$cheap->id, $large->id],
        );
    }

    public function test_does_not_email_an_alert_a_second_time(): void
    {
        Notification::fake();
        $search = SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 200_000]);

        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();
        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();

        Notification::assertSentToTimes($search->user, AlertDigest::class, 1);
        $this->assertDatabaseMissing('alerts', ['emailed_at' => null]);
    }

    public function test_sends_nothing_when_the_buyer_has_no_alerts_waiting(): void
    {
        Notification::fake();
        $search = SavedSearch::factory()->daily()->create(['max_price' => 300_000]);

        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();

        Notification::assertNothingSent();
    }

    /**
     * A weekly search keeps its alerts for Monday even when the same buyer's
     * daily searches are being emailed.
     */
    public function test_covers_only_searches_of_the_given_frequencies(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->daily()->create(['max_price' => 300_000]);
        $weekly = SavedSearch::factory()->for($user)->weekly()->create(['max_price' => null, 'min_bedrooms' => 4]);
        $cheap = Listing::factory()->live()->create(['price' => 250_000, 'bedrooms' => 2]);
        Listing::factory()->live()->create(['price' => 600_000, 'bedrooms' => 5]);

        (new SendAlertDigest($user, [AlertFrequency::Daily]))->handle();

        Notification::assertSentTo(
            $user,
            fn (AlertDigest $digest) => $digest->alerts->pluck('listing_id')->all() === [$cheap->id],
        );
        $this->assertNull($weekly->alerts()->sole()->emailed_at);
    }

    public function test_covers_only_the_buyers_own_alerts(): void
    {
        Notification::fake();
        $search = SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        $theirs = SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);

        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();

        Notification::assertSentToTimes($search->user, AlertDigest::class, 1);
        Notification::assertNotSentTo($theirs->user, AlertDigest::class);
        $this->assertNull($theirs->alerts()->sole()->emailed_at);
    }

    /**
     * A buyer should not be told about a property they can no longer buy.
     */
    public function test_leaves_out_a_listing_that_is_no_longer_live(): void
    {
        Notification::fake();
        $search = SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        $sold = Listing::factory()->live()->create(['price' => 200_000]);
        $live = Listing::factory()->live()->create(['price' => 250_000]);
        $sold->update(['status' => ListingStatus::Sold]);

        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();

        Notification::assertSentTo(
            $search->user,
            fn (AlertDigest $digest) => $digest->alerts->pluck('listing_id')->all() === [$live->id],
        );
    }

    public function test_sends_nothing_when_none_of_the_waiting_listings_are_live(): void
    {
        Notification::fake();
        $search = SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        $listing = Listing::factory()->live()->create(['price' => 200_000]);
        $listing->update(['status' => ListingStatus::UnderOffer]);

        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();

        Notification::assertNothingSent();
    }

    /**
     * A sale can fall through. The buyer was never emailed about this listing,
     * so it is still news to them once it is back on the market.
     */
    public function test_sends_a_held_back_listing_once_it_is_live_again(): void
    {
        Notification::fake();
        $search = SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        $listing = Listing::factory()->live()->create(['price' => 200_000]);
        $listing->update(['status' => ListingStatus::UnderOffer]);
        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();
        $listing->update(['status' => ListingStatus::Live]);

        (new SendAlertDigest($search->user, [AlertFrequency::Daily]))->handle();

        Notification::assertSentTo(
            $search->user,
            fn (AlertDigest $digest) => $digest->alerts->pluck('listing_id')->all() === [$listing->id],
        );
    }

    /**
     * The whole path, with nothing faked: a listing goes live and a buyer with
     * two instant searches that accept it gets one email, not two.
     */
    public function test_emails_a_buyer_once_when_a_listing_matches_two_of_their_instant_searches(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->instant()->create(['max_price' => 300_000]);
        SavedSearch::factory()->for($user)->instant()->create(['max_price' => null, 'min_bedrooms' => 2]);
        $listing = Listing::factory()->draft()->create(['price' => 250_000, 'bedrooms' => 3]);

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        $emails = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $emails);
        $this->assertSame('1 new listing matches your saved searches', $emails->first()->getOriginalMessage()->getSubject());
        $this->assertDatabaseMissing('alerts', ['emailed_at' => null]);
    }
}
