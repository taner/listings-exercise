<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\AlertDigest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An alert is raised by a listing going live, so each test saves its searches
 * and then creates the live listings their criteria accept.
 */
class AlertDigestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The alerts page reads the alerts themselves, so this notification is
     * only ever the email.
     */
    public function test_is_delivered_by_email(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);

        $digest = new AlertDigest($this->alertsOf($user));

        $this->assertSame(['mail'], $digest->via($user));
    }

    public function test_subject_names_the_search_when_only_one_has_new_listings(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['name' => 'Leeds flats', 'max_price' => 300_000]);
        Listing::factory(3)->live()->create(['price' => 250_000]);

        $mail = (new AlertDigest($this->alertsOf($user)))->toMail($user);

        $this->assertSame('3 new listings match Leeds flats', $mail->subject);
    }

    public function test_subject_is_singular_for_one_listing(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['name' => 'Leeds flats', 'max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);

        $mail = (new AlertDigest($this->alertsOf($user)))->toMail($user);

        $this->assertSame('1 new listing matches Leeds flats', $mail->subject);
    }

    /**
     * A listing that matches two searches is still one listing.
     */
    public function test_subject_counts_each_listing_once_across_several_searches(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);
        SavedSearch::factory()->for($user)->create(['max_price' => null, 'min_bedrooms' => 2]);
        Listing::factory()->live()->create(['price' => 250_000, 'bedrooms' => 3]);
        Listing::factory()->live()->create(['price' => 250_000, 'bedrooms' => 1]);

        $mail = (new AlertDigest($this->alertsOf($user)))->toMail($user);

        $this->assertSame('2 new listings match your saved searches', $mail->subject);
    }

    public function test_email_lists_each_listing_under_its_search_with_a_link_to_it(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['name' => 'Leeds flats', 'max_price' => 300_000]);
        $listing = Listing::factory()->live()
            ->for(Branch::factory()->create(['region' => 'Leeds']))
            ->create([
                'address_line_1' => '12 High Street',
                'price' => 245_000,
                'bedrooms' => 2,
                'property_type' => PropertyType::Flat,
            ]);

        $email = (string) (new AlertDigest($this->alertsOf($user)))->toMail($user)->render();

        $this->assertStringContainsString('Leeds flats', $email);
        $this->assertStringContainsString('£245,000', $email);
        $this->assertStringContainsString('12 High Street, Leeds', $email);
        $this->assertStringContainsString('2 bed', $email);
        $this->assertStringContainsString('Flat', $email);
        $this->assertStringContainsString(route('listings.show', $listing), $email);
    }

    /**
     * A busy search must not turn the email into a catalogue. The newest four
     * are shown and the rest are a click away.
     */
    public function test_email_shows_the_newest_four_listings_of_a_search_and_counts_the_rest(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);
        $listings = collect(range(1, 6))->map(function (int $number) {
            $this->travel(1)->minutes();

            return Listing::factory()->live()->create(['price' => 250_000, 'address_line_1' => "{$number} Order Street"]);
        });

        $email = (string) (new AlertDigest($this->alertsOf($user)))->toMail($user)->render();

        foreach ([6, 5, 4, 3] as $shown) {
            $this->assertStringContainsString(route('listings.show', $listings[$shown - 1]), $email);
        }
        foreach ([2, 1] as $hidden) {
            $this->assertStringNotContainsString("{$hidden} Order Street", $email);
        }
        $this->assertStringContainsString('and 2 more', $email);
    }

    /**
     * To the buyer a listing that matches two of their searches is still one
     * listing. It is shown once, under the search they saved first.
     */
    public function test_email_shows_a_listing_once_when_it_matches_several_searches(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['name' => 'Under 300k', 'max_price' => 300_000]);
        SavedSearch::factory()->for($user)->create(['name' => 'Two beds or more', 'max_price' => null, 'min_bedrooms' => 2]);
        $matchesBoth = Listing::factory()->live()->create(['price' => 250_000, 'bedrooms' => 3]);
        $this->travel(1)->minutes();
        $matchesSecondOnly = Listing::factory()->live()->create(['price' => 600_000, 'bedrooms' => 3]);

        $email = (string) (new AlertDigest($this->alertsOf($user)))->toMail($user)->render();

        $linkToBoth = '"'.route('listings.show', $matchesBoth).'"';
        $this->assertSame(1, substr_count($email, $linkToBoth));
        $this->assertSame(1, substr_count($email, '"'.route('listings.show', $matchesSecondOnly).'"'));
        $this->assertGreaterThan(strpos($email, 'Under 300k'), strpos($email, $linkToBoth));
    }

    public function test_email_leaves_out_a_search_whose_listings_are_all_shown_under_another(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['name' => 'Under 300k', 'max_price' => 300_000]);
        SavedSearch::factory()->for($user)->create(['name' => 'Two beds or more', 'max_price' => null, 'min_bedrooms' => 2]);
        Listing::factory()->live()->create(['price' => 250_000, 'bedrooms' => 3]);

        $email = (string) (new AlertDigest($this->alertsOf($user)))->toMail($user)->render();

        $this->assertStringContainsString('Under 300k', $email);
        $this->assertStringNotContainsString('Two beds or more', $email);
    }

    public function test_email_does_not_mention_more_when_a_search_has_four_listings_or_fewer(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);
        Listing::factory(4)->live()->create(['price' => 250_000]);

        $email = (string) (new AlertDigest($this->alertsOf($user)))->toMail($user)->render();

        $this->assertStringNotContainsString(' more', $email);
    }

    public function test_email_links_to_the_alerts_page_and_the_saved_searches_page(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);

        $mail = (new AlertDigest($this->alertsOf($user)))->toMail($user);

        $this->assertSame('View your alerts', $mail->actionText);
        $this->assertSame(route('alerts.index'), $mail->actionUrl);
        $this->assertStringContainsString(route('saved-searches.index'), (string) $mail->render());
    }

    public function test_email_escapes_the_search_name(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['name' => '<script>alert("x")</script>', 'max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);

        $email = (string) (new AlertDigest($this->alertsOf($user)))->toMail($user)->render();

        $this->assertStringContainsString('&lt;script&gt;', $email);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $email);
    }

    /**
     * The buyer's alerts, oldest first, so that showing the newest is plainly
     * the notification's doing and not the order they arrived in.
     *
     * @return Collection<int, Alert>
     */
    private function alertsOf(User $user): Collection
    {
        return $user->alerts()
            ->with(['savedSearch', 'listing'])
            ->oldest('alerts.created_at')
            ->orderBy('alerts.id')
            ->get();
    }
}
