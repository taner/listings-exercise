---
paths:
  - 'database/migrations/**'
---

# Migrations

## Store enums as string columns
Enum-backed fields are `string()` columns cast to the PHP enum in the model's `casts()`. Do not use database `enum()` columns.
