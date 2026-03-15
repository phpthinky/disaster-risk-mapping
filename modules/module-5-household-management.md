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

## Files Created

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
- `resources/views/households/_form.blade.php`

---

## Business Rules

1. **Barangay scoping**: `barangay_staff` can only see/create/edit households in their assigned barangay. Admin can manage all.
2. **GPS required**: Latitude must be 12.50–13.20, Longitude 120.50–121.20.
3. **Sync chain**: HouseholdObserver fires on `saved`/`deleted` → `SyncService::handleSync($barangay_id)`.
4. **family_members computed**: Stored as count of `household_members` rows; the observer recomputes all demographic counts from the members table via `SyncService::recomputeHousehold()`.
5. **Export**: Excel download filtered by barangay scope.
6. **No manual population entry** — all counts come from sync chain.
7. **Duplicate member guard**: Adding a member with the same name (case-insensitive) to the same household is blocked with HTTP 422.

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
| `store()` | Validate, check for duplicate name, create member, trigger `SyncService::recomputeHousehold()` then `handleSync()` |
| `destroy()` | JSON: delete member, trigger sync |

**Field mapping note**: Validation uses friendly keys (`name`, `sex`, `relation`) which are explicitly mapped to DB columns (`full_name`, `gender`, `relationship`) before `create()`.

---

## StoreHouseholdRequest Validation Rules

```php
'household_head'         => 'required|string|max:255',
'barangay_id'            => 'required|exists:barangays,id',
'sex'                    => 'required|in:Male,Female',
'age'                    => 'required|integer|min:0|max:130',
'birthday'               => 'nullable|date',
'gender'                 => 'required|in:Male,Female,Other',
'house_type'             => 'nullable|string|max:100',
'sitio_purok_zone'       => 'nullable|string|max:255',
'ip_non_ip'              => 'nullable|in:IP,Non-IP',
'hh_id'                  => 'nullable|string|max:100',
'latitude'               => 'required|numeric|between:12.50,13.20',
'longitude'              => 'required|numeric|between:120.50,121.20',
'preparedness_kit'       => 'nullable|boolean',
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
- Household head info card (includes Birthday row)
- GPS mini-map (Leaflet marker)
- Demographic summary
- Family members table with Birthday column and add/delete member (AJAX)
- Add Member modal with age ↔ birthday sync

### households/create.blade.php & edit.blade.php
- Form with all fields including Birthday
- Leaflet map for GPS coordinate picking (click to set lat/lng)
- Auto-fill latitude/longitude inputs from map click
- Age ↔ birthday JS sync (see Modifications section)

---

## HouseholdExport (maatwebsite/excel)

Columns: ID, Barangay, HH ID, Household Head, Sex, Age, Zone, IP/Non-IP, House Type, Family Members, PWD, Seniors, Children, Infants, Pregnant, Latitude, Longitude, Created At

---

## Modifications

### Birthday Field (households & household_members)

**Migrations added:**
- `2026_03_14_000001_add_birthday_to_households_and_members.php` — nullable `date` column `birthday` on both tables
- `2026_03_14_000002_add_is_ip_to_household_members.php` — `boolean` column `is_ip` (was used in views/controller but missing from the original migration)

**Models updated:**
- `Household`: `birthday` added to `$fillable`, cast to `'date'`
- `HouseholdMember`: `birthday` and `is_ip` added to `$fillable`, cast appropriately; `$appends = ['name', 'sex', 'relation']` added so accessor aliases are included in JSON responses

**Age ↔ Birthday JS sync (both `_form.blade.php` and Add Member modal):**
- Changing **birthday** → calculates exact age (accounts for month/day) → sets age field
- Changing **age** → if birthday is blank, sets birthday to `YYYY-01-01` (January 1st of that year)
- Changing **age** → if birthday is already filled, does **nothing** (no overwrite)
- Modal `hidden.bs.modal` event resets both fields

---

## Bug Fixes

### 1. Member save: field name mismatch → 500 / silent data loss
- **Cause**: Controller validated with keys `name`/`sex`/`relation`; DB columns are `full_name`/`gender`/`relationship` (all NOT NULL). Passing wrong keys to `create()` caused a DB constraint violation (500).
- **Fix**: Controller now explicitly maps `name → full_name`, `sex → gender`, `relation → relationship` before calling `create()`.

### 2. `PopulationDataArchive` table name mismatch → 500 on every member add/delete
- **Cause**: Migration created the table as `population_data_archive` (singular); Laravel pluralises the model name to `population_data_archives` by default. `SyncService::handleSync()` crashed with *"no such table"* after every member operation — after the member was already committed, causing phantom duplicates on retry.
- **Fix**: Added `protected $table = 'population_data_archive'` to `PopulationDataArchive` model.

### 3. `is_ip` column missing from `household_members`
- **Cause**: Column was used in views, controller validation, and AJAX responses, but was never in the original migration.
- **Fix**: Added via `2026_03_14_000002_add_is_ip_to_household_members.php`.

### 4. Modal backdrop stuck after successful add
- **Cause**: `bootstrap.Modal.getInstance()` returns `null` when the modal was opened via `data-bs-toggle` (not JS-initialised). Calling `.hide()` on `null` throws silently and leaves the backdrop.
- **Fix**: Uses `bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)` to always get a valid instance, followed by explicit cleanup of any stale `.modal-backdrop` elements and `modal-open` body class.

### 5. Duplicate member names per household
- **Fix**: Controller checks `LOWER(full_name)` case-insensitively before `create()` and returns HTTP 422 with a readable message if a duplicate is found.

---

## Acceptance Criteria

- [x] Admin can list, create, edit, delete any household
- [x] Barangay staff can only manage their own barangay's households
- [x] GPS coordinates outside valid range are rejected
- [x] Saving/deleting a household triggers sync chain (barangay stats update)
- [x] Family members can be added/deleted via AJAX on show page
- [x] Excel export works and is scoped by role
- [x] GPS picker map on create/edit lets user click to set coordinates
- [x] Birthday field on household head form: age ↔ birthday sync works correctly
- [x] Birthday field in Add Member modal: age ↔ birthday sync works correctly
- [x] Adding a member with a duplicate name is rejected with a clear error
- [x] Modal closes cleanly after member is added (no stuck backdrop)
