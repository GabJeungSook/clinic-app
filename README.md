# Aesthetic Clinic Management System

Offline-first management system for a medical aesthetic clinic — patients, services,
multi-session treatments, inventory (with batch/expiry + FEFO), purchasing, billing
(tax + promos), reports, and settings. Built with **Laravel 13 + Inertia + Vue 3 +
TypeScript + Tailwind + shadcn-vue**, running on **SQLite** so it works fully offline
on a single PC. The architecture (ULID keys, per-branch scoping, ledger-based
inventory) is ready to migrate online later.

---

## Running it (Windows, offline)

### First-time setup
Double-click **`Setup-Clinic.bat`** (or run it in a terminal). It creates the
database, loads starter data, and builds the interface.

> Requires PHP 8.3+ and Node.js on the machine. This project was built with the
> [Laravel Herd](https://herd.laravel.com) PHP runtime, which puts `php` on the PATH.

### Launch
Double-click **`Start-Clinic.bat`**. It starts the local server and opens the app in
your browser at <http://127.0.0.1:8000>. Keep the small window open while using the
system; close it to stop.

### Login
- **Admin (full access):** `admin` / `password`
- **Receptionist (front desk):** `reception` / `password` — sees Dashboard, Appointments, Patients, Point of Sale, Reports only

> Add more staff and set their roles under **Staff** (admin only).

> Change this password after first login (top-right menu → Settings → Security).

### Services catalogue
Skinthera's real service menu (72 services across 14 categories: Botox, Pico Laser,
Diode, HIFU, Fillers, Drips, etc.) is loaded automatically. **Set each service's price
and number of sessions** under **Services → Edit** — they seed at ₱0 / 1 session.
To re-load the catalogue after a reset:
```
php artisan db:seed --class=SkintheraServicesSeeder
```

### Sample data (for testing reports & charts)
To fill the system with a realistic, backdated dataset — ~24 patients, inventory
with batches (incl. low-stock and expiring), suppliers/purchases, treatment courses
with performed sessions, invoices/payments spread over the last month, and past +
upcoming **appointments** — run:
```
php artisan db:seed --class=SampleDataSeeder
```
This populates every report and the dashboard charts. When you're ready to go live
with a clean database, reset with:
```
php artisan migrate:fresh --seed
php artisan db:seed --class=SkintheraServicesSeeder
```

---

## Running it (developer mode)

```bash
composer install
npm install
php artisan migrate --seed
npm run dev        # in one terminal (hot reload)
php artisan serve  # in another terminal
```

Run the test suite:
```bash
php artisan test
```

---

## Daily operations

- **Backups (important — this PC holds the only data):**
  `php artisan clinic:backup` creates a snapshot in `storage/app/backups`. The app also
  runs this automatically each night (23:30) if the Windows Task Scheduler runs
  `php artisan schedule:run` every minute. **Copy backups to a USB drive or cloud
  folder regularly.** You can also back up on demand from **Settings → Backups**.
- **Integrity check:** `php artisan clinic:reconcile` recomputes stock/paid caches from
  the ledgers and reports any drift (runs nightly at 23:45).

---

## Going online later

The same codebase becomes the online, multi-branch app:
1. Deploy to a server and point `DB_CONNECTION` at MySQL/Postgres.
2. `php artisan migrate` (schema is driver-agnostic; ULID keys avoid branch-merge
   collisions).
3. Enable the multi-branch scope by giving each user a branch, add the booking and
   per-branch reporting modules. No rewrite — business logic already lives in
   `app/Actions` behind an HTTP layer.

---

## Desktop app (NativePHP Desktop v2)

The app ships as a real Windows desktop app via **[NativePHP Desktop v2](https://nativephp.com)**
(`nativephp/desktop`, free/OSS, Laravel-13-compatible). It bundles PHP + SQLite, stores
the database in the user's **appData** folder, and **runs migrations automatically when
the app version changes**. The `.bat` launchers above are now a dev-only fallback.

### Prerequisites (build machine)
PHP 8.3+, **Node 22+**, and internet for the first build (downloads the PHP + Electron
runtimes).

### Build the installer
```powershell
php artisan native:build win x64
```
This compiles the frontend (`npm run build` runs automatically via the `prebuild` hook),
bundles the app, and produces a Windows installer under **`nativephp/electron/dist/`**
(e.g. `Skinthera Medical Aesthetic-1.0.0-setup.exe`, ~120 MB).
Run `php artisan native:run` first to smoke-test the app in a native window.

On first launch the app creates its database in appData and seeds the demo-free baseline
(`ProductionSeeder`: branch, roles, units, settings, the `admin` login, and the real
service menu) — see `app/Providers/NativeAppServiceProvider.php`.

### Releasing an update (offline, manual)
1. Make your code + migration changes (additive/reversible — never drop patient/financial data).
2. Bump the version: `NATIVEPHP_APP_VERSION` in `.env` (or `config/nativephp.php`). **This is
   what triggers the on-device migration.**
3. `php artisan native:build win x64` → new installer in `nativephp/electron/dist/`.
4. Copy the installer to the clinic PC (USB/download) and run it. It replaces the app and,
   because the version changed, **auto-migrates the existing appData database** — patient
   data is preserved. The app also takes a **backup before migrating** (`clinic:backup`), so
   a bad update can be rolled back by restoring the latest `.sqlite` snapshot.

> Auto-update over the internet is disabled (`NATIVEPHP_UPDATER_ENABLED=false`) to keep the
> clinic fully offline. To enable it later, set a provider (GitHub Releases / S3) in
> `config/nativephp.php` and re-enable the flag.
