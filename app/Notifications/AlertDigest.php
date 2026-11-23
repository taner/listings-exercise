<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email telling a buyer about the new listings their saved searches
 * accepted.
 *
 * One email covers every alert the buyer has waiting, grouped by search, so a
 * buyer with several searches still gets a single message, and a listing
 * several of those searches accepted appears in it once.
 */
class AlertDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * A busy search must not turn the email into a catalogue. The rest are on
     * the alerts page.
     */
    private const int LISTINGS_SHOWN_PER_SEARCH = 4;

    /**
     * @param  Collection<int, Alert>  $alerts  With their saved search and
     *                                          listing loaded.
     */
    public function __construct(
        public Collection $alerts,
    ) {
        // It is sent while the alerts are being marked as emailed. Waiting
        // for that to commit means a rolled-back send never reaches the buyer.
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject());

        // To the buyer a listing that matched two of their searches is still
        // one listing, so it is shown once, under the search they saved first.
        $oneAlertPerListing = $this->alerts->sortBy('saved_search_id')->unique('listing_id');

        // Newest first, so the four shown for a search are its latest and
        // the search with the freshest listing leads the email.
        $newestFirst = $oneAlertPerListing->sortBy([['created_at', 'desc'], ['id', 'desc']]);

        foreach ($newestFirst->groupBy('saved_search_id') as $alerts) {
            $message->line('**'.$alerts->firstOrFail()->savedSearch->label().'**');

            foreach ($alerts->take(self::LISTINGS_SHOWN_PER_SEARCH) as $alert) {
                $listing = $alert->listing;

                $message->line(sprintf(
                    '[£%s · %s, %s](%s) · %d bed · %s',
                    number_format($listing->price),
                    $listing->address_line_1,
                    $listing->city,
                    route('listings.show', $listing),
                    $listing->bedrooms,
                    $listing->property_type->label(),
                ));
            }

            $more = $alerts->count() - self::LISTINGS_SHOWN_PER_SEARCH;

            if ($more > 0) {
                $message->line("…and {$more} more.");
            }
        }

        return $message
            ->action('View your alerts', route('alerts.index'))
            ->line(sprintf(
                'To change how often a search emails you, or to stop it, visit your [saved searches](%s).',
                route('saved-searches.index'),
            ));
    }

    /**
     * Names the search when there is only one. A listing that matched two
     * searches is counted once.
     */
    private function subject(): string
    {
        $count = $this->alerts->unique('listing_id')->count();
        $searches = $this->alerts->unique('saved_search_id');

        $matched = $searches->count() === 1
            ? $searches->firstOrFail()->savedSearch->label()
            : 'your saved searches';

        return $count === 1
            ? "1 new listing matches {$matched}"
            : "{$count} new listings match {$matched}";
    }
}
