# Module 8 — Incident Reports

## Goal
Full CRUD for disaster incident reports with Leaflet polygon drawing to define the affected area. After saving, the system auto-computes which households fall inside the polygon and produces a per-barangay affected population breakdown using point-in-polygon (ray-casting).

---

## Routes

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | `/incidents` | `incidents.index` | all roles |
| GET | `/incidents/create` | `incidents.create` | admin, barangay_staff |
| POST | `/incidents` | `incidents.store` | admin, barangay_staff |
| GET | `/incidents/{incident}` | `incidents.show` | all roles |
| GET | `/incidents/{incident}/edit` | `incidents.edit` | admin, barangay_staff |
| PUT | `/incidents/{incident}` | `incidents.update` | admin, barangay_staff |
| DELETE | `/incidents/{incident}` | `incidents.destroy` | admin, barangay_staff |
| GET | `/api/incidents/map-data` | `api.incidents.map-data` | all roles (AJAX) |

> Division chief role is read-only. Write methods abort 403 if a division chief attempts them.

---

## Files Created

```
app/Http/Controllers/IncidentReportController.php
app/Services/AffectedAreaService.php
resources/views/incidents/
  ├── index.blade.php
  ├── create.blade.php
  ├── edit.blade.php
  ├── show.blade.php
  └── _form.blade.php
modules/module-8-incident-reports.md
```

*(Models and migrations already existed from Module 1)*

---

## Business Rules

1. **Polygon-driven computation** — affected households and populations are computed from the drawn polygon, not entered manually.
2. **Barangay staff scope** — staff only see incidents where at least one affected area belongs to their own barangay.
3. **Division chief** — read-only index + show; create/edit/delete returns 403.
4. **Recompute on every save** — updating an incident clears and recomputes all `affected_areas` rows.
5. **No polygon = no affected areas** — saving without a polygon is valid; affected area table will be empty.
6. **Excluded count** — households missing valid GPS coordinates (outside 12.50–13.20°N, 120.50–121.20°E) are counted but excluded from PIP computation.

---

## AffectedAreaService::compute()

```php
AffectedAreaService::compute(string $polygonGeoJson): array
```

Returns:
```php
[
    'by_barangay'    => [ ['barangay_id'=>1, 'barangay_name'=>'...', 'affected_households'=>12, ...], ... ],
    'totals'         => [ 'affected_households'=>45, 'affected_population'=>230, ... ],
    'excluded_count' => 3,
]
```

**Algorithm:**
1. Parse the GeoJSON polygon string via `GeoService::outerRing()` (supports `Polygon` and `Feature` wrappers)
2. Load all households with valid GPS within Sablayan bounds
3. For each household: run `GeoService::pointInPolygon(lat, lng, ring)` (ray-casting)
4. Aggregate matching households by `barangay_id`: households, population, PWD, seniors, infants, children (minors), pregnant, IPs

---

## Data Flow

```
User draws polygon on Leaflet map
    → polygon stored as GeoJSON string in hidden input
    → form POSTed to incidents.store / incidents.update
    → AffectedAreaService::compute($polygon)
        → GeoService::outerRing() — extract coordinate ring
        → GeoService::pointInPolygon() — ray-casting for each household
        → aggregate by barangay
    → affected_areas rows deleted + recreated
    → redirect to incidents.show with success message
```

---

## Views

### incidents/index.blade.php
- **3 stat cards**: Total Incidents, Active (Ongoing), Total Affected Population
- **Filter bar**: status dropdown (All/Ongoing/Monitoring/Resolved) + free-text search (title or hazard type)
- **Table**: Title, Disaster Type (with colour + icon from `hazard_types`), Date, Status badge, Affected Pop., Reported By, Actions (View / Edit / Delete)
- Delete button hidden for division chief

### incidents/_form.blade.php (shared create/edit form)
- **Incident Details card**: Title, Disaster Type (select from `hazard_types`), Date, Status, Description
- **Draw Affected Area card**: Leaflet map with satellite base layer; polygon draw tool (top-right); household dots (blue = normal, red = has vulnerable members); barangay boundaries with name labels; existing polygon restored from hidden input on edit
- Hidden input `affected_polygon` stores the GeoJSON geometry string
- Submit: "Save & Compute Affected Areas"

### incidents/show.blade.php
- **Left card — Incident Info**: Disaster type, date, reporter, description, 8 summary stat boxes (people, households, PWD, elderly, infants, children, pregnant, IPs)
- **Right card — Affected Area Map**: Read-only Leaflet map showing the saved polygon + household dots + barangay boundaries
- **Affected Areas table**: Per-barangay breakdown sorted by population descending; totals row in dark footer
- Edit button in header (hidden for division chief)

---

## Acceptance Criteria

- [x] CRUD for incident reports (admin + barangay_staff)
- [x] Division chief gets read-only access (403 on write attempts)
- [x] Barangay staff see only incidents affecting their barangay
- [x] Leaflet polygon drawing tool on create/edit form
- [x] Satellite + OSM tile layer switcher on all maps
- [x] Existing polygon restored on edit form
- [x] Household dots colour-coded by vulnerability (PWD/elderly/infant/pregnant)
- [x] Barangay boundaries shown as reference overlay
- [x] On save, AffectedAreaService runs PIP and populates `affected_areas`
- [x] Show page displays per-barangay breakdown table with totals footer
- [x] Show page map displays saved polygon with household overlay
- [x] Status badge with correct colour (Ongoing=red, Monitoring=yellow, Resolved=green)
- [x] Hazard type displayed with colour + icon from `hazard_types` table
- [x] No affected areas message shown when polygon not drawn or empty result
