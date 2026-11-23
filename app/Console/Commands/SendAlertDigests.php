<?php

namespace App\Console\Commands;

use App\Enums\AlertFrequency;
use App\Jobs\SendAlertDigest;
use App\Models\Alert;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerts:send-digests {frequency?* : Send only for these frequencies (daily, weekly), whatever the day}')]
#[Description('Queue one email for every buyer with alerts waiting on their daily or weekly saved searches')]
class SendAlertDigests extends Command
{
    /**
     * The scheduler runs this once each morning. It only queues the work: each
     * buyer is emailed by their own job, so one failure does not hold up the
     * rest.
     */
    public function handle(): int
    {
        $frequencies = $this->frequencies();

        if ($frequencies === null) {
            $this->error('Unknown frequency. Use one or more of: '.implode(', ', array_column(AlertFrequency::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $searchesWithAlertsWaiting = SavedSearch::query()
            ->whereIn('alert_frequency', $frequencies)
            ->whereIn('id', Alert::query()->awaitingEmail()->select('saved_search_id'));

        $queued = 0;

        User::query()
            ->whereIn('id', $searchesWithAlertsWaiting->select('user_id'))
            ->lazyById(500)
            ->each(function (User $user) use ($frequencies, &$queued): void {
                SendAlertDigest::dispatch($user, $frequencies);
                $queued++;
            });

        $this->info("Queued emails for {$queued} buyer(s).");

        return self::SUCCESS;
    }

    /**
     * The frequencies named on the command line, or the ones due today: daily
     * searches every day, joined by weekly ones on a Monday so that a buyer
     * with both still gets one email that morning. Null if a name is unknown.
     *
     * @return list<AlertFrequency>|null
     */
    private function frequencies(): ?array
    {
        $named = $this->argument('frequency');

        if ($named === []) {
            return now()->isMonday()
                ? [AlertFrequency::Daily, AlertFrequency::Weekly]
                : [AlertFrequency::Daily];
        }

        $frequencies = [];

        foreach ($named as $name) {
            $frequency = AlertFrequency::tryFrom($name);

            if ($frequency === null) {
                return null;
            }

            $frequencies[] = $frequency;
        }

        return $frequencies;
    }
}
