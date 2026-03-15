# Module 13 — Reports & Exports

**Status**: Draft / Awaiting Approval
**Depends on**: Modules 1-12 (all data models, sync chain, dashboards)

---

## Goal

Provide a dedicated **Reports** section where all three roles can preview, filter,
and download four types of structured reports in **Excel (.xlsx)** and **PDF**
formats. Reports are role-scoped: barangay staff see only their barangay's data;
admin and division chief see the full municipality.

---

## Packages Required

| Package | Version | Purpose |
|---------|---------|---------|
| `maatwebsite/laravel-excel` | `^3.1` | Excel export (`.xlsx`) |
| `barryvdh/laravel-dompdf` | `^3.0` | Server-side PDF generation |

Both will be installed via `composer require` during implementation.
`HouseholdExport.php` already exists and uses the Excel package — it will be
kept and reused.

---

## Report Types

### 1. Population Summary
A full breakdown of every barangay's population figures.

| Column | Source |
|--------|--------|
| Barangay | `barangays.name` |
| Total Population | `barangays.population` |
| Households | `barangays.household_count` |
| PWD | `barangays.pwd_count` |
| Senior (60+) | `barangays.senior_count` |
| Infant (0–2) | `barangays.infant_count` |
| Pregnant | `barangays.pregnant_count` |
| IP | `barangays.ip_count` |
| At-Risk | `barangays.at_risk_count` |

Filters: none (always all 22 barangays for admin/division; locked to own for barangay staff).
Exports: Excel, PDF.

---

### 2. Barangay Risk Analysis
A risk-oriented report showing hazard exposure per barangay.

| Column | Source |
|--------|--------|
| Barangay | `barangays.name` |
| Hazard Type | `hazard_types.name` |
| Risk Level | `hazard_zones.risk_level` |
| Zone Name | `hazard_zones.name` |
| Area (km²) | `hazard_zones.area_km2` |
| Affected Population | `hazard_zones.affected_population` |
| At-Risk Population | `barangays.at_risk_count` |

Grouped by barangay → hazard type → risk level.
Filters: barangay (select), hazard type (select), risk level (select).
Exports: Excel, PDF.

---

### 3. Household Data Export
Full household roster with family demographics. Reuses `HouseholdExport`.

Filters: barangay (select), IP/non-IP (select), has GPS coordinates (toggle).
Export: Excel only (already structured; no PDF for large tabular data).

---

### 4. Incident Summary
All incident reports with their affected area breakdown.

| Column | Source |
|--------|--------|
| Incident Title | `incident_reports.title` |
| Hazard Type | `hazard_types.name` |
| Status | `incident_reports.status` |
| Date | `incident_reports.incident_date` |
| Affected Barangays | `affected_areas` (count) |
| Affected Households | `affected_areas.affected_households` (sum) |
| Affected Population | `affected_areas.affected_population` (sum) |

Filters: status (select), date range (from/to), hazard type (select).
Exports: Excel, PDF.

---

## Controller

**`app/Http/Controllers/ReportController.php`**

```
index()          → report menu page
population()     → web preview + download links
risk()           → web preview + download links
households()     → web preview + download links
incidents()      → web preview + download links
exportExcel($type, Request)  → returns Excel download
exportPdf($type, Request)    → returns PDF download (inline or attachment)
```

Private helper `applyRoleScope(Builder $query): Builder` enforces barangay_staff
isolation — no duplicating this logic per method.

---

## Routes

```php
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/',                      [ReportController::class, 'index'])       ->name('index');
    Route::get('/population',            [ReportController::class, 'population'])  ->name('population');
    Route::get('/risk',                  [ReportController::class, 'risk'])        ->name('risk');
    Route::get('/households',            [ReportController::class, 'households'])  ->name('households');
    Route::get('/incidents',             [ReportController::class, 'incidents'])   ->name('incidents');
    Route::get('/{type}/excel',          [ReportController::class, 'exportExcel']) ->name('excel');
    Route::get('/{type}/pdf',            [ReportController::class, 'exportPdf'])   ->name('pdf');
});
```

All routes sit inside the existing `auth` + `active` middleware group.
Admin and Division Chief can access all four; Barangay Staff can access all four
but data is scoped to their barangay.

---

## Exports (app/Exports/)

| File | Report | Concerns |
|------|--------|----------|
| `HouseholdExport.php` | Households | already exists — add GPS filter |
| `PopulationExport.php` | Population Summary | `FromCollection`, `WithHeadings`, `WithStyles`, `WithTitle` |
| `RiskAnalysisExport.php` | Risk Analysis | `FromCollection`, `WithHeadings`, `WithStyles`, `WithGrouping` |
| `IncidentSummaryExport.php` | Incident Summary | `FromQuery`, `WithHeadings`, `WithMapping`, `WithStyles` |

---

## Views

```
resources/views/reports/
├── index.blade.php          ← 4 report cards with description + download buttons
├── population.blade.php     ← Bootstrap table preview, Excel/PDF buttons
├── risk.blade.php           ← Grouped table by barangay, filter sidebar
├── households.blade.php     ← Table preview + filters, Excel only
├── incidents.blade.php      ← Table preview + filters, Excel/PDF buttons
└── pdf/
    ├── population.blade.php ← Clean print layout, no navbar/sidebar
    ├── risk.blade.php       ← Clean print layout with risk-level colour bands
    └── incidents.blade.php  ← Clean print layout
```

PDF templates use an isolated layout (`layouts/pdf.blade.php`) — no sidebar,
no navbar, just a logo header, report title, generated-at timestamp, and a clean
Bootstrap table.

---

## Sidebar Integration

Add a **Reports** nav item in `components/sidebar.blade.php` (all roles):

```
Reports (fas fa-file-chart-column)
  → /reports
```

---

## Scope Rules Summary

| Role | Population | Risk | Households | Incidents |
|------|-----------|------|-----------|-----------|
| Admin | All 22 barangays | All | All | All |
| Division Chief | All 22 (read-only) | All | All | All |
| Barangay Staff | Own barangay only | Own barangay only | Own only | Incidents affecting own barangay |

---

## Out of Scope for This Module

- Scheduled / automated report delivery (email)
- Custom report builder / ad-hoc queries
- Charts inside PDF (DomPDF does not render Canvas)
- Any data entry or modification

---

## Open Questions Before Implementation

1. **PDF orientation** — landscape suits wide tables better; portrait is more
   standard. Preference?
2. **Company header on PDF** — should it include the municipality logo/seal or
   just text? (Logo would need an asset path.)
3. **Risk Analysis grouping** — rows per hazard zone (detailed) or one row per
   barangay + hazard type (aggregated)?  Aggregated is shorter; detailed gives
   full audit trail.
4. **Household PDF** — given potentially thousands of rows, we agreed to Excel
   only. Confirm this is acceptable or if a paginated PDF is needed too.
