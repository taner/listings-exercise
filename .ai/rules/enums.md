---
paths:
  - 'app/Enums/**'
---

# Enums

## Enums own their labels
Use string-backed enums with TitleCase cases and snake_case values. Each has a `label()` built with `match`. When the front end needs a select, add a static `options()` returning `value` / `label` pairs and pass it as a prop, so labels are never duplicated in Vue.
