---
paths:
  - 'app/Models/**'
---

# Models

## Annotate models for Larastan
Declare every column as `@property` and every relation as `@property-read` in the class docblock. Give relation methods a generic return doc (`@return BelongsTo<Branch, $this>`) and the factory trait `/** @use HasFactory<XFactory> */`.

## Store enums as string columns
Enum-backed fields are `string()` columns cast to the PHP enum in the model's `casts()`. Do not use database `enum()` columns.

## Declare mass assignment with protected $fillable
List mass-assignable columns in a `protected $fillable` array, not the `#[Fillable]` attribute. `User` is the unmodified framework skeleton and keeps its attribute; leave it as it is.
