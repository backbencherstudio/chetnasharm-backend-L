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
- Node.js (for Vite when using `php artisan dev`)
- Queue worker (included via `php artisan dev`)
- Scheduler in production (`php artisan schedule:run` via cron)

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

### Run locally

Laravel 13 provides `php artisan dev`, which starts the local server, queue worker, log tailing (Pail), and Vite together:

```bash
php artisan dev
```

List registered processes:

```bash
php artisan dev:list
```

API base URL: `http://localhost:8000/api`

## Testing

```bash
php artisan test --compact
# or a single file
php artisan test --compact tests/Feature/EnvSettingsTest.php
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
```

## License

Proprietary — Chetnasharm / Listenact project.
