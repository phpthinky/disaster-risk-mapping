# Module 7 — Population Data

## Goal
Read-only views of auto-computed population data per barangay, with a full archive trail, annual snapshot system, and a population trend chart. No manual entry — all figures come from the household sync chain.

---

## Routes

| Method | URI | Name | Middleware | Access |
|--------|-----|------|-----------|--------|
| GET | `/population` | `population.index` | auth | admin, division_chief (barangay_staff redirected to show) |
| GET | `/population/{barangay}` | `population.show` | auth | all roles (staff scoped to own barangay) |
| POST | `/population/{barangay}/snapshot` | `population.snapshot` | auth, role:admin | admin only |

---

## Files Created / Modified

```
app/Http/Controllers/PopulationDataController.php
app/Console/Commands/PopulationAnnualSnapshot.php   ← artisan command
app/Models/PopulationDataArchive.php                ← added snapshot_type to $fillable
app/Services/SyncService.php                        ← tags auto-archive with snapshot_type='auto'
routes/console.php                                  ← yearly schedule (Dec 31 23:55)
routes/web.php                                      ← snapshot POST route added
resources/views/population/
  ├── index.blade.php
  └── show.blade.php
database/migrations/
  └── 2026_03_15_024342_add_snapshot_type_to_population_data_archive.php
modules/module-7-population-data.md
```

---

## Business Rules

1. **Read-only** — no create/edit/delete UI; all population figures are computed by `SyncService`.
2. **Barangay staff** — redirect straight to `population.show` for their own barangay; cannot access index.
3. **Division chief and admin** — full index with all 22 barangays.
4. **Barangay staff visiting another barangay's show** — 403 Forbidden.
5. **Auto archive** — every time `SyncService::syncPopulationData()` runs (on every household save/delete), the old record is copied to `population_data_archive` with `snapshot_type = 'auto'`.
6. **Annual snapshot** — a deliberate year-end copy saved with `snapshot_type = 'annual'`. Can be triggered by an admin via the UI button, or automatically by the Laravel scheduler every Dec 31.
7. **Trend chart** — uses only `annual` snapshots (last 10) to show meaningful year-over-year population change.

---

## Archive Snapshot Types

| `snapshot_type` | Created by | Frequency | Purpose |
|-----------------|-----------|-----------|---------|
| `auto` | `SyncService::syncPopulationData()` | Every household save/delete | Continuous change audit trail |
| `annual` | Admin button or `population:annual-snapshot` cron | Once per year | Year-over-year comparison; drives trend chart |

---

## Data Sources

| Field | Source |
|-------|--------|
| `total_population` | `barangays.population` (sum of all household `family_members`) |
| `households` | `barangays.household_count` |
| `elderly_count` | `barangays.senior_count` (members aged 60+) |
| `children_count` | `barangays.children_count` (members aged 3–12) |
| `infant_count` | `barangays.infant_count` (members aged 0–2) |
| `pwd_count` | `barangays.pwd_count` |
| `pregnant_count` | `barangays.pregnant_count` |
| `ip_count` | `barangays.ip_count` |
| `solo_parent_count` / `widow_count` | `population_data` record (set during sync) |

---

## PopulationDataController Methods

| Method | Logic |
|--------|-------|
| `index()` | Barangay staff → redirect to own `show`. Others: load all barangays + current `PopulationData` keyed by `barangay_id` + most-recent archive per barangay for "previous" comparison + summary totals |
| `show(Barangay $barangay)` | Scope check for staff. Load current `PopulationData`, paginated archive (20/page), optional year + type filter, chart data (last 10 *annual* snapshots in chronological order) |
| `snapshot(Barangay $barangay)` | Admin only. Reads current `population_data` record and writes a new `population_data_archive` entry with `snapshot_type = 'annual'`. Redirects back with flash message. |

---

## Artisan Command — `population:annual-snapshot`

```bash
# Snapshot all barangays
php artisan population:annual-snapshot

# Snapshot one barangay only (by ID)
php artisan population:annual-snapshot --barangay=5
```

- Reads the current `population_data` record for each barangay
- Writes a `population_data_archive` row with `snapshot_type = 'annual'`, `change_type = 'ANNUAL'`, `archived_by = 'cron_annual'`
- Skips barangays that have no `population_data` record yet
- Returns `Command::SUCCESS` / `Command::FAILURE`
- Output is logged to `storage/logs/population-snapshot.log` when run via the scheduler

---

## Scheduler (Laravel 11)

Defined in `routes/console.php`:

```php
Schedule::command('population:annual-snapshot')
    ->yearlyOn(12, 31, '23:55')   // Dec 31 at 23:55
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/population-snapshot.log'));
```

> See the README for crontab setup instructions.

---

## Views

### population/index.blade.php
- **Info banner** — explains the difference between auto and annual archiving; links to "Details & History"
- **4 stat cards**: Total Population, Total Households, PWD Count, Barangay Count
- **Table** (all barangays): Barangay name, Population, Change vs previous (↑/↓ with colour), Households, Elderly, Children, PWD, IPs, Last Sync date, "Details & History" button
- **Totals row** in `<tfoot>` summing numeric columns

### population/show.blade.php
- **Header actions** (admin only): "Take Annual Snapshot" button → POST to `population.snapshot`
- **Left card — Current Snapshot**: Total Population + Households (large numbers), then a breakdown table with percentage of total for each demographic group (elderly, children, infants, PWD, pregnant, IPs, solo parents, widows)
- **Right card — Trend Chart**: Chart.js dual-axis line chart (population left axis, households right axis) from the last 10 **annual** snapshots in chronological order. Empty state includes a "Take First Snapshot" CTA for admins.
- **Archive Trail table**: Paginated (20/page), filterable by year **and** type (All / Annual only / Auto only). Columns: Archived At, **Type** badge (green Annual / grey Auto), Population, Households, Elderly, Children, PWD, IPs, Archived By. Annual rows are highlighted green.

---

## Acceptance Criteria

- [x] All roles can access population data (staff scoped to own barangay)
- [x] Barangay staff visiting `/population` are redirected to their own barangay show page
- [x] No create/edit/delete UI — read-only banner displayed
- [x] Index shows all barangays with current figures and change vs previous
- [x] Change column shows green ↑ for increase, red ↓ for decrease, — if no previous
- [x] Footer totals row on index table
- [x] Show page displays full demographic breakdown with % of total
- [x] Chart.js trend line from last 10 **annual** snapshots (year-over-year)
- [x] Archive trail paginated (20/page) with year + type filter
- [x] Archive trail shows Type badge (Annual/Auto), Archived By
- [x] Annual snapshot rows highlighted green in archive trail
- [x] Admin can take manual annual snapshot via "Take Annual Snapshot" button
- [x] `population:annual-snapshot` artisan command works with optional `--barangay` flag
- [x] Yearly schedule registered in `routes/console.php` (Dec 31 23:55)
- [x] Alert shown when barangay has no population data yet
- [x] Empty chart state shows "Take First Snapshot" CTA for admins
