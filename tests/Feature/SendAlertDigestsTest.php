<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Jobs\SendAlertDigest;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendAlertDigestsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A buyer gets one digest, however many of their searches have something
     * new.
     */
    public function test_queues_one_digest_for_each_buyer_with_alerts_waiting(): void
    {
        $this->travelTo('2026-11-24 08:00:00'); // a Tuesday
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->daily()->create(['max_price' => 300_000]);
        SavedSearch::factory()->for($user)->daily()->create(['max_price' => 400_000]);
        $other = SavedSearch::factory()->daily()->create(['max_price' => 300_000])->user;
        Listing::factory()->live()->create(['price' => 250_000]);
        Queue::fake([SendAlertDigest::class]);

        $this->artisan('alerts:send-digests')->assertSuccessful();

        Queue::assertPushed(SendAlertDigest::class, 2);
        Queue::assertPushed(SendAlertDigest::class, fn (SendAlertDigest $job) => $job->user->is($user));
        Queue::assertPushed(SendAlertDigest::class, fn (SendAlertDigest $job) => $job->user->is($other));
    }

    public function test_leaves_weekly_searches_alone_on_other_days(): void
    {
        $this->travelTo('2026-11-24 08:00:00'); // a Tuesday
        SavedSearch::factory()->weekly()->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);
        Queue::fake([SendAlertDigest::class]);

        $this->artisan('alerts:send-digests')->assertSuccessful();

        Queue::assertNotPushed(SendAlertDigest::class);
    }

    /**
     * On a Monday the weekly searches go into the same digest as the daily
     * ones, so nobody gets two emails that morning.
     */
    public function test_sends_weekly_and_daily_searches_together_on_a_monday(): void
    {
        $this->travelTo('2026-11-23 08:00:00'); // a Monday
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->weekly()->create(['max_price' => 300_000]);
        SavedSearch::factory()->for($user)->daily()->create(['max_price' => 400_000]);
        Listing::factory()->live()->create(['price' => 250_000]);
        Queue::fake([SendAlertDigest::class]);

        $this->artisan('alerts:send-digests')->assertSuccessful();

        Queue::assertPushed(SendAlertDigest::class, 1);
        Queue::assertPushed(
            SendAlertDigest::class,
            fn (SendAlertDigest $job) => $job->user->is($user)
                && $job->frequencies === [AlertFrequency::Daily, AlertFrequency::Weekly],
        );
    }

    /**
     * For running a frequency by hand, whatever the day.
     */
    public function test_sends_only_the_frequencies_it_is_given(): void
    {
        $this->travelTo('2026-11-24 08:00:00'); // a Tuesday
        $weekly = SavedSearch::factory()->weekly()->create(['max_price' => 300_000]);
        SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 250_000]);
        Queue::fake([SendAlertDigest::class]);

        $this->artisan('alerts:send-digests weekly')->assertSuccessful();

        Queue::assertPushed(SendAlertDigest::class, 1);
        Queue::assertPushed(
            SendAlertDigest::class,
            fn (SendAlertDigest $job) => $job->user->is($weekly->user)
                && $job->frequencies === [AlertFrequency::Weekly],
        );
    }

    public function test_skips_a_buyer_with_nothing_new_to_send(): void
    {
        $this->travelTo('2026-11-24 08:00:00'); // a Tuesday
        SavedSearch::factory()->daily()->create(['max_price' => 300_000]);
        Listing::factory()->live()->create(['price' => 450_000]);
        Queue::fake([SendAlertDigest::class]);

        $this->artisan('alerts:send-digests')->assertSuccessful();

        Queue::assertNotPushed(SendAlertDigest::class);
    }

    public function test_fails_for_a_frequency_it_does_not_know(): void
    {
        Queue::fake([SendAlertDigest::class]);

        $this->artisan('alerts:send-digests hourly')->assertFailed();

        Queue::assertNotPushed(SendAlertDigest::class);
    }

    /**
     * Without a schedule the daily and weekly searches would raise alerts and
     * never email them. One run a morning means at most one digest a day.
     */
    #[DataProvider('moments')]
    public function test_is_scheduled_once_every_morning_at_eight(string $moment, int $runsDue): void
    {
        $this->travelTo($moment);

        $due = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains($event->command, 'alerts:send-digests'))
            ->filter(fn (Event $event) => $event->isDue(app()));

        $this->assertCount($runsDue, $due);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function moments(): array
    {
        return [
            'a Monday at eight' => ['2026-11-23 08:00:00', 1],
            'a Tuesday at eight' => ['2026-11-24 08:00:00', 1],
            'a Monday at nine' => ['2026-11-23 09:00:00', 0],
        ];
    }
}
