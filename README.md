# Chetnasharm Backend

Laravel API backend for the Chetnasharm / Listenact learning platform. It powers admin, teacher, and student flows for classes, batches, enrollments, payments, assignments, attendance, and class reminders.

## Stack

- PHP 8.3
- Laravel 13
- JWT auth (`tymon/jwt-auth`)
- Spatie roles & permissions (`admin`, `teacher`, `student`)
- Stripe & PayPal payments
- Meta WhatsApp Cloud API (class reminders)
- Google OAuth (Socialite)
- Pest / PHPUnit
- Laravel Envoy (deploy)

## Features

- Public class/batch browsing and teacher profiles
- Admin CRUD for users, teachers, classes, batches, settings
- Teacher availability, schedules, attendance, notes, assignments
- Student enrollment, payments, waitlists, activity notes
- Integration credentials (Stripe / PayPal / WhatsApp) stored in `settings.integrations`
- Social links API for the website footer
- Scheduled class reminders (email + WhatsApp)

## Requirements

- PHP 8.3+
- Composer
- MySQL (or SQLite for local/tests)
- Node.js (optional, for Vite assets)
- Queue worker (for notifications / reminders)
- Cron / scheduler (`php artisan schedule:run`)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configure database and mail in `.env`, then:

```bash
php artisan migrate --seed
# or
php artisan migrate:fresh --seed
```

Default seeded admin (from `DatabaseSeeder`):

- Email: `admin@gmail.com`
- Password: `12345678`

Run the app:

```bash
composer run dev
# or
php artisan serve
php artisan queue:listen
php artisan schedule:work
```

API base URL: `http://localhost:8000/api`

## Useful Composer scripts

| Script | Purpose |
|--------|---------|
| `composer run dev` | Serve app + queue + logs + Vite |
| `composer test` | Run the test suite |
| `composer deploy` | Envoy: pull `mahmudul` branch + migrate |
| `composer deploy:fresh` | Envoy: pull + `migrate:fresh --seed` |

### Envoy deploy

Set in `.env`:

```env
DEPLOY_SERVER=user@your-server
DEPLOY_PATH=/var/www/chetnasharm
DEPLOY_BRANCH=mahmudul
DEPLOY_PHP=php
```

SSH access to the server must already work. Then:

```bash
composer deploy
composer deploy:fresh   # destructive: wipes DB and reseeds
```

## Important API groups

| Area | Examples |
|------|----------|
| Auth | `/api/login`, `/api/register`, `/api/auth/google` |
| Public | `/api/single-class/{id}`, `/api/batches/{classId}`, `/api/social-links`, `/api/support` |
| Admin | `/api/admin/*` (users, teachers, classes, batches, settings, env-settings) |
| Teacher | `/api/teacher/*` (availability, students, assignments, attendance) |
| Student | enrollment, payments, waitlist, recordings |

Integration credentials admin routes (DB-backed, not `.env`):

- `GET /api/admin/env-settings`
- `POST /api/admin/env-settings`

Social links:

- `GET /api/social-links`
- `GET /api/admin/social-links`
- `PUT /api/admin/social-links`

## Testing

```bash
php artisan test --compact
# or a single file
php artisan test --compact tests/Feature/EnvSettingsTest.php
```

## Project structure (high level)

```
app/
  Http/Controllers/Api/   # API controllers
  Models/                 # Eloquent models
  Notifications/          # Mail + WhatsApp reminders
  Jobs/                   # Scheduled reminder job
  Common/                 # Shared helpers (pagination, integrations, phone)
database/
  migrations/
  seeders/                # RolePermission, DemoData, etc.
routes/api.php
Envoy.blade.php           # Remote deploy tasks
```

## License

Proprietary — Chetnasharm / Listenact project.
