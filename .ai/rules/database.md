---
paths:
  - 'database/**'
---

# Database

## PHPStan level 5 with an empty baseline
Code under `app`, `database` and `routes` must pass `vendor/bin/phpstan analyse` (Larastan, level 5). Do not add `ignoreErrors` entries: fix the error, or narrow the ignore with a comment saying why. Factories declare `@extends Factory<Model>` and `@return array<string, mixed>`.
