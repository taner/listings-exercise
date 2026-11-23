<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use App\Models\SavedSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_frequency_defaults_to_daily(): void
    {
        $search = SavedSearch::factory()->create();

        $this->assertSame(AlertFrequency::Daily, $search->alert_frequency);
        $this->assertSame(AlertFrequency::Daily, $search->fresh()->alert_frequency);
    }

    public function test_property_type_is_cast_to_an_enum(): void
    {
        $search = SavedSearch::factory()->create(['property_type' => PropertyType::Flat]);

        $this->assertSame(PropertyType::Flat, $search->fresh()->property_type);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('summaries')]
    public function test_summary_describes_the_criteria(array $criteria, string $expected): void
    {
        $search = SavedSearch::factory()->create($criteria);

        $this->assertSame($expected, $search->summary());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function summaries(): array
    {
        return [
            'every criterion' => [
                [
                    'property_type' => PropertyType::Flat,
                    'min_price' => 150_000,
                    'max_price' => 300_000,
                    'min_bedrooms' => 2,
                    'max_bedrooms' => 4,
                    'min_bathrooms' => 2,
                    'region' => 'Leeds',
                ],
                'Flat · Leeds · £150,000 to £300,000 · 2 to 4 beds · 2+ baths',
            ],
            'a property type only' => [['property_type' => PropertyType::SemiDetached, 'max_price' => null], 'Semi-detached'],
            'a minimum price only' => [['min_price' => 150_000, 'max_price' => null], 'From £150,000'],
            'a maximum price only' => [['max_price' => 300_000], 'Up to £300,000'],
            'a minimum number of bedrooms' => [['max_price' => null, 'min_bedrooms' => 3], '3+ beds'],
            'a maximum number of bedrooms' => [['max_price' => null, 'max_bedrooms' => 2], 'Up to 2 beds'],
            'an exact number of bedrooms' => [['max_price' => null, 'min_bedrooms' => 3, 'max_bedrooms' => 3], '3 beds'],
            'one bedroom at most' => [['max_price' => null, 'max_bedrooms' => 1], 'Up to 1 bed'],
            'a minimum number of bathrooms' => [['max_price' => null, 'min_bathrooms' => 2], '2+ baths'],
        ];
    }

    public function test_label_is_the_name_when_the_search_has_one(): void
    {
        $search = SavedSearch::factory()->create(['name' => 'Leeds flats', 'max_price' => 300_000]);

        $this->assertSame('Leeds flats', $search->label());
    }

    public function test_label_falls_back_to_the_summary(): void
    {
        $search = SavedSearch::factory()->create(['name' => null, 'max_price' => 300_000]);

        $this->assertSame('Up to £300,000', $search->label());
    }
}
