---
paths:
  - 'resources/js/**'
---

# Js

## Plain JavaScript SFCs with runtime props
Write `<script setup>` in plain JavaScript, with no TypeScript, and declare props as runtime objects: `defineProps({ listing: { type: Object, required: true } })`.

## Relative imports, no alias
No `@/` alias is configured. Import with relative paths, such as `../../components/AppLayout.vue` and `../format`.

## Literal URLs, no route helper
Ziggy and Wayfinder are not installed. Link and visit with literal paths, such as `href="/"` and `` `/listings/${listing.id}` ``.

## Format on the client with format.js
Format prices and dates with the helpers in `resources/js/format.js` (en-GB, GBP) and add new formatters there. Show enum text from the server's `*_label` fields and option props, never from a label map in Vue.

## Tailwind utilities only
Style with Tailwind utility classes in the template, on the slate palette with `rounded-lg` controls and `rounded-xl` cards. There is no UI component library and no custom CSS.

## Comment the why
Explain a non-obvious decision in a short comment that says why: the bug it prevents or the constraint it serves. Do not narrate what the code does.
