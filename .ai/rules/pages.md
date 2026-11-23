---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Wrap pages in AppLayout
Each page renders `<Head title>` and wraps its content in `<AppLayout heading subheading>` inside its own template; persistent layouts (`defineOptions({ layout })`) are not used. Pages live at `pages/<Resource>/<Action>.vue` and shared pieces sit flat in `components/`.

## Filters live in the query string
Seed a filter form from the server's `filters` prop and re-issue `router.get` with blank fields dropped (`preserveState`, `preserveScroll`, `replace`), so a search survives a refresh, a shared link and the back button. Do not keep filter state on the client only.
