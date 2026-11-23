# Manual testing

A walkthrough of everything the saved-search alerts feature does. Work through it in order, because later steps use the searches and listings from earlier ones. It takes about ten minutes.

## Before you start

```bash
php artisan migrate:fresh --seed   # a clean database with the demo user
composer run dev                   # the app, Vite, the queue worker and the logs
```

Open <http://127.0.0.1:8000>. Keep a second terminal open for the commands below.

Two things to know:

- Alerts are created by the queue worker, so they show up a second or two after a command, not instantly. Refresh the page if you were quick.
- No real email is sent. With the default `.env` each email is written to `storage/logs/laravel.log`.

There is no screen for publishing a listing, so this command stands in for it. It makes one draft listing live:

```bash
php artisan tinker --execute 'App\Models\Listing::query()->where("status", "draft")->first()->update(["status" => "live", "listed_at" => now()]);'
```

It is called **the publish command** in the steps below.

## 1. Filters on the listings page

1. Set "Min price (£)", "Max beds" and "Min baths", then press "Search". These three filters are new.
   **Expected:** the list narrows, and the filters stay filled in after a page refresh.
2. Set "Min price (£)" to 500000 and "Max price (£)" to 100000, then press "Search".
   **Expected:** a red box says "The minimum price cannot be more than the maximum price."
3. Set "Min beds" to 5 and "Max beds" to 2, then press "Search".
   **Expected:** "The minimum bedrooms cannot be more than the maximum bedrooms."
4. Open <http://127.0.0.1:8000/?region=Atlantis>.
   **Expected:** "We have no branches in that area."

## 2. Save a search

1. Press "Clear".
   **Expected:** "Save this search" is greyed out. A search with no filters cannot be saved.
2. Set "Min beds" to 1 and press "Search", then press "Save this search". Every seeded listing has at least one bedroom, so this search matches any listing.
   **Expected:** you land on "Saved searches" with one card headed "1+ beds".
3. Go back to the listings, run the same search and save it again.
   **Expected:** still one card. Saving the same search twice does not make a copy.

## 3. Rename a search and view its results

1. On the card, type a name such as "Anything" and press "Save".
   **Expected:** "Saved." appears next to the button.
2. Look at the "Alert me" list.
   **Expected:** "Instantly", "Daily" and "Weekly", with "Daily" selected.
3. Press "View results".
   **Expected:** the listings page opens with "Min beds" set to 1.

## 4. A new listing creates an alert

1. Open "Alerts".
   **Expected:** "No alerts yet."
2. Run the publish command, then refresh "Alerts".
   **Expected:** one alert, showing the listing and the name of the search that found it.

## 5. Two searches, and no backfill

1. On the listings page, press "Clear", set "Min baths" to 1, press "Search" and save it. This search also matches any listing.
   **Expected:** a second card, headed "1+ baths".
2. Open "Alerts".
   **Expected:** still one alert. The listing from step 4 went live before this search was saved, so the new search is not alerted about it.
3. Run the publish command, then refresh "Alerts".
   **Expected:** two new alerts for the same listing, one for each search.

## 6. A listing is only new once

1. Take the newest listing off the market:

   ```bash
   php artisan tinker --execute 'App\Models\Listing::query()->latest("listed_at")->first()->update(["status" => "under_offer"]);'
   ```

   **Expected:** on "Alerts", its two alerts now say "This listing is no longer available."
2. Put it back:

   ```bash
   php artisan tinker --execute 'App\Models\Listing::query()->latest("listed_at")->first()->update(["status" => "live"]);'
   ```

   **Expected:** the listing shows again, and there are no new alerts. Three alerts in total.

## 7. The daily email

1. Run `php artisan alerts:send-digests`.
   **Expected:** it prints "Queued emails for 1 buyer(s)."
2. Open `storage/logs/laravel.log` and go to the end.
   **Expected:** one email with the subject "2 new listings match your saved searches". The listing that matched both searches appears once.
3. Run `php artisan alerts:send-digests` again.
   **Expected:** "Queued emails for 0 buyer(s)." An alert is never emailed twice.
4. Run `php artisan schedule:list`.
   **Expected:** `alerts:send-digests` is listed to run every day at 08:00.

## 8. A busy search shows four listings

1. Publish six drafts at once:

   ```bash
   php artisan tinker --execute 'App\Models\Listing::query()->where("status", "draft")->limit(6)->get()->each->update(["status" => "live", "listed_at" => now()]);'
   ```

2. Run `php artisan alerts:send-digests` and look at the end of the log.
   **Expected:** one email. Under the search name it lists four listings, then "…and 2 more."

## 9. Instant and weekly

1. On "Saved searches", set the first search ("Anything") to "Instantly" and press "Save".
2. Run the publish command and look at the end of the log.
   **Expected:** an email arrives within a few seconds, without running `alerts:send-digests`. Its subject is "1 new listing matches Anything".
3. Set the other search ("1+ baths") to "Weekly" and press "Save".
4. Run `php artisan alerts:send-digests weekly`.
   **Expected:** "Queued emails for 1 buyer(s)." and one email for that search. Without the word `weekly`, the command only includes weekly searches on a Monday.

## 10. Delete a search

1. On "Saved searches", press "Delete" on the "1+ baths" card and confirm.
   **Expected:** the card is gone, and its alerts are gone from "Alerts". The other search and its alerts are untouched.

## Starting again

`php artisan migrate:fresh --seed` puts the database back to the start.

## The automated tests

```bash
php artisan test
```
