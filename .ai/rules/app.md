---
paths:
  - 'app/**'
---

# App

## Auth is stubbed: read the user from $request->user()
There is no login flow. `ActAsDemoUser` resolves every web request as the first user in the database through the request's user resolver, so take the user from `$request->user()`. `auth()->user()`, `Auth::user()` and the `auth` middleware do not see that user.

## Start queries with Model::query()
Begin Eloquent chains with `Model::query()`, as in `Listing::query()->live()`, not with a static `Model::where()`.

## PHPStan level 5 with an empty baseline
Code under `app`, `database` and `routes` must pass `vendor/bin/phpstan analyse` (Larastan, level 5). Do not add `ignoreErrors` entries: fix the error, or narrow the ignore with a comment saying why. CI also runs `vendor/bin/pint --test`, `php artisan test` and `npm run build`.

## Comment the why
Explain a non-obvious decision in a short comment or docblock that says why: the bug it prevents or the constraint it serves. Do not narrate what the code does.
