# Module 4 — Barangay Management

## Goal
Full barangay CRUD with GIS boundary drawing (Leaflet Draw), computed stats display, and role-based access. Admin has full control; barangay_staff can only view/edit their own barangay; division_chief is view-only.

---

## Scope

### Controller
| Controller | Methods |
|------------|---------|
| `BarangayController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` |
| + AJAX methods | `staffUsers()`, `boundaries()`, `saveBoundary()`, `deleteBoundary()` |

### Views
| View | Who sees it |
|------|-------------|
| `barangays/index.blade.php` | All roles — admin sees all; staff redirected to own show page |
| `barangays/show.blade.php` | All roles — profile with stats, hazard summary, population |
| `barangays/edit.blade.php` | Admin (any) + staff (own only) — form + Leaflet boundary draw |

### Routes
```
GET    /barangays                  barangays.index     (all roles)
GET    /barangays/create           barangays.create    (admin only)
POST   /barangays                  barangays.store     (admin only)
GET    /barangays/{id}             barangays.show      (all roles)
GET    /barangays/{id}/edit        barangays.edit      (admin + own staff)
PUT    /barangays/{id}             barangays.update    (admin + own staff)
DELETE /barangays/{id}             barangays.destroy   (admin only)
POST   /barangays/{id}/boundary    barangays.boundary  (admin + own staff)
DELETE /barangays/{id}/boundary    barangays.boundary.delete (admin only)
GET    /api/barangays/boundaries   api.boundaries      (auth — for map overlays)
GET    /api/barangays/staff-users  api.staff-users     (admin — populate select)
```

---

## Key Business Rules

1. **Admin** can create, edit, delete any barangay
2. **Barangay staff** can only edit their own barangay's info and draw its boundary
3. **Division chief** view-only
4. **Cannot delete** a barangay that has linked households
5. **Boundary drawing**: Leaflet Draw plugin; only one polygon allowed per barangay
6. **Calculated area**: derived from the polygon using the Shoelace / Haversine formula
7. **Staff assignment**: a `barangay_staff` user can be assigned to exactly one barangay
8. **Boundary logs**: every boundary create/update logged to `barangay_boundary_logs`

---

## Files Created

```
app/Http/Controllers/BarangayController.php
resources/views/barangays/
  ├── index.blade.php
  ├── show.blade.php
  └── edit.blade.php
```

---

## Acceptance Criteria

- [ ] `GET /barangays` lists all 22 barangays with summary stat cards
- [ ] Admin can create a new barangay
- [ ] Admin can edit any barangay name, area, coordinates, assign staff
- [ ] Admin can delete a barangay (blocked if households exist)
- [ ] Leaflet map on edit page shows all existing boundaries as orange overlays
- [ ] User can draw a polygon; calculated area computed client-side and sent to server
- [ ] Saved boundary shows on the edit map on reload
- [ ] Barangay staff sees their own barangay's edit page directly
- [ ] Division chief can only view, not edit
- [ ] `GET /api/barangays/boundaries` returns GeoJSON for all bounded barangays
