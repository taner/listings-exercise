<?php

namespace App\Jobs;

use App\Enums\AlertFrequency;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\AlertDigest;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Emails one buyer every alert that is waiting on their searches of the given
 * frequencies, in a single message.
 *
 * When this runs is what the alert frequency means: straight after matching
 * for instant searches, and from the scheduler for daily and weekly ones.
 */
class SendAlertDigest implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * A user deleted before the job runs has nobody left to email.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  list<AlertFrequency>  $frequencies
     */
    public function __construct(
        public User $user,
        public array $frequencies,
    ) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            // Locked so that two runs for the same buyer cannot both pick up
            // the same alerts and email them twice.
            $alerts = $this->user->alerts()
                ->awaitingEmail()
                ->whereIn('saved_searches.alert_frequency', $this->frequencies)
                ->with(['savedSearch', 'listing'])
                ->lockForUpdate()
                ->get();

            if ($alerts->isEmpty()) {
                return;
            }

            Alert::query()
                ->whereKey($alerts->modelKeys())
                ->update(['emailed_at' => now()]);

            $this->user->notify(new AlertDigest($alerts));
        });
    }
}
