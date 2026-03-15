# Module 6 — Hazard Zones

## Goal
Full CRUD for hazard zones with Leaflet polygon drawing, GeoJSON storage, risk-level colour coding, barangay-boundary reference overlays, and a GeoJSON API endpoint for use by other map views.

---

## Routes

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | /hazards | hazards.index | all roles |
| GET | /hazards/create | hazards.create | admin, barangay_staff |
| POST | /hazards | hazards.store | admin, barangay_staff |
| GET | /hazards/{hazard} | hazards.show | all roles |
| GET | /hazards/{hazard}/edit | hazards.edit | admin, barangay_staff |
| PUT | /hazards/{hazard} | hazards.update | admin, barangay_staff |
| DELETE | /hazards/{hazard} | hazards.destroy | admin only |
| GET | /api/hazard-zones/geojson | api.hazards.geojson | all auth |

---

## Files Created

```
app/Http/Controllers/HazardZoneController.php
app/Models/HazardZone.php          (updated — risk level helpers added)
resources/views/hazards/
  ├── index.blade.php
  ├── show.blade.php
  ├── create.blade.php
  ├── edit.blade.php
  └── _form.blade.php
modules/module-6-hazard-zones.md
```

---

## Business Rules

1. **Barangay scoping** — `barangay_staff` can only view/create/edit zones for their own barangay.
2. **Division chief** — read-only (`index` + `show`); create/edit/delete all return 403.
3. **Admin only** can delete a hazard zone.
4. **`affected_population`** is auto-set from `barangay.population` on create (SyncService keeps this updated thereafter via `syncHazardZones()`).
5. **Coordinates** are stored as a GeoJSON `Geometry` object (type: Polygon) in the `coordinates` text column.
6. **Area (km²)** is auto-calculated client-side from the drawn polygon using Leaflet's geodesic area.

---

## HazardZone Model Additions

```php
const RISK_LEVELS = [
    'High Susceptible', 'Moderate Susceptible', 'Low Susceptible',
    'Not Susceptible', 'Prone', 'Generally Susceptible',
    'PEIS VIII - Very destructive to devastating ground shaking',
    'PEIS VII - Destructive ground shaking', 'General Inundation',
];

static function riskBadgeClass(string $level): string
// Returns Bootstrap colour class: danger / warning / info / success / secondary
```

---

## Risk Level → Badge Colour Mapping

| Risk Level | Badge |
|------------|-------|
| High Susceptible | `danger` |
| Prone | `danger` |
| PEIS VIII — Very destructive… | `danger` |
| Moderate Susceptible | `warning` |
| PEIS VII — Destructive… | `warning` |
| Generally Susceptible | `info` |
| General Inundation | `info` |
| Low Susceptible | `info` |
| Not Susceptible | `success` |

---

## HazardZoneController Methods

| Method | Logic |
|--------|-------|
| `index()` | Paginated list (25/page) with filters (type, risk level, barangay); 4 stat cards; scoped for staff |
| `create()` | Pass hazard types + scoped barangays |
| `store()` | Validate → scope check → auto-set `affected_population` → create |
| `show()` | Detail view with Leaflet map showing zone polygon + barangay boundary overlay |
| `edit()` | Pre-filled form; scope check |
| `update()` | Validate → scope check on both old and new barangay → update |
| `destroy()` | Admin-only; delete record |
| `geojson()` | Returns `FeatureCollection` of all zones that have coordinates; supports `?barangay_id=` and `?hazard_type_id=` filters |

---

## Views

### hazards/index.blade.php
- **4 stat cards**: Total Zones, High Risk Zones, Affected Population, Total Area (km²)
- **Filter bar**: Hazard Type, Risk Level, Barangay (admin only)
- **Table**: Hazard type (colour dot + icon + name), Barangay, Risk Level badge, Area, Affected Population, Map indicator (Yes/No badge), Actions
- **Delete modal**: Confirms zone name before deleting (admin only)

### hazards/_form.blade.php (shared create/edit)
- **Left column**: Hazard Type select (with `data-color`/`data-icon` attributes), Barangay select (disabled for staff), Risk Level select, Area km² input (auto-filled from polygon draw), Description textarea, hidden `coordinates` input
- **Right column**: Leaflet map with:
  - Street / Satellite layer switcher
  - Orange dashed overlay of selected barangay's boundary (loaded from `data-boundary` attribute on option)
  - `L.Control.Draw` — polygon only; colour matches selected hazard type
  - Existing polygon pre-loaded in edit mode
  - Clear Polygon button
  - Geodesic area auto-calculation on draw (updates km² input)

### hazards/show.blade.php
- **Left**: Info table (type, barangay, risk level, area, affected population, polygon status, date added) + description card
- **Right**: Leaflet map with zone polygon (hazard type colour) + barangay boundary (orange dashed); popup shows type + risk badge + barangay name

---

## GeoJSON API Response Format

`GET /api/hazard-zones/geojson`

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "geometry": { "type": "Polygon", "coordinates": [[...]] },
      "properties": {
        "id": 1,
        "hazard_type": "Flooding",
        "color": "#3498db",
        "icon": "fa-water",
        "barangay": "Batong Buhay",
        "risk_level": "High Susceptible",
        "badge_class": "danger",
        "area_km2": 2.45,
        "affected_population": 1234,
        "description": "..."
      }
    }
  ]
}
```

---

## Acceptance Criteria

- [x] All roles can view hazard zones list and detail pages
- [x] Admin and barangay staff can create and edit hazard zones
- [x] Barangay staff sees only their own barangay's zones
- [x] Division chief is read-only (403 on create/edit/delete)
- [x] Admin only can delete a zone
- [x] Risk level dropdown covers all 9 enum values
- [x] Risk level badges are colour-coded by severity
- [x] Hazard type colour/icon are displayed throughout
- [x] Leaflet Draw polygon tool works on create/edit form
- [x] Drawing a polygon auto-calculates area in km²
- [x] Barangay boundary shown as reference overlay on form map
- [x] Existing polygon pre-loaded on edit form
- [x] `affected_population` computed via point-in-polygon from households inside the zone (not barangay total)
- [x] `GET /api/hazard-zones/geojson` returns valid GeoJSON FeatureCollection with full properties
- [x] 4 summary stat cards on index page
- [x] Street / Satellite layer switcher on form and show maps

---

## Bug Fixes (2026-03-15)

### Fix 1 — `GeoService::pointInPolygon()` division by zero
`$crossesX` was computed unconditionally, causing division by zero on degenerate polygon edges where `$yj === $yi`. Fixed by moving the X-crossing test inside the `$crossesY` guard and adding an explicit `$denom != 0.0` check before the division.

### Fix 2 — `area_km2` submission blocked by HTML5 step mismatch
Leaflet auto-calculates area to 4 decimal places (`.toFixed(4)`), but the form had `step="0.01"` and the DB column was `decimal(10,2)`, causing the browser to reject the value as not a valid multiple of `0.01`.
- Migration `2026_03_15_…` widens `hazard_zones.area_km2` to `decimal(10,4)`
- Form input: `step="0.0001"`, `max="9999.9999"`
- Controller validation: `max:9999.9999`

### Fix 3 — `affected_population` was entire barangay population
`SyncService::syncHazardZones()` previously copied `barangay.population` to every zone in the barangay. Replaced with a per-zone point-in-polygon loop: loads all GPS-tagged households, tests each against the zone polygon with `GeoService::pointInPolygon()`, sums `family_members` for matching households only. Zones without a polygon receive `0`.
