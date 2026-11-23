---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Controllers query Eloquent directly and render Inertia pages
There is no Action, Service or Repository layer. Build the Eloquent query in the controller method and return `Inertia::render('<Resource>/<Action>', [...])`, typed as `Inertia\Response`. Put reusable query conditions on the model as a scope.

## Pass models to pages through API Resources
Never hand a model, or a hand-built array of model data, to `Inertia::render`. Wrap it in a JsonResource: `new ListingResource($listing)` or `ListingResource::collection($paginator)`. Resources are unwrapped globally, so a single resource arrives flat and a paginated one keeps `data` / `links` / `meta`.

## Read input with typed getters behind filled()
Type-hint the Form Request, then read values with `$request->string()` / `->integer()`, not `->input()` or `validated()`. Guard each optional filter with `$request->filled()` so a blank query parameter counts as absent.

## Paginate with a deterministic order
Follow the main sort of a paginated query with a unique tiebreaker (`->orderByDesc('id')`) so rows cannot repeat or go missing between pages, and call `->withQueryString()` so filters survive the page links.
