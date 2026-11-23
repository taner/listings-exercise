# Street Listings

A small property-listings application: a Laravel 13 API with a Vue 3 front end. It lists properties for sale, organised by branch, with filtering and a detail view.

This repository is the starting point for a technical exercise. **Your task is described in [`TASK.md`](TASK.md).** This README covers what's here and how to run it.

## Requirements

- PHP 8.4
- Composer
- Node 20+ and npm
- SQLite (bundled with PHP — no separate database server needed)

## Getting started

```bash
# 1. Install dependencies
#    composer install also creates .env from .env.example (if missing),
#    creates database/database.sqlite, and sets APP_KEY when it's blank.
composer install
npm install

# 2. Database — migrate and seed
php artisan migrate --seed

# 3. Run — in three terminals, or use the combined dev script
php artisan serve      # API + app at http://127.0.0.1:8000
npm run dev            # Vite dev server for the front end
php artisan queue:work # creates alerts when a listing goes live

composer run dev       # all three at once, plus the logs
```

Then open <http://127.0.0.1:8000>.

Saved-search alerts are created on the queue, so without a queue worker a listing going live produces none. Daily and weekly alert emails also need the scheduler (`php artisan schedule:work`), or run `php artisan alerts:send-digests` by hand.

### Trying saved-search alerts

With `composer run dev` running, save a search on the listings page with only "Min beds" set to 1, so that it matches any listing. Then make a draft go live:

```bash
php artisan tinker --execute 'App\Models\Listing::query()->where("status", "draft")->first()->update(["status" => "live", "listed_at" => now()]);'
```

The alert shows on `/alerts`. `php artisan alerts:send-digests` sends the email, which lands in `storage/logs/laravel.log`. Save the search first: only listings that go live after a search was saved create alerts.

[`MANUAL_TESTING.md`](MANUAL_TESTING.md) walks through the whole feature step by step.

## Running the checks

```bash
php artisan test              # PHPUnit feature/unit tests
vendor/bin/pint --test        # Code style (Laravel Pint)
vendor/bin/phpstan analyse    # Static analysis (Larastan, level 5)
```

All three run in CI (`.github/workflows/ci.yml`) on push and pull request, and all pass on a fresh clone.

## How it's laid out

```
app/
  Console/Commands/      SendAlertDigests (alerts:send-digests)
  Enums/                 PropertyType, ListingStatus, AlertFrequency
  Http/
    Controllers/         ListingController, SavedSearchController, AlertController
    Requests/            ListingIndexRequest (filter validation)
                         SavedSearchStoreRequest, SavedSearchUpdateRequest
                         Concerns/ValidatesListingCriteria (rules shared by
                         the listings page and saved searches)
    Resources/           ListingResource, BranchResource,
                         SavedSearchResource, AlertResource
    Middleware/          ActAsDemoUser (auth stub — see below)
                         HandleInertiaRequests (shared props)
  Jobs/                  MatchListingToSavedSearches (creates the alerts)
                         SendAlertDigest (emails one buyer their alerts)
  Models/                Branch, Listing, SavedSearch, Alert
  Notifications/         AlertDigest (the alert email)
  Observers/             ListingObserver (spots a listing going live)
database/
  factories/             BranchFactory, ListingFactory, SavedSearchFactory
                         (with states)
  migrations/            branches, listings, saved_searches, alerts
  seeders/               DatabaseSeeder
resources/js/
  pages/Listings/        Index.vue, Show.vue
  pages/SavedSearches/   Index.vue
  pages/Alerts/          Index.vue
  components/            AppLayout, ListingCard, ListingFilters, Pagination,
                         SavedSearchCard
  app.js                 Inertia entry point
routes/                  web.php, console.php (the daily digest schedule)
tests/Feature/           ListingPageTest, ListingTest
                         SavedSearchPageTest, SavedSearchTest,
                         SavedSearchMatchingTest
                         ListingObserverTest, MatchListingToSavedSearchesTest
                         AlertPageTest, AlertDigestTest,
                         SendAlertDigestTest, SendAlertDigestsTest
```

### The domain

- **Branch** — a name and a region.
- **Listing** — address, price, bedrooms, bathrooms, `property_type`, `status` (`draft` / `live` / `under_offer` / `sold`), a branch, and a `listed_at` date.
- **Saved search** — a user's listing criteria (`property_type`, a price range, a bedrooms range, minimum bathrooms, `region`; any left blank accept anything), an optional name, and an `alert_frequency` (`instant` / `daily` / `weekly`) for its emails.
- **Alert** — one saved search matched by one listing that went live after the search was saved. Created once per pair, with an `emailed_at` time once it has gone out in an email.

### The stack

Laravel + **Inertia** + Vue 3 + Tailwind — the same shape as our internal apps. There is no separate JSON API: controllers return `Inertia::render(...)` with props, and API Resources (`ListingResource`, `BranchResource`) define the shape those props take.

| Method | Path                 | Page              | Notes                                                      |
| ------ | -------------------- | ----------------- | ---------------------------------------------------------- |
| GET    | `/`                  | `Listings/Index`  | Live listings, paginated. Filters: `property_type`, `min_price`, `max_price`, `min_bedrooms`, `max_bedrooms`, `min_bathrooms`, `region`, `per_page`. |
| GET    | `/listings/{listing}`| `Listings/Show`   | A single live listing. Non-live listings 404.              |
| GET    | `/saved-searches`    | `SavedSearches/Index` | The user's saved searches. `POST` saves the applied filters; `PATCH` and `DELETE` on `/saved-searches/{savedSearch}` rename, re-time or remove one. |
| GET    | `/alerts`            | `Alerts/Index`    | The user's alerts: one for each new listing a saved search matched. Paginated. |

Filters live in the query string, so a search is shareable, bookmarkable and survives the back button. `pages/Listings/Index.vue` seeds its form from the `filters` prop and re-issues a `router.get` on submit.

### Authentication

Auth is **stubbed**. A real deployment would authenticate requests properly; to keep this exercise focused on the feature rather than on auth plumbing, every request is resolved as the seeded demo user (`demo@street.example`) via `App\Http\Middleware\ActAsDemoUser`, and shared to the front end as the `auth.user` prop. Build any user-scoped work against `$request->user()` / `auth()->user()` as you normally would — it will return the demo user.

Note the resolver returns `null` until the database is seeded, so run `php artisan migrate --seed` before you start.

---

Your task: **[`TASK.md`](TASK.md)**.
