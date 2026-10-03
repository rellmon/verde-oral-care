# Verde Oral Care

An appointment booking system for a fictional dental clinic. Patients book online, dentists manage their day, and clinic staff run the schedule.

This is a portfolio and learning project: a **Laravel REST API** with a separate **React** frontend, built step by step.

## What it does

**Patients** can create an account, browse services and dentists, see the open times for a dentist on a chosen day, book an appointment, follow its status, cancel it (up to 24 hours before it starts), and edit their profile.

**Dentists** see today's, upcoming, and past appointments, confirm them, mark them completed or no-show, write private notes, and view their own profile and working hours.

**Admins** get a dashboard with live numbers and can manage every appointment (filter, confirm, cancel, complete, reschedule), look up patients and their history, and manage dentists, their weekly working hours, and the list of services.

Public visitors can browse the home page, services, dentists (with their weekly hours), and the About and Contact pages.

## Tech stack

| Part | Technology |
| --- | --- |
| Backend | Laravel (PHP), REST API |
| Authentication | Laravel Sanctum API tokens |
| Database | MySQL |
| Frontend | React with Vite, React Router |
| Styling | Tailwind CSS v4 |
| HTTP client | Axios |
| Tests | PHPUnit feature tests (backend) |
| Code style | Laravel Pint (PHP), ESLint (JavaScript) |

## Getting started

You need PHP 8.3 or newer, Composer, Node.js with npm, and MySQL.

### 1. Create the database

In MySQL, create a database and a user for it (choose your own password):

```sql
CREATE DATABASE verde_oral_care CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'verde_user'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON verde_oral_care.* TO 'verde_user'@'localhost';
```

### 2. Start the API

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Open `backend/.env` and set the database and timezone:

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=verde_oral_care
DB_USERNAME=verde_user
DB_PASSWORD=choose-a-password
APP_TIMEZONE=Asia/Manila
```

Set `APP_TIMEZONE` to the clinic's timezone. "Today" and "times that have already passed" depend on it.

Then create the tables, add the demo data, and start the server:

```bash
php artisan migrate --seed
php artisan serve
```

The API is now at `http://127.0.0.1:8000/api`.

### 3. Start the frontend

In a second terminal:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Open `http://localhost:5173`. The frontend reads the API address from `VITE_API_URL` in `frontend/.env`.

### Demo accounts

`php artisan migrate --seed` creates these accounts. The password for all of them is `password`. They are for local development only.

| Role | Email |
| --- | --- |
| Admin | `admin@verdeoralcare.test` |
| Patient | `patient@verdeoralcare.test` |
| Dentist | `maria.santos@verdeoralcare.test` |
| Dentist | `rafael.cruz@verdeoralcare.test` |
| Dentist | `angela.reyes@verdeoralcare.test` |
| Dentist | `paolo.lim@verdeoralcare.test` |

The seeder also adds eight dental services, four dentists with weekly hours (Monday to Friday 9:00 to 17:00, Saturday 9:00 to 13:00, Sunday closed), and some sample patients.

## Running the tests

The tests run against their own database. They empty it every time, so it must never be the development database. A guard in `tests/TestCase.php` refuses to run unless the database name ends in `_test`.

```sql
CREATE DATABASE verde_oral_care_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON verde_oral_care_test.* TO 'verde_user'@'localhost';
```

```bash
cd backend
php artisan test
```

The connection details (host, user, password) come from `backend/.env`, and `phpunit.xml` only chooses the database name. The tests use MySQL rather than SQLite on purpose: the booking code relies on MySQL's row locking and date handling, and tests should run on the same engine as the real app.

Code style checks:

```bash
cd backend && ./vendor/bin/pint --test
cd frontend && npm run lint
```

## Project structure

```text
verde-oral-care/
├── backend/                         Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/    controllers (Admin/ holds staff-only ones)
│   │   ├── Http/Requests/           validation rules
│   │   ├── Http/Resources/          decide exactly which fields each response contains
│   │   ├── Http/Middleware/         EnsureUserHasRole (the "role:" middleware)
│   │   ├── Models/                  Eloquent models and relationships
│   │   ├── Policies/                ownership rules (whose appointment is it?)
│   │   └── Services/                AvailabilityService (which times are free?)
│   ├── database/                    migrations, factories, seeders
│   ├── routes/api.php               every endpoint and what protects it
│   └── tests/Feature/               the automated tests
└── frontend/                        React app
    └── src/
        ├── api/                     the configured Axios instance
        ├── components/              reusable pieces (modal, dialogs, cards, forms)
        ├── context/                 authentication state
        ├── pages/                   one folder per role: public, patient, dentist, admin
        └── utils/                   formatting helpers and role routing
```

## API overview

All routes start with `/api`. Everything except the public routes needs the header `Authorization: Bearer <token>`, which `POST /login` returns.

**Public**

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/register` | Create a patient account |
| POST | `/login` | Log in and receive a token |
| GET | `/services` | Active services |
| GET | `/dentists` | Active dentists |
| GET | `/dentists/{id}` | One dentist with their weekly hours |
| GET | `/dentists/{id}/availability?service_id=&date=` | Free start times for that day |

**Any logged-in user:** `POST /logout`, `GET /user`

**Patient**

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/appointments?type=upcoming\|history` | My appointments |
| POST | `/appointments` | Book an appointment |
| PATCH | `/appointments/{id}/cancel` | Cancel my appointment |
| GET, PUT | `/patient/profile` | View and update my profile |

**Dentist**

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/dentist/appointments?type=today\|upcoming\|history` | My appointments |
| PATCH | `/dentist/appointments/{id}` | Change status and/or notes |
| GET | `/dentist/profile` | My profile and working hours |

**Admin**

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/admin/stats` | Dashboard numbers |
| GET | `/admin/appointments` | All appointments, with filters and pages |
| PATCH | `/admin/appointments/{id}/status` | Confirm, complete, no-show, cancel |
| GET | `/admin/appointments/{id}/slots?date=` | Times an appointment could move to |
| PATCH | `/admin/appointments/{id}/reschedule` | Move an appointment |
| GET, POST | `/admin/dentists` | List and create dentists |
| PUT | `/admin/dentists/{id}` | Edit a dentist (including activating or deactivating) |
| GET, PUT | `/admin/dentists/{id}/schedules` | Read and replace weekly working hours |
| GET | `/admin/patients`, `/admin/patients/{id}` | Search patients, view one with history |
| GET, POST | `/admin/services` | List and create services |
| PUT, DELETE | `/admin/services/{id}` | Edit or delete a service |

## How it works

**The server decides everything that matters.** The patient on a booking comes from the login token, never from the request. The status of a new appointment, its end time, and a user's role are set in code, and those fields are not mass-assignable, so extra fields in a request are ignored. React shows buttons and messages, but Laravel makes every decision.

**Double booking is prevented in one place.** `AvailabilityService` works out the free start times from the dentist's weekly hours, the service's duration, and the existing appointments. The public availability endpoint and the booking endpoint both use it. Booking re-checks the chosen slot inside a database transaction while holding a row lock on the dentist, so two patients choosing the same slot at the same moment cannot both succeed. Rescheduling uses the same service and lock (ignoring the appointment being moved, so it does not block itself).

**Authorization has three layers.** `auth:sanctum` proves who you are. The `role:` middleware decides which group of routes you can enter. Policies decide whether a specific record is yours: a patient cancels only their own appointments, and a dentist updates only theirs.

**Business rules live in the model, and the API tells the UI what is possible.** The allowed status changes (pending to confirmed, confirmed to completed, and so on) are one table on the `Appointment` model. Responses include `available_statuses` and `can_cancel`, so the React pages hold no copy of the rules and cannot drift from them.

**Responses expose only what each screen needs.** API Resources control the fields. The public dentist list has no email or license number, the patient list has no address or date of birth, and a dentist's private notes never appear in a patient's responses. The `/user` response contains five fields.

**Hardening.** Login is limited to 5 attempts a minute, registration to 10 an hour, and public reads to 120 a minute, each counted separately. Login tokens expire after 7 days, and the frontend sends the person to the login page with a message when one is rejected. A wrong login gives the same message whether or not the email exists. CORS allows only the frontend's address, set in `backend/.env`.

**Performance.** Related records are eager loaded, and in development Laravel is set to throw an error on any lazy load in a list, so a hidden N+1 query cannot go unnoticed. The appointments table has indexes matching the real queries, and date filters use plain comparisons so those indexes can be used.

**Frontend.** An auth context holds the logged-in user. An Axios interceptor adds the token and handles rejected tokens in one place. Protected routes check the role. Every page handles loading, error, and empty states. Search is debounced, and responses that arrive after the inputs changed are ignored.

## Testing

The feature tests send real requests through the whole application: routes, middleware, validation, controllers, and a real MySQL database. They cover:

- **Booking:** a free slot can be booked, the same slot or an overlapping one cannot, back-to-back is allowed, past dates, closed days, off-grid times, and times outside working hours are refused, and only patients can book.
- **Cancellation and status changes:** ownership, the 24-hour boundary, every forbidden status change (for both dentist and admin), and private notes.
- **Rescheduling, authentication, and registration:** including token expiry and the separate rate limits.
- **Route protection:** one test reads the router and checks that every protected route refuses guests with 401 and the wrong roles with 403, and that exactly the six intended routes are public.
- **Admin, public, and profile endpoints:** statistics, search, privacy of each response, working hours, and validation messages.

A fixed clock is used wherever dates matter, so the tests give the same result on any day.

## Known limitations

- **No email notifications yet.** Patients see status changes the next time they open the app. If notifications are added they will be email only.
- **No dentist photos.** The database has a `profile_image` column, but the app shows initials.
- **Admins cannot edit patient details or delete patients and dentists.** Dentists are deactivated instead, which keeps their appointment history.
- **The login token is kept in the browser's `localStorage`.** That is simple and common for a single-page app, but a cross-site scripting bug could expose it. The frontend never inserts raw HTML, which is the main protection. HttpOnly cookies are the alternative.
- **One clinic, and a fixed 30-minute grid.** Start times fall every half hour, and the service's duration decides how long the appointment lasts. Rescheduling keeps the same dentist and service.
- **The booking lock is not covered by a concurrency test.** The tests prove the rules one request after another. They cannot prove two requests at the same instant.
- **No frontend tests.** The rules live in the API and are tested there. The pages were checked by hand, including at phone width.

## Before deploying

These settings must change for a real server:

- `APP_ENV=production` and `APP_DEBUG=false` (debug mode prints stack traces)
- `CORS_ALLOWED_ORIGINS` set to the real frontend address
- `VITE_API_URL` set to the real API address, then `npm run build`
- A strong, unique database password, and HTTPS on both sites
- Do not run the demo seeder in production