# Module 7 — Population Data

## Goal
Read-only views of auto-computed population data per barangay, with a full archive trail and a population trend chart. No manual entry — all figures come from the household sync chain.

---

## Routes

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | /population | population.index | admin, division_chief (barangay_staff redirected to show) |
| GET | /population/{barangay} | population.show | all roles (staff scoped to own barangay) |

---

## Files Created

```
app/Http/Controllers/PopulationDataController.php
resources/views/population/
  ├── index.blade.php
  └── show.blade.php
modules/module-7-population-data.md
```

---

## Business Rules

1. **Read-only** — no create/edit/delete; all population figures are computed by `SyncService`.
2. **Barangay staff** — redirect straight to `population.show` for their own barangay; cannot access index.
3. **Division chief and admin** — full index with all 22 barangays.
4. **Barangay staff visiting another barangay's show** — 403.
5. **Archive trail** — every time `SyncService::syncPopulationData()` runs, the old record is moved to `population_data_archive` before the current record is updated.

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
| `show(Barangay $barangay)` | Scope check for staff. Load current `PopulationData`, paginated archive (20/page), optional year filter, chart data (last 10 archive points reversed to chronological order) |

---

## Views

### population/index.blade.php
- **4 stat cards**: Total Population, Total Households, PWD Count, Barangay Count
- **Table** (all barangays): Barangay name, Population, Change vs previous (↑/↓ with colour), Households, Elderly, Children, PWD, IPs, Last Sync date, View button
- **Totals row** in `<tfoot>` summing numeric columns
- Change indicator uses most-recent `population_data_archive` record per barangay

### population/show.blade.php
- **Left card — Current Snapshot**: Total Population + Households (large numbers), then a breakdown table with percentage of total for each demographic group (elderly, children, infants, PWD, pregnant, IPs, solo parents, widows)
- **Right card — Trend Chart**: Chart.js dual-axis line chart (population left axis, households right axis) from the last 10 archive snapshots in chronological order
- **Archive Trail table**: Paginated (20/page), filterable by year; columns: Archived At, Population, Households, Elderly, Children, PWD, IPs, Archived By, Change Type badge (UPDATE/INSERT)

---

## Acceptance Criteria

- [x] All roles can access population data (staff scoped to own barangay)
- [x] Barangay staff visiting `/population` are redirected to their own barangay show page
- [x] No create/edit/delete UI — read-only banner displayed
- [x] Index shows all barangays with current figures and change vs previous
- [x] Change column shows green ↑ for increase, red ↓ for decrease, — if no previous
- [x] Footer totals row on index table
- [x] Show page displays full demographic breakdown with % of total
- [x] Chart.js trend line from archive history (last 10 snapshots)
- [x] Archive trail paginated (20/page) with year filter
- [x] Archive trail shows Archived By and Change Type badge
- [x] Alert shown when barangay has no population data yet
