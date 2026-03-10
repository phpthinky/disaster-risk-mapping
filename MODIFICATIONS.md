DISASTER RISK MANAGEMENT SYSTEM — FULL REWRITE COMMAND
=======================================================

CONTEXT (Read this first before touching anything):
---------------------------------------------------
This system was originally built in 2022 with a simple vector map,
manual affected population input, and population subtracted from
barangay totals. No household records.

Students rebuilt it in 2025-2026 and added a households table with
actual GPS coordinates, population_data, archive tables, and more
detailed demographics — BUT never connected any of it together.

Every table is isolated. No JOINs are used meaningfully. The map
still shows circle markers. affected_population in hazard_zones is
still manually typed. barangays.population is still hardcoded.
Household GPS coordinates are never plotted or used for computation.

Our job is to finish what the students started — enforce GPS on
households, connect the data, and use household GPS coordinates
to auto-compute affected population when staff draws an incident
polygon on the map.

OUT OF SCOPE FOR THIS VERSION:
- Real-time live polygon drawing during an active disaster event
- Automatic polygon generation from sensor or external data feeds
- These are noted in the system UI as future upgrade capabilities

═══════════════════════════════════════════════════════════════════

PHASE 0 — AUDIT FIRST, NO CHANGES YET
---------------------------------------
1. List all PHP files and describe what each one does in one line
2. Find the main map file — read it completely
3. For every table, check: is it being JOINed anywhere or just
   used as standalone SELECT?
4. Check how hazard_zones.affected_population is currently set —
   manual input or computed?
5. Check if households.latitude/longitude is ever used on the map
6. Check if barangays.population is ever recomputed from households
7. Check how population_data relates to households — ever joined?
8. List all AJAX/API endpoints and what they return to the map
9. Check how risk zones and disasters are currently added by staff
10. Report all findings before writing a single line of code

═══════════════════════════════════════════════════════════════════

PHASE 1 — DATABASE REWIRE (no new tables, fix relationships)
-------------------------------------------------------------
1. Fix population sync — barangays.population should reflect
   SUM(households.family_members) per barangay. Create a reusable
   PHP function sync_barangay_population($barangay_id) that runs
   after every household insert/update/delete.

2. Fix population_data — total_population, elderly_count,
   children_count, pwd_count should all be computable from
   households table. Keep manual entry as fallback only.
   Create a reusable function compute_population_data($barangay_id)
   that pulls and aggregates from households.

3. Fix hazard_zones.affected_population — compute from
   population_data.total_population of the linked barangay_id
   instead of manual typing. Update automatically when
   population_data changes.

4. Fix population update bug — when population_data is updated,
   it must propagate to:
   - barangays.population
   - hazard_zones.affected_population for that barangay
   - population_data_archive (archive old before overwriting)
   Create a single function handle_population_update($barangay_id)
   that handles all propagation in one call.

═══════════════════════════════════════════════════════════════════

PHASE 2 — HOUSEHOLD GPS ENFORCEMENT
-------------------------------------
This is the foundation for the auto-computation in Phase 4.
Every household must have valid GPS coordinates so that when
staff draws an incident polygon, the system can accurately count
which households fall inside it.

1. Update Add Household form:
   - Add Leaflet map picker inside the form
   - Add "Use Current Location" button (browser/device GPS)
     as PRIMARY option — designed for field data collection
     where staff is physically at the household location
   - Add manual map click as FALLBACK for office encoding
   - Show a live map preview that updates as coordinates change
   - Validate coordinates on save:
     * Latitude must be between 12.50 and 13.20
     * Longitude must be between 120.50 and 121.20
     * Reject obviously invalid values like 99.99999 or 0,0
     * GPS coordinates are REQUIRED — form cannot submit without
       valid coordinates
   - After saving, call sync_barangay_population($barangay_id)
     to keep barangay population count updated

2. Update Edit Household form:
   - Same map picker with existing coordinates pre-plotted
     as a draggable marker
   - Staff can drag marker to correct wrong positions
   - Same GPS validation rules apply
   - After saving, call sync_barangay_population($barangay_id)

3. Create GPS data quality report (admin only):
   - List all households with missing or invalid coordinates
   - Show count of invalid records per barangay
   - Allow admin to filter and click through to re-encode
     flagged records
   - This is needed to clean up existing bad data before
     auto-computation can be fully trusted

═══════════════════════════════════════════════════════════════════

PHASE 3 — BARANGAY BOUNDARY DRAWING (GIS foundation)
-----------------------------------------------------
Required before the map upgrade and incident polygon features
can work properly.

1. Add boundary_geojson column (LONGTEXT) to barangays table
   via migration if not yet existing

2. Create barangay_boundaries.php (admin only):
   - Full screen Leaflet map
   - Side panel listing all 22 barangays
   - Click a barangay name → map zooms to its center coordinate
   - Draw polygon boundary using Leaflet Draw plugin
   - If boundary already exists → load it on map for editing
   - Save/update boundary_geojson to DB via AJAX
   - Status checklist: green checkmark = boundary drawn,
     red warning = not yet drawn
   - Progress indicator: X out of 22 barangays mapped

═══════════════════════════════════════════════════════════════════

PHASE 4 — INCIDENT REPORT WITH AUTO HOUSEHOLD COMPUTATION
----------------------------------------------------------
Staff manually draws a polygon on the map to mark the affected
area after a disaster or incident occurs. The system then
automatically counts all households whose GPS coordinates fall
inside that polygon and computes the full affected population
breakdown.

1. Create incident_reports.php (admin and barangay_staff):

   Form fields:
   - Incident title
   - Disaster type (from hazard_types dropdown)
   - Incident date
   - Status: ongoing / resolved / monitoring
   - Description / notes

   Map section:
   - Leaflet map with Draw plugin
   - Staff draws a polygon over the affected area
   - Polygon coordinates saved as GeoJSON

   Auto-computation on save (PHP, server-side):
   - Run point-in-polygon algorithm (ray casting) in PHP
   - Check every household WHERE latitude/longitude is valid
     against the drawn polygon GeoJSON
   - Count all households that fall inside the polygon
   - Aggregate per barangay:
     * affected_households (COUNT of households inside polygon)
     * affected_population (SUM of family_members)
     * affected_pwd (SUM of pwd_count)
     * affected_seniors (SUM of senior_count)
     * affected_infants (SUM of infant_count)
     * affected_minors (SUM of minor_count)
     * affected_pregnant (SUM of pregnant_count)
     * ip_count (COUNT where ip_non_ip = 'IP')
   - Save results to affected_areas table per barangay
   - Update hazard_zones.affected_population for related barangays

   Results display after save:
   - Show total affected population
   - Breakdown table per barangay
   - Vulnerability summary: PWD, seniors, infants, pregnant
   - IP vs Non-IP count

2. Incident list page:
   - Table of all incidents with date, type, status,
     total affected population
   - Click to view full details and affected area map
   - Filter by status, hazard type, date range

3. Add this note visibly in the incident report UI
   (shown to admin users, styled as an info box):

   "DEVELOPER NOTE: This system currently supports manual polygon
   drawing for affected area mapping. This can be upgraded in a
   future version to support real-time GPS-based incident tracking,
   where the affected polygon is automatically generated from live
   field reports. The household GPS infrastructure is already in
   place to support this upgrade."

═══════════════════════════════════════════════════════════════════

PHASE 5 — MAP UPGRADE
----------------------
Replace the existing circle marker map with a proper GIS layered
map. Preserve all existing legend designs, hazard icons, and
color coding exactly as they are.

Layer order (bottom to top):

1. Base tile layer (OpenStreetMap default)
   - Add tile switcher: Street, Satellite, Terrain

2. Barangay boundary layer (always visible)
   - Render boundary_geojson polygons from barangays table
   - Neutral fill with barangay name label
   - Click = popup showing:
     * Barangay name
     * Current population (from barangays.population)
     * Number of households (COUNT from households)
     * Active hazard types in this barangay
   - Gray out barangays with no boundary drawn yet

3. Household location layer (toggle off by default)
   - Plot each household as a small dot
   - Color by vulnerability:
     * Red dot = has PWD, senior, infant, or pregnant member
     * Blue dot = regular household
   - Only plot households with valid GPS coordinates
   - Click = popup: household head, family members count,
     vulnerability breakdown

4. Household density heatmap layer (toggle off by default)
   - Leaflet.heat plugin
   - Intensity based on family_members per household
   - Shows population concentration within barangays

5. Hazard zone layer (toggle on by default)
   - Keep existing circle markers as fallback if no boundary
   - If barangay has boundary_geojson → render colored polygon
     overlay colored by risk_level:
     * High Susceptible = red
     * Moderate Susceptible = orange
     * Low Susceptible = yellow
     * Prone / General Inundation = purple
   - Keep existing hazard type icons
   - Keep existing map legend panel exactly as designed

6. Incident polygon layer (toggle on by default)
   - Show all active/ongoing incident polygons on the map
   - Color by hazard type
   - Click = popup showing incident title, date, total
     affected population, status

7. Evacuation centers layer (toggle on by default)
   - Plot using evacuation_centers.latitude/longitude
   - Custom shelter icon
   - Click = popup: name, capacity, current_occupancy,
     facilities, contact person, status

8. Layer control panel (top right)
   - Toggle each layer independently
   - Group: Base Layers | Overlays

═══════════════════════════════════════════════════════════════════

PHASE 6 — POPULATION ARCHIVE & COMPARISON
------------------------------------------
1. Fix population upload/update:
   - Before saving new population_data, archive old data to
     population_data_archive with change_type = 'UPDATE'
   - After saving, call handle_population_update($barangay_id)
     to propagate to barangays and hazard_zones
   - Show confirmation message listing what was updated

2. Population comparison view (admin and division_chief):
   - Table showing all barangays:
     * Previous population (latest from archive)
     * Current population (from population_data or households sum)
     * Difference: +/- colored green/red
     * Hazard flag: warn if population increased in High or
       Moderate Susceptible zones
   - Filter by barangay, date range, hazard type
   - Export to CSV

═══════════════════════════════════════════════════════════════════

TECHNICAL CONSTRAINTS (strictly follow):
-----------------------------------------
- Plain PHP only, no framework, no composer
- MySQL / MariaDB database
- Shared hosting compatible (Hostinger)
- No Node.js, no npm builds
- Leaflet.js for all map features (load from CDN)
- Leaflet.draw plugin for polygon drawing (CDN)
- Leaflet.heat plugin for heatmap (CDN)
- Bootstrap 5.3 for UI (already in project)
- Font Awesome for icons (already in project)
- Point-in-polygon ray casting algorithm in PHP (not JS)
- All database queries via PDO with prepared statements
- No raw GET/POST without sanitization
- public_path style file handling for any uploads

═══════════════════════════════════════════════════════════════════

FUTURE VERSION ROADMAP (do not implement, reference only):
-----------------------------------------------------------
- Real-time live incident polygon generation from field reports
- Automatic affected area detection from sensor/external data
- Mobile app integration for live household GPS updates
- The household GPS infrastructure built in this version
  fully supports all of the above future upgrades