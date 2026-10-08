# @dsh/web

Vue 3 + TypeScript + Vite SPA for the DSH registry — publish, discover and install
DeepSeek Harness plugins.

## Scripts

| Command | Description |
| --- | --- |
| `pnpm --filter @dsh/web dev` | Dev server on http://localhost:5173 |
| `pnpm --filter @dsh/web build` | Type-check (`vue-tsc`) + production build |
| `pnpm --filter @dsh/web test` | Unit tests (Vitest + happy-dom) |
| `pnpm --filter @dsh/web test:watch` | Unit tests in watch mode |

## Typography

Self-hosted via `@fontsource-variable` (no CDN, works offline). Both families are
declared in `src/style.css` and only the **latin** + **vietnamese** subsets are shipped.

| Token | Family | Used for |
| --- | --- | --- |
| `--font-sans` | Inter Variable | UI, body copy, headings |
| `--font-mono` | JetBrains Mono Variable | commands, package names, versions, endpoints |

## Conventions

- `src/views` — route pages, `src/layouts` — default (`AppNavbar`) and auth shells.
- `src/components/ui` — shadcn-vue primitives (see `components.json`).
- Copy lives in the SFCs; keep headings unique per page (`AuthLayout` renders the
  `h1` from `route.meta.title`, so views must not repeat a page title).

