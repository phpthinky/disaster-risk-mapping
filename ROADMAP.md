# Disaster Risk Mapping System — Laravel 11 Migration Roadmap

## Overview

This document tracks the migration of the legacy PHP application (`old-app/`) into a full Laravel 11 system. The application is a **Disaster Risk Management Platform** for the municipality of Sablayan, Occidental Mindoro, Philippines. It manages 22 barangays, household data, hazard zones, incident reports, population statistics, and GIS mapping.

---

## Architecture Decisions

- **Backend**: Laravel 11 (PHP 8.2+), Eloquent ORM, Blade templating
- **Frontend**: Bootstrap 5 + Leaflet.js (maps) + Chart.js (charts) + Vite
- **Database**: MySQL (production) / SQLite (development)
- **Auth**: Laravel's built-in auth with role-based middleware
- **Maps**: Leaflet.js with GeoJSON layers
- **Exports**: Laravel Excel (maatwebsite)

---

## Golden Rules (from old-app — must preserve in Laravel)

1. **No manual population entry** — all counts are computed from the `households` table via a sync chain.
2. **GPS coordinates are required** for new household entries (lat: 12.50–13.20, long: 120.50–121.20).
3. **Sync chain order**: `recompute_household → sync_barangay → sync_population_data → sync_hazard_zones`
4. **Population archive trail** — every update to `population_data` archives the old record.
5. **Role-based access**: `admin` (full), `barangay_staff` (own barangay), `division_chief` (read-only aggregate).

---

## Module List

| # | Module | Status | Description |
|---|--------|--------|-------------|
| 1 | Foundation & Database | ✅ Done | Migrations, Models, Seeders, Relationships |
| 2 | Authentication & Authorization | ✅ Done | Auth, Roles, Middleware, Policies |
| 3 | Layout & UI Shell | ✅ Done | Master layout, Sidebar, Navbar, Blade components |
| 4 | Barangay Management | ⬜ Pending | CRUD, GIS boundary drawing, stats |
| 5 | Household Management | ⬜ Pending | CRUD, family members, sync chain, GPS |
| 6 | Hazard Zones | ⬜ Pending | CRUD, GeoJSON layers, risk levels |
| 7 | Population Data | ⬜ Pending | Auto-computed view, archive trail |
| 8 | Incident Reports | ⬜ Pending | Polygon drawing, affected area computation |
| 9 | Map View | ⬜ Pending | Leaflet master map, all GIS layers |
| 10 | Alerts & Announcements | ⬜ Pending | CRUD, role targeting |
| 11 | Evacuation Centers | ⬜ Pending | CRUD, map markers |
| 12 | Dashboards | ⬜ Pending | Admin, Barangay, Division Chief dashboards |
| 13 | Reports & Exports | ⬜ Pending | PDF/Excel export, risk analysis |
| 14 | User Management | ⬜ Pending | Admin user CRUD, profile |

---

## Module Details

### Module 1 — Foundation & Database
**Goal**: Establish the full database schema in Laravel format, all Eloquent models with relationships, and the default seeder.

**Deliverables**:
- 10+ Laravel migrations matching old-app schema
- 13 Eloquent models with relationships and casts
- `SyncService` — the core computation engine (from `sync_functions.php`)
- `Household` model observer triggering sync chain automatically
- Default database seeder (22 barangays, 6 hazard types, 5 users)

---

### Module 2 — Authentication & Authorization
**Goal**: Secure the application with role-based access control.

**Deliverables**:
- Login / logout (reuse Laravel UI scaffold)
- `CheckRole` middleware for admin/barangay_staff/division_chief
- Gate policies for barangay-scoped access
- `auth.php` config update

---

### Module 3 — Layout & UI Shell ✅
**Goal**: Create a consistent, responsive layout matching the old-app's visual design.

**Deliverables**:
- `layouts/app.blade.php` — full shell: navbar + sidebar + flash messages + footer + `@stack('scripts')`
- `components/sidebar.blade.php` — collapsible dark sidebar with role-aware menu (3 sections, 12 items)
- `components/navbar.blade.php` — dark top navbar, user avatar dropdown, active-alerts badge
- `resources/sass/app.scss` — CSS variables, sidebar styles, stat-card styles, guest-wrapper, responsive rules
- Guest layout (login page) uses dark gradient background via `guest-wrapper`
- `login.blade.php` updated: icon branding, input-group fields, dark card
- Dashboard stubs updated to use `@section('page-header')` + breadcrumb pattern
- Bootstrap 5, Font Awesome 6, Leaflet, Chart.js loaded via CDN in master layout

---

### Module 4 — Barangay Management
**Goal**: Full barangay CRUD with GIS boundary drawing.

**Deliverables**:
- `BarangayController` (index, show, edit, update)
- `BarangayBoundaryController` (draw/save GeoJSON polygon)
- Barangay list/show/edit Blade views
- AJAX endpoints for boundary data

---

### Module 5 — Household Management
**Goal**: Household + family member CRUD with automatic sync chain.

**Deliverables**:
- `HouseholdController` (CRUD + export)
- `HouseholdMemberController` (AJAX add/delete)
- `HouseholdObserver` → triggers `SyncService::handleSync()`
- Household list/form Blade views
- Excel export via `HouseholdExport`

---

### Module 6 — Hazard Zones
**Goal**: Hazard zone management with map overlays.

**Deliverables**:
- `HazardZoneController` (CRUD)
- GeoJSON rendering on Leaflet layers
- Hazard stats AJAX endpoint
- Risk level color-coding

---

### Module 7 — Population Data
**Goal**: View auto-computed population data and full archive trail.

**Deliverables**:
- `PopulationDataController` (index, show)
- Archive history view per barangay
- Population data is read-only (computed by sync chain)

---

### Module 8 — Incident Reports
**Goal**: Create incident reports by drawing a polygon; system auto-computes affected households.

**Deliverables**:
- `IncidentReportController` (CRUD)
- Point-in-polygon service (ray casting algorithm)
- `AffectedAreaService::compute()`
- Polygon drawing UI (Leaflet draw plugin)
- Affected area breakdown per barangay

---

### Module 9 — Map View
**Goal**: Master interactive map with all GIS layers togglable.

**Deliverables**:
- `MapController`
- Leaflet map with layers: households, barangay boundaries, hazard zones, incident polygons, evacuation centers
- Layer controls + popups

---

### Module 10 — Alerts & Announcements
**Goal**: System-wide alerts and role-targeted announcements.

**Deliverables**:
- `AlertController`, `AnnouncementController` (CRUD)
- Active alerts displayed in navbar/dashboard
- Role-based announcement targeting

---

### Module 11 — Evacuation Centers
**Goal**: Manage evacuation centers with capacity tracking.

**Deliverables**:
- `EvacuationCenterController` (CRUD)
- Map pins for each center
- Capacity/occupancy status badges

---

### Module 12 — Dashboards
**Goal**: Role-specific dashboards with real-time statistics.

**Deliverables**:
- `DashboardController` (admin, barangay, division)
- Chart.js charts: hazard distribution, population breakdown
- Quick-stat cards (households, population, hazard zones, active incidents)

---

### Module 13 — Reports & Exports
**Goal**: Generate printable reports and exportable data.

**Deliverables**:
- `ReportController`
- Barangay risk analysis report
- Household data export (Excel)
- Population summary report (PDF)

---

### Module 14 — User Management
**Goal**: Admin-level user CRUD and profile management.

**Deliverables**:
- `UserController` (admin CRUD)
- `ProfileController` (update name, email, password)
- Assign barangay to barangay_staff users

---

## File Structure (Target)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                   (existing)
│   │   ├── BarangayController.php
│   │   ├── HouseholdController.php
│   │   ├── HazardZoneController.php
│   │   ├── IncidentReportController.php
│   │   ├── PopulationDataController.php
│   │   ├── MapController.php
│   │   ├── AlertController.php
│   │   ├── AnnouncementController.php
│   │   ├── EvacuationCenterController.php
│   │   ├── DashboardController.php
│   │   ├── ReportController.php
│   │   └── UserController.php
│   ├── Middleware/
│   │   └── CheckRole.php
│   └── Requests/
│       ├── StoreHouseholdRequest.php
│       └── StoreIncidentRequest.php
├── Models/
│   ├── User.php
│   ├── Barangay.php
│   ├── Household.php
│   ├── HouseholdMember.php
│   ├── HazardType.php
│   ├── HazardZone.php
│   ├── IncidentReport.php
│   ├── AffectedArea.php
│   ├── PopulationData.php
│   ├── PopulationDataArchive.php
│   ├── EvacuationCenter.php
│   ├── Alert.php
│   ├── Announcement.php
│   ├── DataEntry.php
│   └── BarangayBoundaryLog.php
├── Observers/
│   └── HouseholdObserver.php
└── Services/
    ├── SyncService.php
    └── IncidentAffectedAreaService.php
database/
├── migrations/
│   ├── (10 migrations converted to Laravel format)
└── seeders/
    ├── DatabaseSeeder.php
    └── DefaultSeeder.php
resources/views/
├── layouts/
│   └── app.blade.php
├── components/
│   ├── sidebar.blade.php
│   └── navbar.blade.php
├── barangays/
├── households/
├── hazards/
├── incidents/
├── population/
├── map/
├── alerts/
├── announcements/
├── evacuations/
├── dashboards/
├── reports/
└── users/
```

---

## Progress Log

| Date | Module | Action |
|------|--------|--------|
| 2026-03-14 | Roadmap | Created ROADMAP.md and module description files |
| 2026-03-14 | Module 1 | Migrations, Models, SyncService, Seeder |
| 2026-03-14 | Module 2 | Auth, CheckRole middleware, policies, username login |
| 2026-03-14 | Module 3 | Master layout, sidebar component, navbar component, SCSS styles |
