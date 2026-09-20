# DSH

pnpm monorepo: Laravel API + Filament admin + Vue SPA.

## Stack

- `apps/api` — Laravel 13 REST (`/api/v1`), Filament 5 (`/admin`), Scramble (`/docs/api`), Sanctum
- `apps/web` — Vue 3 + Vite + Vue Router + Pinia
- `packages/shared` — shared TypeScript types
- Docker Dev Container — whole workspace + Postgres + Redis + Mailpit

## Ports

| Service | URL / port |
|---------|------------|
| API | http://localhost:8000 |
| Vue | http://localhost:5173 |
| Filament | http://localhost:8000/admin |
| Scramble | http://localhost:8000/docs/api |
| Mailpit | http://localhost:8025 |
| Postgres | localhost:5432 |
| Redis | localhost:6379 |

## Dev Container

1. Open this repo in VS Code / Cursor
2. Reopen in Container
3. Inside the container:

```bash
pnpm install
cp apps/api/.env.example apps/api/.env
cd apps/api && composer install && php artisan key:generate && php artisan migrate --seed
cd /workspace && pnpm --filter @dsh/web exec -- cp -n .env.example .env || true
```

## Run

```bash
# terminal 1
pnpm --filter @dsh/api dev

# terminal 2
pnpm dev:web
```

## Seed users

| Email | Password | Admin |
|-------|----------|-------|
| admin@example.com | password | yes |
| user@example.com | password | no |

## Sanctum SPA notes

- Vue origin: `http://localhost:5173`
- Call `GET /sanctum/csrf-cookie` before `POST /api/v1/login` with `credentials: 'include'`
