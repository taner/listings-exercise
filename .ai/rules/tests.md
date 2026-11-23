---
paths:
  - 'tests/**'
---

# Tests

## Test naming and layout
Write PHPUnit classes in `tests/Feature` with `use RefreshDatabase` and `test_snake_case_sentence(): void` methods. HTTP page tests go in `<Resource>PageTest` and model behaviour (scopes, casts) in `<Model>Test`; both use the database, so both live in Feature.

## Assert Inertia pages with AssertableInertia
Request pages by literal URL (`$this->get('/?max_price=300000')`), not `route()`, and assert with `->assertInertia(fn (AssertableInertia $page) => $page->component(...)->has(...)->where(...))`.

## Build data with factory states
Create records with factories and their named states (`Listing::factory()->live()`, `->sold()`, `->for($branch)`), overriding only the attribute under test. A bare `Listing::factory()` is a draft.

## The acting user is the first user row
The auth stub resolves the first user by id, and `null` when the table is empty. A user-scoped test must create that user first (`User::factory()->create()` or `$this->seed()`); `actingAs()` does not change who `$request->user()` returns.
