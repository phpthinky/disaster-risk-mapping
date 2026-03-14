# Module 5 — Household Management

## Goal
Full CRUD for households and family members, with automatic sync chain on every save/delete, GPS coordinate validation, and Excel export.

---

## Routes

| Method | URI | Controller@Method | Name | Access |
|--------|-----|-------------------|------|--------|
| GET | /households | HouseholdController@index | households.index | admin, barangay_staff |
| GET | /households/create | HouseholdController@create | households.create | admin, barangay_staff |
| POST | /households | HouseholdController@store | households.store | admin, barangay_staff |
| GET | /households/{household} | HouseholdController@show | households.show | admin, barangay_staff |
| GET | /households/{household}/edit | HouseholdController@edit | households.edit | admin, barangay_staff |
| PUT | /households/{household} | HouseholdController@update | households.update | admin, barangay_staff |
| DELETE | /households/{household} | HouseholdController@destroy | households.destroy | admin, barangay_staff |
| GET | /households/export | HouseholdController@export | households.export | admin, barangay_staff |
| POST | /api/households/{household}/members | HouseholdMemberController@store | api.members.store | admin, barangay_staff |
| DELETE | /api/household-members/{member} | HouseholdMemberController@destroy | api.members.destroy | admin, barangay_staff |
| GET | /api/households/{household}/members | HouseholdMemberController@index | api.members.index | admin, barangay_staff |

---

## Files to Create

### Controllers
- `app/Http/Controllers/HouseholdController.php`
- `app/Http/Controllers/HouseholdMemberController.php`

### Form Requests
- `app/Http/Requests/StoreHouseholdRequest.php`

### Exports
- `app/Exports/HouseholdExport.php`

### Views
- `resources/views/households/index.blade.php`
- `resources/views/households/show.blade.php`
- `resources/views/households/create.blade.php`
- `resources/views/households/edit.blade.php`

---

## Business Rules

1. **Barangay scoping**: `barangay_staff` can only see/create/edit households in their assigned barangay. Admin can manage all.
2. **GPS required**: Latitude must be 12.50–13.20, Longitude 120.50–121.20.
3. **Sync chain**: HouseholdObserver fires on `saved`/`deleted` → `SyncService::handleSync($barangay_id)`.
4. **family_members computed**: Stored as count of `household_members` rows; the observer recomputes all demographic counts from the members table via `SyncService::recomputeHousehold()`.
5. **Export**: Excel download filtered by barangay scope.
6. **No manual population entry** — all counts come from sync chain.

---

## HouseholdController Methods

| Method | Logic |
|--------|-------|
| `index()` | Staff → filter by own barangay_id; Admin → show all or filter by `?barangay_id=`; load summary stats |
| `create()` | Pass barangays list (staff: only own) |
| `store()` | Validate via `StoreHouseholdRequest`, create, observer auto-triggers sync |
| `show()` | Load household with members; GPS marker mini-map |
| `edit()` | Form pre-filled |
| `update()` | Validate, update, observer triggers sync |
| `destroy()` | Delete household (members cascade); barangay_id captured before delete for sync |
| `export()` | Return `HouseholdExport` as xlsx |

---

## HouseholdMemberController Methods

| Method | Logic |
|--------|-------|
| `index()` | JSON: list members of household |
| `store()` | JSON: create member, trigger `SyncService::recomputeHousehold()` then `handleSync()` |
| `destroy()` | JSON: delete member, trigger sync |

---

## StoreHouseholdRequest Validation Rules

```php
'household_head'   => 'required|string|max:255',
'barangay_id'      => 'required|exists:barangays,id',
'sex'              => 'required|in:Male,Female',
'age'              => 'required|integer|min:0|max:130',
'gender'           => 'required|in:Male,Female,Other',
'house_type'       => 'nullable|string|max:100',
'sitio_purok_zone' => 'nullable|string|max:255',
'ip_non_ip'        => 'nullable|in:IP,Non-IP',
'hh_id'            => 'nullable|string|max:100',
'latitude'         => 'required|numeric|between:12.50,13.20',
'longitude'        => 'required|numeric|between:120.50,121.20',
'preparedness_kit' => 'nullable|boolean',
'educational_attainment' => 'nullable|string|max:100',
```

---

## Views

### households/index.blade.php
- Summary stat cards: total households, total population, PWD, seniors
- Barangay filter dropdown (admin only)
- Searchable table: HH head, barangay, zone, family members, GPS status, actions
- Export button

### households/show.blade.php
- Household head info card
- GPS mini-map (Leaflet marker)
- Demographic summary
- Family members table with add/delete member (AJAX)

### households/create.blade.php & edit.blade.php
- Form with all fields
- Leaflet map for GPS coordinate picking (click to set lat/lng)
- Auto-fill latitude/longitude inputs from map click

---

## HouseholdExport (maatwebsite/excel)

Columns: ID, Barangay, HH ID, Household Head, Sex, Age, Zone, IP/Non-IP, House Type, Family Members, PWD, Seniors, Children, Infants, Pregnant, Latitude, Longitude, Created At

---

## Acceptance Criteria

- [ ] Admin can list, create, edit, delete any household
- [ ] Barangay staff can only manage their own barangay's households
- [ ] GPS coordinates outside valid range are rejected
- [ ] Saving/deleting a household triggers sync chain (barangay stats update)
- [ ] Family members can be added/deleted via AJAX on show page
- [ ] Excel export works and is scoped by role
- [ ] GPS picker map on create/edit lets user click to set coordinates
