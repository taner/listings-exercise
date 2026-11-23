<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * An alert is raised by a listing going live, so that is how these tests make
 * one: a saved search first, then a live listing its criteria accept.
 */
class AlertPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The task's central promise. A daily search is used on purpose: the alert
     * is on the page at once, whatever the frequency holds back the email for.
     */
    public function test_index_shows_an_alert_as_soon_as_a_matching_listing_goes_live(): void
    {
        $this->travelTo('2026-11-14 09:00:00');
        $search = SavedSearch::factory()->for(User::factory())->daily()->create([
            'name' => 'Under 300k',
            'max_price' => 300_000,
        ]);
        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $this->travelTo('2026-11-14 09:30:00');

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 1)
                ->where('alerts.data.0.saved_search_label', 'Under 300k')
                ->where('alerts.data.0.created_at', '2026-11-14T09:30:00+00:00')
                ->where('alerts.data.0.listing.id', $listing->id)
                ->where('alerts.data.0.listing.branch.id', $listing->branch_id)
            );
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());
        $this->assertNull($search->alerts()->sole()->emailed_at);
    }

    public function test_index_lists_the_newest_alert_first(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        SavedSearch::factory()->for(User::factory())->create(['max_price' => 300_000]);
        $this->travelTo('2026-11-10 08:00:00');
        $older = Listing::factory()->live()->create(['price' => 200_000]);
        $this->travelTo('2026-11-14 08:00:00');
        $newer = Listing::factory()->live()->create(['price' => 250_000]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 2)
                ->where('alerts.data.0.listing.id', $newer->id)
                ->where('alerts.data.1.listing.id', $older->id)
            );
    }

    public function test_index_shows_one_alert_for_each_search_a_listing_matches(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['name' => 'Under 300k', 'max_price' => 300_000]);
        SavedSearch::factory()->for($user)->create(['name' => 'Two beds or more', 'max_price' => null, 'min_bedrooms' => 2]);

        Listing::factory()->live()->create(['price' => 250_000, 'bedrooms' => 3]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 2)
                ->where('alerts.data.0.saved_search_label', 'Two beds or more')
                ->where('alerts.data.1.saved_search_label', 'Under 300k')
            );
    }

    /**
     * Only live listings are public, so the alert stays but its listing is
     * held back, just as the listing's own page would be a 404.
     */
    public function test_index_withholds_a_listing_that_is_no_longer_live(): void
    {
        SavedSearch::factory()->for(User::factory())->create(['max_price' => 300_000]);
        $listing = Listing::factory()->live()->create(['price' => 250_000]);
        $listing->update(['status' => ListingStatus::Sold]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.listing', null)
            );
    }

    public function test_index_does_not_show_another_users_alerts(): void
    {
        User::factory()->create();
        SavedSearch::factory()->for(User::factory())->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 0));
    }

    public function test_index_shows_twelve_alerts_to_a_page(): void
    {
        SavedSearch::factory()->for(User::factory())->create(['max_price' => 300_000]);
        Listing::factory(13)->live()->create(['price' => 250_000]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 12)
                ->where('alerts.meta.total', 13)
                ->where('alerts.meta.last_page', 2)
            );
    }

    /**
     * Every alert carries a search, a listing and that listing's branch, so
     * all three have to be loaded for the whole page at once.
     */
    public function test_index_runs_the_same_number_of_queries_however_many_alerts_it_shows(): void
    {
        SavedSearch::factory()->for(User::factory())->create(['max_price' => 300_000]);
        $countQueries = function (): int {
            DB::flushQueryLog();
            $this->get('/alerts')->assertOk();

            return count(DB::getQueryLog());
        };
        DB::enableQueryLog();
        Listing::factory(2)->live()->create(['price' => 250_000]);
        $withTwoAlerts = $countQueries();
        Listing::factory(3)->live()->create(['price' => 250_000]);

        $withFiveAlerts = $countQueries();

        $this->assertSame($withTwoAlerts, $withFiveAlerts);
    }

    public function test_index_is_forbidden_without_a_user(): void
    {
        $this->get('/alerts')->assertForbidden();
    }
}
