# Module 11 — Evacuation Centers

## Goal
Full CRUD for evacuation centers with GPS pin-picker map, capacity/occupancy tracking, status management, and a Leaflet overview map on the index page showing all centers as colour-coded markers.

---

## Routes

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | `/evacuations` | `evacuations.index` | all roles |
| GET | `/evacuations/create` | `evacuations.create` | admin, barangay_staff |
| POST | `/evacuations` | `evacuations.store` | admin, barangay_staff |
| GET | `/evacuations/{evacuation}` | `evacuations.show` | all roles |
| GET | `/evacuations/{evacuation}/edit` | `evacuations.edit` | admin, barangay_staff |
| PUT | `/evacuations/{evacuation}` | `evacuations.update` | admin, barangay_staff |
| DELETE | `/evacuations/{evacuation}` | `evacuations.destroy` | admin only |

> Division chief is read-only — write methods abort 403.
> Barangay staff see and can only edit centers belonging to their own barangay.

The map API endpoint (`/api/map/evacuation-centers`) is defined in `MapController` and used by both the master map (Module 9) and the index page map.

---

## Files Created

```
app/Http/Controllers/EvacuationCenterController.php
resources/views/evacuations/
  ├── index.blade.php
  ├── create.blade.php
  ├── edit.blade.php
  ├── show.blade.php
  └── _form.blade.php
modules/module-11-evacuation-centers.md
```

*(Model `EvacuationCenter` and migration already existed from Module 1)*

---

## Business Rules

1. **Capacity constraint** — `current_occupancy` must be ≥ 0; `availability` = `max(0, capacity - current_occupancy)` (computed accessor on the model).
2. **Role scoping** — `barangay_staff` can only create/edit/view centers in their own barangay; other barangays return 403.
3. **Division chief** — read-only (index + show); all write attempts return 403.
4. **Status values**: `operational`, `maintenance`, `closed`.
5. **GPS optional** — lat/lng are nullable; centers without coordinates appear in the table but not on the map.
6. **Sidebar link** — `evacuations.index` (note the plural `s`; the original placeholder had `evacuation.index` which was corrected in this module).

---

## Model: EvacuationCenter

| Column | Type | Notes |
|--------|------|-------|
| `name` | string | Required |
| `barangay_id` | FK | Required |
| `capacity` | integer | Required, ≥ 1 |
| `current_occupancy` | integer | Default 0 |
| `latitude` | float | Nullable |
| `longitude` | float | Nullable |
| `facilities` | text | Nullable |
| `contact_person` | string | Nullable |
| `contact_number` | string | Nullable |
| `status` | enum | `operational` \| `maintenance` \| `closed` |

Computed accessor: `getAvailabilityAttribute()` → `max(0, capacity - current_occupancy)`

---

## Views

### evacuations/index.blade.php
- **4 stat cards**: Total Centers, Operational, Total Capacity, Current Occupancy.
- **Leaflet overview map**: markers loaded from `api.map.evacuation-centers`; green = operational, yellow = maintenance, red = closed; each marker has a popup with status, capacity, occupancy, and a "View" link.
- **Filter bar**: barangay dropdown (hidden for barangay_staff) + status dropdown.
- **Table**: Name, Barangay, Status badge, Capacity, Occupancy (with mini progress bar), GPS status, Contact, Actions.
- Progress bar colour: green < 50%, yellow 50–79%, red ≥ 80%.

### evacuations/_form.blade.php (shared create/edit)
- **Basic Information card**: Name, Barangay (disabled + hidden input for barangay_staff), Status, Facilities.
- **Capacity card**: Total Capacity + Current Occupancy side by side.
- **Contact Information card**: Contact Person, Contact Number.
- **GPS pin picker map**: Leaflet map (right column); click to place a draggable marker; drag to update coordinates; manual lat/lng inputs sync bidirectionally with the marker.
- Clear Pin button resets both inputs and removes the marker.

### evacuations/show.blade.php
- **Center Details card**: Barangay link, Capacity, Occupancy with progress bar, Available Slots (coloured green/red).
- **Facilities card**: shown only if populated.
- **Contact card**: contact person + number.
- **GPS Coordinates card**: lat/lng in monospace; "No GPS" message with link to edit if not set.
- **Location map** (right column): Leaflet map centred on the center's pin with a popup; hidden if no coordinates.
- Admin-only Delete button at bottom with confirmation modal.

---

## Acceptance Criteria

- [x] Full CRUD for evacuation centers
- [x] Admin has unrestricted access
- [x] Barangay staff scoped to own barangay; cross-barangay attempts return 403
- [x] Division chief gets read-only access (403 on write attempts)
- [x] Index map shows all GPS-tagged centers with colour-coded markers by status
- [x] Form GPS pin picker: click to place, drag to move, manual input, clear button
- [x] Capacity progress bar colour-coded (green/yellow/red) in table and show page
- [x] Available slots computed and displayed
- [x] Show page location map with popup
- [x] Sidebar link resolves correctly as `evacuations.index`
