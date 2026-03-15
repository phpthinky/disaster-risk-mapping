# Disaster Risk Mapping System
### Sablayan, Occidental Mindoro, Philippines

A web-based Disaster Risk Management Platform for the municipality of Sablayan. It manages 22 barangays, household data, hazard zones, population statistics, incident reports, and GIS mapping.

Built with **Laravel 11**, **Bootstrap 5**, **Leaflet.js**, and **Chart.js**.

---

## Quick Start

```bash
# 1. Install PHP dependencies
composer install

# 2. Install JS/CSS dependencies
npm install

# 3. Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# 4. Configure your database in .env, then run migrations and seeders
php artisan migrate --seed

# 5. Start the dev server (two terminals)
php artisan serve
npm run dev
```

> Default admin credentials are set in `database/seeders/UserSeeder.php`.

---

## Roles

| Role | Access |
|------|--------|
| `admin` | Full access to all modules |
| `division_chief` | Read-only aggregate views |
| `barangay_staff` | Own barangay data only |

---

## Key Concepts

### Sync Chain
All population figures are **auto-computed** — never entered manually. Every time a household or member is saved or deleted, the following chain runs automatically via `HouseholdObserver`:

```
recomputeHousehold()
  → syncBarangay()
    → syncPopulationData()
      → syncHazardZones()
```

### Population Archiving
There are two types of archive records in `population_data_archive`:

| Type | When | `snapshot_type` |
|------|------|----------------|
| **Auto** | Every household save/delete (continuous audit trail) | `auto` |
| **Annual** | Dec 31 via cron, or manually via admin button | `annual` |

The trend chart on the Population module uses only **annual** snapshots to show meaningful year-over-year data.

---

## Cron Job — Annual Population Snapshot

The system takes a deliberate year-end snapshot of every barangay's population on **Dec 31 at 23:55** automatically, using Laravel's scheduler.

### Step 1 — Add one line to the server's crontab

Open the crontab editor on the server:

```bash
crontab -e
```

Add this single line (replace the path with your actual project path):

```cron
* * * * * php /var/www/disaster-risk-mapping/artisan schedule:run >> /dev/null 2>&1
```

> This runs every minute. Laravel reads `routes/console.php` and decides whether it's time to run your scheduled commands — the annual snapshot will only fire once per year on Dec 31.

### Step 2 — Verify the schedule is registered

```bash
php artisan schedule:list
```

You should see `population:annual-snapshot` with a `Yearly on December 31 at 23:55` frequency.

### Step 3 — Test the command manually

```bash
# Snapshot all barangays right now (for testing or a mid-year manual save)
php artisan population:annual-snapshot

# Snapshot one specific barangay only
php artisan population:annual-snapshot --barangay=5
```

Snapshots are logged to `storage/logs/population-snapshot.log`.

### Step 4 — Confirm in the UI

Go to **Population Data → Details & History** for any barangay. In the **Archive Trail** table, filter by **Annual only** — you should see the new row highlighted in green with `archived_by = cron_annual`.

### Admin manual snapshot

If you need to save an annual snapshot at any time (e.g. before a major data update), log in as admin, go to **Population Data**, click a barangay's **Details & History** button, then click **Take Annual Snapshot** in the top-right corner.

---

## Modules

| # | Module | Status |
|---|--------|--------|
| 1 | Foundation & Database | ✅ Done |
| 2 | Authentication & Authorization | ✅ Done |
| 3 | Layout & UI Shell | ✅ Done |
| 4 | Barangay Management | ✅ Done |
| 5 | Household Management | ✅ Done |
| 6 | Hazard Zones | ✅ Done |
| 7 | Population Data | ✅ Done |
| 8 | Incident Reports | ⬜ Pending |
| 9 | Map View | ⬜ Pending |
| 10 | Alerts & Announcements | ⬜ Pending |
| 11 | Evacuation Centers | ⬜ Pending |
| 12 | Dashboards | ⬜ Pending |
| 13 | Reports & Exports | ⬜ Pending |
| 14 | User Management | ⬜ Pending |

See [`ROADMAP.md`](ROADMAP.md) and the [`modules/`](modules/) folder for detailed notes on each module.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 11 (PHP 8.2+) |
| Frontend | Bootstrap 5, Vite |
| Maps | Leaflet.js + Leaflet.Draw |
| Charts | Chart.js |
| Database | MySQL (production) / SQLite (development) |
| Auth | Laravel built-in + role middleware |
| GIS | GeoJSON stored in `text` columns; ray-casting point-in-polygon via `GeoService` |

---

## License

Internal municipal government system — not publicly licensed.
