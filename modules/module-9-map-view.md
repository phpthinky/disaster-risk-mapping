# Module 9 — Map View

## Goal
A single master interactive Leaflet map showing all GIS layers simultaneously: barangay boundaries, households, hazard zones, active incident polygons, and evacuation center markers. All layers are loaded via AJAX and can be toggled individually. Stat sidebar panel shows live counts.

---

## Routes

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | `/map` | `map.index` | all roles |
| GET | `/api/map/barangays` | `api.map.barangays` | all roles (AJAX) |
| GET | `/api/map/hazard-zones` | `api.map.hazard-zones` | all roles (AJAX) |
| GET | `/api/map/households` | `api.map.households` | all roles (AJAX) |
| GET | `/api/map/incidents` | `api.map.incidents` | all roles (AJAX) |
| GET | `/api/map/evacuation-centers` | `api.map.evacuation-centers` | all roles (AJAX) |

---

## Files Created

```
app/Http/Controllers/MapController.php
resources/views/map/
  └── index.blade.php
modules/module-9-map-view.md
```

*(Models already existed from Module 1)*

---

## API Response Shapes

### `api.map.barangays`
```json
[
  {
    "id": 1, "name": "Poblacion", "population": 4200,
    "household_count": 820, "pwd_count": 35, "senior_count": 110,
    "infant_count": 60, "pregnant_count": 12, "ip_count": 0,
    "at_risk_count": 95, "hazard_zones_count": 3,
    "boundary_geojson": "{...}"
  }
]
```

### `api.map.hazard-zones`
```json
[
  {
    "id": 1, "risk_level": "High Susceptible", "area_km2": 2.34,
    "affected_population": 340, "coordinates": "{...GeoJSON geometry...}",
    "description": "...", "barangay_name": "Poblacion",
    "hazard_name": "Flood", "hazard_color": "#3498db",
    "hazard_icon": "fa-water"
  }
]
```

### `api.map.households`
```json
[
  {
    "id": 1, "household_head": "Juan dela Cruz", "barangay_name": "Poblacion",
    "latitude": 12.835, "longitude": 120.82,
    "family_members": 5, "sitio": "Purok 3",
    "is_ip": false, "has_vulnerable": true,
    "vuln_flags": ["Elderly", "PWD"]
  }
]
```

### `api.map.incidents`
```json
[
  {
    "id": 1, "title": "Typhoon Odette", "status": "ongoing",
    "incident_date": "2026-03-15", "affected_polygon": "{...}",
    "total_affected": 1240, "hazard_name": "Flood",
    "hazard_color": "#3498db", "hazard_icon": "fa-water"
  }
]
```

*(Only `ongoing` and `monitoring` incidents with non-empty polygons are returned.)*

### `api.map.evacuation-centers`
```json
[
  {
    "id": 1, "name": "Brgy. Hall - Sabang", "barangay_name": "Sabang",
    "latitude": 12.842, "longitude": 120.818,
    "status": "operational", "capacity": 500, "current_occupancy": 120,
    "availability": 380, "facilities": "Restrooms, Kitchen",
    "contact_person": "Juan dela Cruz", "contact_number": "09123456789"
  }
]
```

*(Only centers with non-null, non-zero lat/lng are returned.)*

---

## Business Rules

1. **All layers loaded via AJAX** — no data embedded in the Blade template (except the stats sidebar).
2. **Household dots** — blue = no vulnerable members; red = has PWD, elderly, infant, or pregnant member.
3. **Hazard zone polygons** — filled with the hazard type's colour at 30% opacity.
4. **Incident polygons** — shown only for `ongoing` and `monitoring` status; filled in the hazard type colour.
5. **Evacuation center markers** — colour-coded circle icons: green = operational, yellow = maintenance, red = closed.
6. **Layer controls** — each layer group (Barangays, Hazard Zones, Households, Incidents, Evac Centers) is togglable via Leaflet's built-in layer control.
7. **Popups** — every feature has an informative popup (name, key stats, link to detail page).

---

## Views

### map/index.blade.php
- **Stats sidebar** (above the map): 6 quick-count chips — Barangays, Hazard Zones, Mapped Households, At-Risk Population, Active Incidents, Operational Evac Centers.
- **Full-width Leaflet map** (600 px tall) — OSM Street + Esri Satellite base layers.
- **Layer control** (top-right): overlay toggles for all 5 data layers.
- **Loading indicator** shown while AJAX fetches complete.
- Each layer is loaded independently via `fetch()` on page load.

---

## Acceptance Criteria

- [x] All 5 GIS layers render on the master map
- [x] Barangay boundaries shown as polygon overlays with population popup
- [x] Household dots colour-coded by vulnerability
- [x] Hazard zone polygons filled with hazard type colour
- [x] Active incident polygons shown with hazard type colour
- [x] Evacuation centers shown as colour-coded circle icons
- [x] All layers individually togglable via Leaflet layer control
- [x] Every marker/polygon has a popup linking to the relevant detail page
- [x] Stats sidebar reflects live database counts (passed from controller)
- [x] Satellite / street tile layer switcher available
