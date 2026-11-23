<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Listings are filtered in two directions: the listings page finds the
 * listings a set of criteria accepts, and alert matching finds the saved
 * searches that accept one listing. They are separate queries, so these tests
 * hold both to the same hand-written answers. If the two ever disagree, a
 * buyer is alerted about a listing their own search would not show them.
 */
class SavedSearchMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<string>  $expected
     */
    #[DataProvider('criteria')]
    public function test_the_listings_page_shows_the_expected_listings(array $criteria, array $expected): void
    {
        $this->createListings();

        $response = $this->get('/?'.Arr::query([...$criteria, 'per_page' => 100]));

        $shown = collect($response->viewData('page')['props']['listings']['data'])->pluck('reference');
        $this->assertSame($expected, $shown->sort()->values()->all());
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<string>  $expected
     */
    #[DataProvider('criteria')]
    public function test_a_saved_search_matches_the_same_listings_its_results_page_shows(array $criteria, array $expected): void
    {
        User::factory()->create();
        $listings = $this->createListings();
        $this->post('/saved-searches', $criteria);
        $search = SavedSearch::query()->sole();

        $matched = $listings
            ->filter(fn (Listing $listing) => SavedSearch::query()->whereKey($search)->matching($listing)->exists())
            ->pluck('reference');

        $this->assertSame($expected, $matched->sort()->values()->all());
    }

    /**
     * Each case names the listings, by reference, that the criteria accept.
     * The prices, bedroom and bathroom counts sit on the boundaries on purpose.
     *
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function criteria(): array
    {
        return [
            'a maximum price' => [['max_price' => 300_000], ['A', 'B', 'C']],
            'a minimum price' => [['min_price' => 300_000], ['B', 'C', 'D']],
            'a price range' => [['min_price' => 250_000, 'max_price' => 300_000], ['B', 'C']],
            'a minimum number of bedrooms' => [['min_bedrooms' => 3], ['B', 'C']],
            'a maximum number of bedrooms' => [['max_bedrooms' => 2], ['A', 'D']],
            'a bedroom range' => [['min_bedrooms' => 2, 'max_bedrooms' => 3], ['A', 'B']],
            'a minimum number of bathrooms' => [['min_bathrooms' => 2], ['B', 'C']],
            'a region' => [['region' => 'Leeds'], ['A', 'B']],
            'a property type' => [['property_type' => 'flat'], ['A', 'B']],
            'every criterion at once' => [
                [
                    'property_type' => 'detached',
                    'min_price' => 300_000,
                    'max_price' => 400_000,
                    'min_bedrooms' => 3,
                    'max_bedrooms' => 4,
                    'min_bathrooms' => 3,
                    'region' => 'Manchester',
                ],
                ['C'],
            ],
        ];
    }

    /**
     * @return Collection<int, Listing>
     */
    private function createListings(): Collection
    {
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        $manchester = Branch::factory()->create(['region' => 'Manchester']);

        return collect([
            ['reference' => 'A', 'branch_id' => $leeds->id, 'property_type' => PropertyType::Flat, 'price' => 200_000, 'bedrooms' => 2, 'bathrooms' => 1],
            ['reference' => 'B', 'branch_id' => $leeds->id, 'property_type' => PropertyType::Flat, 'price' => 300_000, 'bedrooms' => 3, 'bathrooms' => 2],
            ['reference' => 'C', 'branch_id' => $manchester->id, 'property_type' => PropertyType::Detached, 'price' => 300_000, 'bedrooms' => 4, 'bathrooms' => 3],
            ['reference' => 'D', 'branch_id' => $manchester->id, 'property_type' => PropertyType::Bungalow, 'price' => 450_000, 'bedrooms' => 1, 'bathrooms' => 1],
        ])->map(fn (array $attributes) => Listing::factory()->live()->create($attributes));
    }
}
