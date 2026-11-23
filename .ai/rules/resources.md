---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## Resource field conventions
Add `@mixin <Model>` to the class and return snake_case keys that match the columns. Expose an enum as its `->value` plus a `<field>_label` from `label()`, and a date as `->toIso8601String()` for the front end to format.
