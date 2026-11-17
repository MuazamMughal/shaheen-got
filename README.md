# Shaheen Group of Travels — Group Ticketing Platform

Laravel 12 · Inertia.js · Vue 3 · Tailwind CSS v4 · MySQL 8

- Architecture: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- **Deploying to Hostinger: [deploy/README.md](deploy/README.md)**

## Local development (Docker)

Requirements: Docker Desktop.

```bash
cp .env.example .env                 # first time only; set SUPER_ADMIN_PASSWORD
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate   # first time only
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

| Service | URL |
|---|---|
| App | http://localhost:8080 |
| Vite (HMR) | http://localhost:5173 |
| Mailpit (all outgoing email) | http://localhost:8025 |
| phpMyAdmin | http://localhost:8081 |
| MySQL (from host) | `127.0.0.1:3307` |

The `worker` container runs the scheduler and queue — the local stand-in for the
Hostinger cron job. All emails are queued, so **restart it after changing PHP code**:

```bash
docker compose restart worker
```

## Notifications

Every event emails the right people (and shows in the 🔔 bell for signed-in users).
Who receives what is decided in one place: `app/Services/Notifier.php`.
Emails to a booking's **booker** (the agent's customer) never include Shaheen's prices.

### Everyday commands

```bash
docker compose exec app php artisan <command>
docker compose exec app php artisan test
docker compose exec node npm run build
docker compose logs -f node            # Vite output
```

### Logging in

The super admin is created by `php artisan db:seed` from `SUPER_ADMIN_EMAIL` /
`SUPER_ADMIN_PASSWORD` in `.env`. New agents register at `/register` and wait for approval.

| User type | Lands on |
|---|---|
| super_admin / staff | `/admin` |
| agent (approved) | `/agent` |
| agent (pending) | `/agent/pending` |
| rejected / suspended | cannot log in |

## Theming

Brand colors are stored in the `settings` table (`theme.primary`, `theme.secondary`,
`theme.tertiary`) and printed as CSS variables, so they change without a rebuild.
Use `bg-secondary`, `text-tertiary`, `bg-secondary-50 … 900`, `bg-tertiary-50 … 700`.
