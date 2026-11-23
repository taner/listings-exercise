<?php

namespace App\Models;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A set of listing criteria a user wants to be alerted about. The criteria are
 * fixed once saved; only the name and the alert frequency change afterwards.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property PropertyType|null $property_type
 * @property int|null $min_price
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property int|null $max_bedrooms
 * @property int|null $min_bathrooms
 * @property string|null $region
 * @property AlertFrequency $alert_frequency
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, Alert> $alerts
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'property_type',
        'min_price',
        'max_price',
        'min_bedrooms',
        'max_bedrooms',
        'min_bathrooms',
        'region',
        'alert_frequency',
    ];

    /**
     * Mirrors the column default, so a search that has just been created reads
     * the same in memory as it does once reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'alert_frequency' => AlertFrequency::Daily->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'min_price' => 'integer',
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
            'max_bedrooms' => 'integer',
            'min_bathrooms' => 'integer',
            'alert_frequency' => AlertFrequency::class,
        ];
    }

    /**
     * Only the searches whose criteria accept the given listing. A criterion
     * left blank accepts anything.
     *
     * This is the listings page filter run the other way round: one listing
     * against every search, rather than one search against every listing.
     * SavedSearchMatchingTest holds the two to the same answers.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatching(Builder $query, Listing $listing): void
    {
        $query
            ->where(fn (Builder $query) => $query
                ->whereNull('property_type')
                ->orWhere('property_type', $listing->property_type))
            ->where(fn (Builder $query) => $query
                ->whereNull('min_price')
                ->orWhere('min_price', '<=', $listing->price))
            ->where(fn (Builder $query) => $query
                ->whereNull('max_price')
                ->orWhere('max_price', '>=', $listing->price))
            ->where(fn (Builder $query) => $query
                ->whereNull('min_bedrooms')
                ->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $query) => $query
                ->whereNull('max_bedrooms')
                ->orWhere('max_bedrooms', '>=', $listing->bedrooms))
            ->where(fn (Builder $query) => $query
                ->whereNull('min_bathrooms')
                ->orWhere('min_bathrooms', '<=', $listing->bathrooms))
            ->where(fn (Builder $query) => $query
                ->whereNull('region')
                ->orWhere('region', $listing->branch->region));
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /**
     * The criteria that are set, keyed like the listings page filters, so they
     * are the query string of the page showing this search's results.
     *
     * @return array<string, int|string>
     */
    public function filters(): array
    {
        return array_filter([
            'property_type' => $this->property_type?->value,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'min_bedrooms' => $this->min_bedrooms,
            'max_bedrooms' => $this->max_bedrooms,
            'min_bathrooms' => $this->min_bathrooms,
            'region' => $this->region,
        ], fn (int|string|null $value) => $value !== null);
    }

    /**
     * The criteria in words, e.g. "Flat · Leeds · Up to £300,000".
     */
    public function summary(): string
    {
        $price = match (true) {
            $this->min_price !== null && $this->max_price !== null => $this->pounds($this->min_price).' to '.$this->pounds($this->max_price),
            $this->min_price !== null => 'from '.$this->pounds($this->min_price),
            $this->max_price !== null => 'up to '.$this->pounds($this->max_price),
            default => null,
        };

        $bedrooms = match (true) {
            $this->min_bedrooms !== null && $this->min_bedrooms === $this->max_bedrooms => $this->beds($this->min_bedrooms),
            $this->min_bedrooms !== null && $this->max_bedrooms !== null => $this->min_bedrooms.' to '.$this->beds($this->max_bedrooms),
            $this->min_bedrooms !== null => $this->min_bedrooms.'+ beds',
            $this->max_bedrooms !== null => 'up to '.$this->beds($this->max_bedrooms),
            default => null,
        };

        return Str::ucfirst(implode(' · ', array_filter([
            $this->property_type?->label(),
            $this->region,
            $price,
            $bedrooms,
            $this->min_bathrooms !== null ? $this->min_bathrooms.'+ baths' : null,
        ])));
    }

    /**
     * What to call this search: its name if the user gave one, otherwise the
     * criteria in words.
     */
    public function label(): string
    {
        return $this->name ?? $this->summary();
    }

    private function pounds(int $amount): string
    {
        return '£'.number_format($amount);
    }

    private function beds(int $count): string
    {
        return $count === 1 ? '1 bed' : $count.' beds';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * One alert for each listing that went live and was accepted by this
     * search.
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
