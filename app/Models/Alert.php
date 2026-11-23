<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A listing that went live and was accepted by a saved search. It is recorded
 * the moment that happens and shown on the alerts page straight away. Whether
 * it has also been emailed depends on the search's alert frequency.
 *
 * @property int $id
 * @property int $saved_search_id
 * @property int $listing_id
 * @property Carbon|null $emailed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SavedSearch $savedSearch
 * @property-read Listing $listing
 */
class Alert extends Model
{
    protected $fillable = [
        'listing_id',
        'emailed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emailed_at' => 'datetime',
        ];
    }

    /**
     * Only alerts that have not been emailed yet and whose listing can still
     * be bought. One whose listing has left the market stays unsent, so it is
     * picked up if the listing comes back before the buyer was emailed.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeAwaitingEmail(Builder $query): void
    {
        $query
            ->whereNull('emailed_at')
            ->whereIn('listing_id', Listing::query()->live()->select('id'));
    }

    /**
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
