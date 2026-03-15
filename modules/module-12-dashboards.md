# Module 12 — Dashboards

## Goal
Three role-specific dashboards providing each user type with a relevant overview of system data: quick-stat cards, Chart.js charts, recent activity feeds, and shortcut links.

---

## Routes

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | `/dashboard` | `dashboard` | admin (redirects from `/`) |
| GET | `/dashboard/division` | `dashboard.division` | admin, division_chief |
| GET | `/dashboard/barangay` | `dashboard.barangay` | admin, barangay_staff |

> Login redirect sends users to their role-appropriate dashboard automatically.

---

## Files Created / Modified

```
app/Http/Controllers/DashboardController.php   (stub exists — populate in this module)
resources/views/dashboards/
  ├── admin.blade.php
  ├── division.blade.php
  └── barangay.blade.php
modules/module-12-dashboards.md
```

---

## Business Rules

1. **Role redirect** — `LoginController` (or the root `/` route) sends each role to their own dashboard.
2. **No cross-role data** — the barangay dashboard only queries the authenticated user's `barangay_id`; the division dashboard shows municipality-wide aggregates.
3. **Charts are client-side** — Chart.js data is serialised via `@json()` in the blade template; no separate AJAX chart endpoints.
4. **Real-time counts** — all stat cards pull live Eloquent counts on each page load; no caching.

---

## Dashboard: Admin (`dashboards/admin.blade.php`)

### Stat Cards (row of 6)
| Card | Value |
|------|-------|
| Total Barangays | `Barangay::count()` |
| Total Households | `Household::count()` |
| Total Population | `Barangay::sum('population')` |
| Active Incidents | `IncidentReport::whereIn('status',['ongoing','monitoring'])->count()` |
| Hazard Zones | `HazardZone::count()` |
| Evacuation Centers | `EvacuationCenter::where('status','operational')->count()` |

### Charts
- **Hazard Zone Distribution** (doughnut/pie) — count of zones per hazard type, coloured by `hazard_types.color`.
- **Top 10 Barangays by Population** (horizontal bar) — `Barangay::orderByDesc('population')->limit(10)`.

### Additional Panels
- **Recent Incident Reports** — last 5 incidents with status badge, date, disaster type.
- **Active Alerts** — list of active alerts; "No active alerts" if empty.
- **Quick Links** — shortcut buttons to Add Household, Add Hazard Zone, Create Incident Report, View Map.

---

## Dashboard: Division Chief (`dashboards/division.blade.php`)

### Stat Cards (row of 5)
| Card | Value |
|------|-------|
| Total Population | `Barangay::sum('population')` |
| At-Risk Population | `Barangay::sum('at_risk_count')` |
| Active Incidents | `IncidentReport::whereIn('status',['ongoing','monitoring'])->count()` |
| Evac Centers (operational) | `EvacuationCenter::where('status','operational')->count()` |
| Total Evac Capacity | `EvacuationCenter::where('status','operational')->sum('capacity')` |

### Charts
- **Vulnerable Population by Barangay** (grouped bar) — PWD, Elderly, Infant, Pregnant per barangay.
- **Hazard Zone Count by Barangay** (horizontal bar).

### Additional Panels
- **Barangay Summary Table** — all 22 barangays: name, population, households, at-risk count, hazard zones, incident count.
- **Active Incidents** — last 5 ongoing/monitoring incidents.

---

## Dashboard: Barangay Staff (`dashboards/barangay.blade.php`)

> All data scoped to `auth()->user()->barangay_id`.

### Stat Cards (row of 5)
| Card | Value |
|------|-------|
| Total Households | `Household::where('barangay_id', $bid)->count()` |
| Total Population | `$barangay->population` |
| At-Risk Population | `$barangay->at_risk_count` |
| Hazard Zones | `HazardZone::where('barangay_id', $bid)->count()` |
| Active Incidents | incidents affecting this barangay |

### Charts
- **Population Breakdown** (doughnut) — PWD, Elderly, Infants, Pregnant, IP vs general population.
- **Hazard Zones by Type** (bar) — count per hazard type within this barangay.

### Additional Panels
- **Recent Households** — last 5 households added in this barangay with GPS badge.
- **Evacuation Centers** — list of this barangay's evac centers with status + capacity.
- **Quick Links** — Add Household, View Households, View Map.

---

## Acceptance Criteria

- [x] Admin dashboard shows system-wide stat cards, two Chart.js charts, recent incidents, active alerts
- [x] Division chief dashboard shows municipality-wide stats, vulnerable population chart, barangay summary table
- [x] Barangay staff dashboard shows own-barangay stats, population breakdown chart, recent households
- [x] All stat values are live database counts (no hard-coded data)
- [x] Chart.js data passed safely via `@json()` (no XSS risk)
- [x] Role-based routing: each role is directed to their own dashboard on login
- [x] Quick-link buttons on each dashboard for the most common actions
- [x] "No data" states handled gracefully (empty charts, empty tables)
