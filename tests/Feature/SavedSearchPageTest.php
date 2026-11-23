<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use App\Jobs\SendAlertDigest;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_saves_the_criteria_for_the_user(): void
    {
        $user = User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);

        $response = $this->post('/saved-searches', [
            'property_type' => 'flat',
            'min_price' => 150_000,
            'max_price' => 300_000,
            'min_bedrooms' => 2,
            'max_bedrooms' => 4,
            'min_bathrooms' => 2,
            'region' => 'Leeds',
        ]);

        $response->assertRedirect('/saved-searches');
        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $user->id,
            'name' => null,
            'property_type' => 'flat',
            'min_price' => 150_000,
            'max_price' => 300_000,
            'min_bedrooms' => 2,
            'max_bedrooms' => 4,
            'min_bathrooms' => 2,
            'region' => 'Leeds',
            'alert_frequency' => 'daily',
        ]);
    }

    /**
     * A second copy of a search would alert the user twice about every
     * listing, so saving it again leads back to the one they already have.
     */
    public function test_store_does_not_duplicate_a_search_the_user_already_has(): void
    {
        User::factory()->create();
        $this->post('/saved-searches', ['property_type' => 'flat', 'max_price' => 300_000]);

        $response = $this->post('/saved-searches', ['property_type' => 'flat', 'max_price' => 300_000]);

        $response->assertRedirect('/saved-searches');
        $this->assertDatabaseCount('saved_searches', 1);
    }

    public function test_store_saves_a_search_that_differs_in_any_one_criterion(): void
    {
        User::factory()->create();
        $this->post('/saved-searches', ['property_type' => 'flat', 'max_price' => 300_000]);

        $this->post('/saved-searches', ['property_type' => 'bungalow', 'max_price' => 300_000]);
        $this->post('/saved-searches', ['max_price' => 300_000]);

        $this->assertDatabaseCount('saved_searches', 3);
    }

    /**
     * The same criteria are a different search when someone else holds them.
     */
    public function test_store_lets_two_users_hold_the_same_search(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for(User::factory())->create(['max_price' => 300_000]);

        $this->post('/saved-searches', ['max_price' => 300_000]);

        $this->assertDatabaseCount('saved_searches', 2);
        $this->assertDatabaseHas('saved_searches', ['user_id' => $user->id, 'max_price' => 300_000]);
    }

    /**
     * "At least 0 bedrooms" and "from £0" filter nothing, so they must not
     * make an otherwise identical search look different.
     */
    public function test_store_ignores_minimums_of_zero(): void
    {
        User::factory()->create();
        $this->post('/saved-searches', ['max_price' => 300_000]);

        $this->post('/saved-searches', ['max_price' => 300_000, 'min_price' => 0, 'min_bedrooms' => 0, 'min_bathrooms' => 0]);

        $this->assertDatabaseCount('saved_searches', 1);
        $this->assertDatabaseHas('saved_searches', ['min_price' => null, 'min_bedrooms' => null, 'min_bathrooms' => null]);
    }

    public function test_store_rejects_a_search_with_no_criteria(): void
    {
        User::factory()->create();

        $response = $this->post('/saved-searches', ['min_price' => 0, 'property_type' => '']);

        $response->assertSessionHasErrors([
            'criteria' => 'Choose at least one filter before saving a search.',
        ]);
        $this->assertDatabaseEmpty('saved_searches');
    }

    public function test_store_rejects_a_min_price_above_the_max_price(): void
    {
        User::factory()->create();

        $response = $this->post('/saved-searches', ['min_price' => 400_000, 'max_price' => 300_000]);

        $response->assertSessionHasErrors([
            'min_price' => 'The minimum price cannot be more than the maximum price.',
        ]);
        $this->assertDatabaseEmpty('saved_searches');
    }

    /**
     * A search for a region no branch covers could never match a listing.
     */
    public function test_store_rejects_min_bedrooms_above_max_bedrooms(): void
    {
        User::factory()->create();

        $response = $this->post('/saved-searches', ['min_bedrooms' => 4, 'max_bedrooms' => 3]);

        $response->assertSessionHasErrors([
            'min_bedrooms' => 'The minimum bedrooms cannot be more than the maximum bedrooms.',
        ]);
        $this->assertDatabaseEmpty('saved_searches');
    }

    public function test_store_rejects_a_region_that_no_branch_covers(): void
    {
        User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);

        $response = $this->post('/saved-searches', ['region' => 'Atlantis']);

        $response->assertSessionHasErrors(['region' => 'We have no branches in that area.']);
        $this->assertDatabaseEmpty('saved_searches');
    }

    public function test_store_is_forbidden_without_a_user(): void
    {
        $response = $this->post('/saved-searches', ['max_price' => 300_000]);

        $response->assertForbidden();
        $this->assertDatabaseEmpty('saved_searches');
    }

    public function test_index_lists_only_the_users_own_searches(): void
    {
        $user = User::factory()->create();
        $mine = SavedSearch::factory()->for($user)->create();
        SavedSearch::factory()->for(User::factory())->create();

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 1)
                ->where('savedSearches.0.id', $mine->id)
            );
    }

    public function test_index_describes_each_search(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()
            ->for($user)
            ->weekly()
            ->create([
                'name' => 'Leeds flats',
                'property_type' => PropertyType::Flat,
                'min_price' => 150_000,
                'max_price' => 300_000,
                'min_bedrooms' => 2,
                'max_bedrooms' => 4,
                'min_bathrooms' => 2,
                'region' => 'Leeds',
            ]);

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('savedSearches.0.name', 'Leeds flats')
                ->where('savedSearches.0.summary', 'Flat · Leeds · £150,000 to £300,000 · 2 to 4 beds · 2+ baths')
                ->where('savedSearches.0.alert_frequency', 'weekly')
                // The query string of the search's "View results" link.
                ->where('savedSearches.0.filters', [
                    'property_type' => 'flat',
                    'min_price' => 150_000,
                    'max_price' => 300_000,
                    'min_bedrooms' => 2,
                    'max_bedrooms' => 4,
                    'min_bathrooms' => 2,
                    'region' => 'Leeds',
                ])
            );
    }

    public function test_index_shows_the_newest_search_first(): void
    {
        $user = User::factory()->create();
        $older = SavedSearch::factory()->for($user)->create(['created_at' => now()->subDay()]);
        $newer = SavedSearch::factory()->for($user)->create(['created_at' => now()]);

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('savedSearches.0.id', $newer->id)
                ->where('savedSearches.1.id', $older->id)
            );
    }

    public function test_index_provides_the_alert_frequency_options(): void
    {
        User::factory()->create();

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alertFrequencies', [
                    ['value' => 'instant', 'label' => 'Instantly'],
                    ['value' => 'daily', 'label' => 'Daily'],
                    ['value' => 'weekly', 'label' => 'Weekly'],
                ])
            );
    }

    public function test_index_is_forbidden_without_a_user(): void
    {
        $this->get('/saved-searches')->assertForbidden();
    }

    public function test_update_changes_the_name_and_alert_frequency(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create();

        $response = $this->patch("/saved-searches/{$search->id}", [
            'name' => 'Leeds flats',
            'alert_frequency' => 'weekly',
        ]);

        $response->assertRedirect('/saved-searches');
        $this->assertDatabaseHas('saved_searches', [
            'id' => $search->id,
            'name' => 'Leeds flats',
            'alert_frequency' => 'weekly',
        ]);
    }

    public function test_update_clears_a_blank_name(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create(['name' => 'Leeds flats']);

        $this->patch("/saved-searches/{$search->id}", ['name' => '   ', 'alert_frequency' => 'daily']);

        $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'name' => null]);
    }

    /**
     * Criteria are fixed once saved, and a search never changes hands.
     */
    public function test_update_changes_nothing_but_the_name_and_alert_frequency(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);

        $this->patch("/saved-searches/{$search->id}", [
            'name' => 'Leeds flats',
            'alert_frequency' => 'daily',
            'max_price' => 1,
            'user_id' => $other->id,
        ]);

        $this->assertDatabaseHas('saved_searches', [
            'id' => $search->id,
            'user_id' => $user->id,
            'max_price' => 300_000,
        ]);
    }

    /**
     * Matches waiting for the next daily or weekly run would otherwise sit
     * there until another listing happened to match.
     */
    public function test_update_sends_what_is_waiting_when_the_frequency_becomes_instant(): void
    {
        Queue::fake([SendAlertDigest::class]);
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->weekly()->create();

        $this->patch("/saved-searches/{$search->id}", ['alert_frequency' => 'instant']);

        Queue::assertPushed(
            SendAlertDigest::class,
            fn (SendAlertDigest $job) => $job->user->is($user)
                && $job->frequencies === [AlertFrequency::Instant],
        );
    }

    #[DataProvider('scheduledFrequencies')]
    public function test_update_sends_nothing_early_when_the_frequency_changes_to_daily_or_weekly(string $frequency): void
    {
        Queue::fake([SendAlertDigest::class]);
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->instant()->create();

        $this->patch("/saved-searches/{$search->id}", ['alert_frequency' => $frequency]);

        Queue::assertNotPushed(SendAlertDigest::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function scheduledFrequencies(): array
    {
        return [
            'daily' => ['daily'],
            'weekly' => ['weekly'],
        ];
    }

    public function test_update_does_not_resend_when_an_instant_search_is_only_renamed(): void
    {
        Queue::fake([SendAlertDigest::class]);
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->instant()->create();

        $this->patch("/saved-searches/{$search->id}", ['name' => 'Renamed', 'alert_frequency' => 'instant']);

        Queue::assertNotPushed(SendAlertDigest::class);
    }

    public function test_update_rejects_an_unknown_alert_frequency(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create();

        $response = $this->patch("/saved-searches/{$search->id}", ['alert_frequency' => 'hourly']);

        $response->assertSessionHasErrors(['alert_frequency' => 'The selected alert frequency is invalid.']);
        $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'alert_frequency' => 'daily']);
    }

    public function test_update_rejects_a_name_longer_than_100_characters(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create();

        $response = $this->patch("/saved-searches/{$search->id}", [
            'name' => str_repeat('a', 101),
            'alert_frequency' => 'daily',
        ]);

        $response->assertSessionHasErrors(['name' => 'The name field must not be greater than 100 characters.']);
        $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'name' => null]);
    }

    /**
     * Not found rather than forbidden, and checked before the input is, so
     * the response gives no sign that the search exists.
     */
    public function test_update_returns_404_for_another_users_search(): void
    {
        User::factory()->create();
        $search = SavedSearch::factory()->for(User::factory())->create();

        $response = $this->patch("/saved-searches/{$search->id}", ['alert_frequency' => 'hourly']);

        $response->assertNotFound();
        $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'alert_frequency' => 'daily']);
    }

    public function test_destroy_deletes_the_users_search(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create();

        $response = $this->delete("/saved-searches/{$search->id}");

        $response->assertRedirect('/saved-searches');
        $this->assertModelMissing($search);
    }

    /**
     * Its alerts are about a search the user no longer wants.
     */
    public function test_destroy_deletes_the_searchs_alerts_with_it(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);

        $this->delete("/saved-searches/{$search->id}");

        $this->assertDatabaseEmpty('alerts');
    }

    public function test_destroy_returns_404_for_another_users_search(): void
    {
        User::factory()->create();
        $search = SavedSearch::factory()->for(User::factory())->create();

        $response = $this->delete("/saved-searches/{$search->id}");

        $response->assertNotFound();
        $this->assertModelExists($search);
    }
}
