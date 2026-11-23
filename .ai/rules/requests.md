---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Validate in Form Requests with array rules
Every endpoint that takes input gets a Form Request named `<Resource><Action>Request`, GET query-string filters included. Write rules as arrays, mark optional filters `nullable`, and validate enum input with `new Enum(X::class)`.
