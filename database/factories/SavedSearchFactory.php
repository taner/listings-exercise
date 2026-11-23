<?php

namespace Database\Factories;

use App\Enums\AlertFrequency;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => null,
            'property_type' => null,
            'min_price' => null,
            // A search needs at least one criterion, so the default is a price
            // cap.
            'max_price' => fake()->numberBetween(120, 950) * 1000,
            'min_bedrooms' => null,
            'max_bedrooms' => null,
            'min_bathrooms' => null,
            'region' => null,
        ];
    }

    public function instant(): static
    {
        return $this->state(fn () => ['alert_frequency' => AlertFrequency::Instant]);
    }

    public function daily(): static
    {
        return $this->state(fn () => ['alert_frequency' => AlertFrequency::Daily]);
    }

    public function weekly(): static
    {
        return $this->state(fn () => ['alert_frequency' => AlertFrequency::Weekly]);
    }
}
