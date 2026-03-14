# Module 1 — Foundation & Database

## Goal
Establish the complete Laravel 11 database layer: all migrations in Laravel Blueprint syntax, all Eloquent models with relationships, the SyncService (core computation engine), a HouseholdObserver to auto-trigger syncing, and the default database seeder.

---

## Scope

### Migrations (13 files)
Convert all old-app PDO-style migrations into Laravel Blueprint migrations:

| File | Tables |
|------|--------|
| `create_barangays_table` | `barangays` |
| `create_users_table` | `users` (extends default) |
| `create_alerts_announcements_tables` | `alerts`, `announcements` |
| `create_hazard_tables` | `hazard_types`, `hazard_zones` |
| `create_evacuation_data_entries_tables` | `evacuation_centers`, `data_entries` |
| `create_households_tables` | `households`, `household_members` |
| `create_population_tables` | `population_data`, `population_data_archive` |
| `gis_upgrade_barangay_boundary_incidents` | `barangay_boundary_logs`, `incident_reports`, `affected_areas`, add `boundary_geojson` to barangays |
| `add_computed_columns_to_barangays` | add computed stat columns to `barangays` and `ip_count` to `affected_areas` |
| `add_calculated_area_to_barangays` | add `calculated_area_km2` to `barangays` |

### Models (15 files)
| Model | Table | Key Relations |
|-------|-------|--------------|
| `User` | `users` | belongsTo Barangay; hasMany DataEntry, Announcement |
| `Barangay` | `barangays` | hasMany User, Household, HazardZone, PopulationData, EvacuationCenter, Alert, AffectedArea |
| `Household` | `households` | belongsTo Barangay; hasMany HouseholdMember |
| `HouseholdMember` | `household_members` | belongsTo Household |
| `HazardType` | `hazard_types` | hasMany HazardZone, IncidentReport |
| `HazardZone` | `hazard_zones` | belongsTo HazardType, Barangay |
| `IncidentReport` | `incident_reports` | belongsTo HazardType, User; hasMany AffectedArea |
| `AffectedArea` | `affected_areas` | belongsTo IncidentReport, Barangay |
| `PopulationData` | `population_data` | belongsTo Barangay |
| `PopulationDataArchive` | `population_data_archive` | belongsTo Barangay |
| `EvacuationCenter` | `evacuation_centers` | belongsTo Barangay |
| `Alert` | `alerts` | belongsTo Barangay |
| `Announcement` | `announcements` | belongsTo User (creator) |
| `DataEntry` | `data_entries` | belongsTo User |
| `BarangayBoundaryLog` | `barangay_boundary_logs` | belongsTo Barangay, User |

### Services (2 files)
| Service | Source | Purpose |
|---------|--------|---------|
| `SyncService` | `old-app/sync_functions.php` | Core sync chain: household → barangay → population_data → hazard_zones |
| `IncidentAffectedAreaService` | `old-app/incident_reports.php` | Ray-casting point-in-polygon, computes affected_areas per incident |

### Observer (1 file)
| Observer | Model | Triggers |
|----------|-------|---------|
| `HouseholdObserver` | `Household` | `saved`, `deleted` → `SyncService::handleSync($barangay_id)` |

### Seeders (2 files)
| Seeder | Data |
|--------|------|
| `DefaultSeeder` | 22 barangays, 6 hazard types, 5 users |
| `DatabaseSeeder` | Calls `DefaultSeeder` |

---

## Implementation Flow

```
1. Run existing Laravel default migrations (users, cache, jobs tables)
2. Create new migrations in order:
   - barangays (referenced by users)
   - alter users table (add barangay_id, role, is_active)
   - alerts + announcements
   - hazard_types + hazard_zones
   - evacuation_centers + data_entries
   - households + household_members
   - population_data + population_data_archive
   - GIS upgrade (barangay_boundary_logs, incident_reports, affected_areas, barangay boundary cols)
   - computed columns for barangays + affected_areas
3. Create all 15 Models with relationships, casts, fillable arrays
4. Create SyncService with all 5 sync methods
5. Create IncidentAffectedAreaService with point-in-polygon algorithm
6. Create HouseholdObserver, register in AppServiceProvider
7. Create DefaultSeeder + DatabaseSeeder
8. Run: php artisan migrate:fresh --seed
```

---

## Key Data Rules to Implement

### SyncService chain:
```php
// Called after any household create/update/delete
SyncService::handleSync($barangay_id)
  → recomputeHousehold($household_id)      // updates households row
  → syncBarangay($barangay_id)             // aggregates all households
  → syncPopulationData($barangay_id)       // archives old, writes new population_data
  → syncHazardZones($barangay_id)          // updates affected_population in hazard_zones
```

### Age group classifications (from household_members.age):
| Group | Age Range |
|-------|-----------|
| Infant | 0–2 |
| Child | 3–5 |
| Minor | 3–12 (overlaps child) |
| Adolescent | 13–17 |
| Young Adult | 18–24 |
| Adult | 25–44 |
| Middle Aged | 45–59 |
| Senior | 60+ |

### Population archive:
- Before overwriting `population_data`, copy existing row to `population_data_archive`
- Set `change_type` = 'UPDATE' or 'DELETE'
- Set `archived_by` = currently logged-in user's username

---

## Files Created in This Module

```
database/migrations/
  ├── xxxx_create_barangays_table.php
  ├── xxxx_add_fields_to_users_table.php
  ├── xxxx_create_alerts_announcements_tables.php
  ├── xxxx_create_hazard_tables.php
  ├── xxxx_create_evacuation_data_entries_tables.php
  ├── xxxx_create_households_tables.php
  ├── xxxx_create_population_tables.php
  ├── xxxx_create_gis_and_incident_tables.php
  ├── xxxx_add_computed_columns_to_barangays.php
  └── xxxx_add_calculated_area_to_barangays.php
database/seeders/
  ├── DatabaseSeeder.php
  └── DefaultSeeder.php
app/Models/
  ├── Barangay.php
  ├── Household.php
  ├── HouseholdMember.php
  ├── HazardType.php
  ├── HazardZone.php
  ├── IncidentReport.php
  ├── AffectedArea.php
  ├── PopulationData.php
  ├── PopulationDataArchive.php
  ├── EvacuationCenter.php
  ├── Alert.php
  ├── Announcement.php
  ├── DataEntry.php
  └── BarangayBoundaryLog.php
  (User.php — update existing)
app/Services/
  ├── SyncService.php
  └── IncidentAffectedAreaService.php
app/Observers/
  └── HouseholdObserver.php
```

---

## Acceptance Criteria

- [ ] `php artisan migrate:fresh --seed` runs without errors
- [ ] All 22 barangays seeded
- [ ] All 6 hazard types seeded
- [ ] 5 default users seeded (admin, division_chief, 3 barangay_staff)
- [ ] All model relationships resolve (e.g., `Barangay::with('households')->first()`)
- [ ] `SyncService::handleSync($barangay_id)` runs the full chain without errors
- [ ] `HouseholdObserver` registered and triggers on `Household::create()`
