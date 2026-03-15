# Module 13 — Reports & Exports

**Status**: Implemented
**Depends on**: Modules 1-12 (all data models, sync chain, dashboards)

---

## Goal

Provide a dedicated **Reports** section with two sub-sections:

1. **Tabular Reports** — filter, preview, and download data in Excel (.xlsx) and PDF formats, role-scoped.
2. **Graphical Reports** — interactive Chart.js charts with year-by-year (5-year comparison) and month-by-month (1-year breakdown) filtering, updated via AJAX without page reload.

---

## Packages

| Package | Version | Purpose |
|---------|---------|---------|
| `rap2hpoutre/fast-excel` | `^5.6` | Excel export using collections — Laravel 11 compatible |
| `barryvdh/laravel-dompdf` | `^3.1` | Server-side PDF generation |

---

## Tabular Report Types

### 1. Population Summary
Full population breakdown per barangay from live `barangays` fields.

Filters: none (role-scoped automatically).
Exports: Excel, PDF (landscape A4).

### 2. Barangay Risk Analysis
Hazard zones grouped by barangay → hazard type → risk level.

Filters: barangay, hazard type, risk level.
Exports: Excel, PDF (landscape A4).

### 3. Household Data Export
Full household roster. Excel only (too wide for PDF).

Filters: barangay, IP/non-IP, has GPS.
Export: Excel only.

### 4. Incident Summary
Incident reports with affected area aggregates.

Filters: status, date range (from/to), hazard type.
Exports: Excel, PDF (landscape A4).

---

## Graphical Report Types

All charts use **Chart.js 4** loaded from CDN. Each chart page has an AJAX-powered
control bar at the top. The page loads with default data; changing any filter
sends a `fetch()` request to the JSON endpoint and calls `chart.data = ...; chart.update()`.

### Chart 1 — Population Trend (Annual only)
- **Chart type**: Line chart
- **X-axis**: Barangay names
- **Series**: One line per selected year (up to 5)
- **Mode**: Annual only (population data is annual)
- **Filters**: Year checkboxes (up to 5 years), Barangay selector
- **Data source**: `population_data.total_population` grouped by `YEAR(data_date)`

### Chart 2 — Vulnerability Overview (Annual only)
- **Chart type**: Grouped bar chart
- **X-axis**: Selected years
- **Series**: PWD, Seniors (Elderly), Children, At-Risk, IP — one bar group per year
- **Mode**: Annual only
- **Filters**: Year checkboxes (up to 5), Barangay selector
- **Data source**: `population_data` aggregated by `YEAR(data_date)`

### Chart 3 — Incident Frequency (Annual + Monthly)
- **Chart type**: Stacked bar chart
- **Annual mode**: X = selected years, series = hazard types
- **Monthly mode**: X = Jan–Dec, series = hazard types, for one selected year
- **Filters (annual)**: Year checkboxes (up to 5)
- **Filters (monthly)**: Year selector (single)
- **Data source**: `incident_reports.incident_date`

### Chart 4 — Household Registrations (Annual + Monthly)
- **Chart type**: Bar chart
- **Annual mode**: X = selected years, Y = households registered per year
- **Monthly mode**: X = Jan–Dec, Y = households registered per month
- **Filters**: Mode toggle, year checkboxes or single year selector, Barangay
- **Data source**: `households.created_at`

### Chart 5 — Hazard Exposure (Static snapshot)
- **Chart type**: Doughnut (risk level distribution) + horizontal bar (top barangays by hazard area)
- **No time dimension** — always shows current snapshot
- **No AJAX** — rendered server-side on page load
- **Data source**: `hazard_zones`

---

## Interactive Filter Control Bar

```
┌─────────────────────────────────────────────────────────────────────┐
│ [View: Annual ▼]  [2021✓] [2022✓] [2023✓] [2024✓] [2025✓]  [Barangay: All ▼] │
└─────────────────────────────────────────────────────────────────────┘

When mode = Monthly:
┌─────────────────────────────────────────────────────────────────────┐
│ [View: Monthly ▼]  [Year: 2025 ▼]  [Barangay: All ▼]              │
└─────────────────────────────────────────────────────────────────────┘
```

Switching mode/filters triggers `fetch()` → JSON endpoint → `chart.update()`.

---

## Controller: `app/Http/Controllers/ReportController.php`

```
index()                           → reports hub
population(Request)               → tabular view
risk(Request)                     → tabular view with filters
households(Request)               → tabular view with filters + pagination
incidents(Request)                → tabular view with filters + pagination
exportExcel(string $type, Request)→ FastExcel download
exportPdf(string $type, Request)  → DomPDF download

graphical()                       → graphical hub
graphicalPopulation()             → chart view
graphicalPopulationData(Request)  → JSON for AJAX
graphicalVulnerability()          → chart view
graphicalVulnerabilityData(Request) → JSON for AJAX
graphicalIncidents()              → chart view
graphicalIncidentsData(Request)   → JSON for AJAX
graphicalHouseholds()             → chart view
graphicalHouseholdsData(Request)  → JSON for AJAX
graphicalHazards()                → static chart view (no AJAX)

private applyBarangayScope(Builder, string $col)
private staffBarangayId(): ?int
private scopedBarangays(): Collection
```

---

## Routes

```php
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/',                          'index')             → reports.index
    Route::get('/population',                'population')        → reports.population
    Route::get('/risk',                      'risk')              → reports.risk
    Route::get('/households',                'households')        → reports.households
    Route::get('/incidents',                 'incidents')         → reports.incidents
    Route::get('/{type}/excel',              'exportExcel')       → reports.excel
    Route::get('/{type}/pdf',                'exportPdf')         → reports.pdf

    Route::get('/graphical',                 'graphical')         → reports.graphical
    Route::get('/graphical/population',      'graphicalPopulation')     → reports.graphical.population
    Route::get('/graphical/population/data', 'graphicalPopulationData') → reports.graphical.population.data
    Route::get('/graphical/vulnerability',   'graphicalVulnerability')  → reports.graphical.vulnerability
    Route::get('/graphical/vulnerability/data', 'graphicalVulnerabilityData') → ...
    Route::get('/graphical/incidents',       'graphicalIncidents')      → reports.graphical.incidents
    Route::get('/graphical/incidents/data',  'graphicalIncidentsData')  → ...
    Route::get('/graphical/households',      'graphicalHouseholds')     → reports.graphical.households
    Route::get('/graphical/households/data', 'graphicalHouseholdsData') → ...
    Route::get('/graphical/hazards',         'graphicalHazards')        → reports.graphical.hazards
});
```

---

## Views

```
resources/views/
├── layouts/
│   └── pdf.blade.php               ← isolated PDF layout (no navbar/sidebar)
└── reports/
    ├── index.blade.php             ← hub: 2 cards (Tabular, Graphical)
    ├── population.blade.php        ← table + Excel/PDF buttons
    ├── risk.blade.php              ← grouped table + filters + Excel/PDF buttons
    ├── households.blade.php        ← paginated table + filters + Excel button
    ├── incidents.blade.php         ← paginated table + filters + Excel/PDF buttons
    ├── graphical/
    │   ├── index.blade.php         ← 5 chart cards linking to each chart
    │   ├── population.blade.php    ← line chart, annual, year checkboxes
    │   ├── vulnerability.blade.php ← grouped bar, annual, year checkboxes
    │   ├── incidents.blade.php     ← stacked bar, annual+monthly toggle
    │   ├── households.blade.php    ← bar, annual+monthly toggle
    │   └── hazards.blade.php       ← doughnut + horizontal bar, static
    └── pdf/
        ├── population.blade.php    ← print-ready, no sidebar
        ├── risk.blade.php          ← print-ready with risk colour bands
        └── incidents.blade.php     ← print-ready
```

---

## Scope Rules

| Role | Population | Risk | Households | Incidents | Charts |
|------|-----------|------|-----------|-----------|--------|
| Admin | All 22 barangays | All | All | All | All |
| Division Chief | All 22 | All | All | All | All |
| Barangay Staff | Own barangay | Own | Own | Incidents affecting own | Own barangay |

---

## Out of Scope

- Scheduled / automated report delivery (email)
- Custom report builder / ad-hoc queries
- Charts inside PDF (DomPDF does not render Canvas)
- Any data entry or modification
