DISASTER RISK MANAGEMENT SYSTEM — COMPLETE REWRITE COMMAND v2
==============================================================

CONTEXT (Read this carefully before touching anything):
-------------------------------------------------------
This system was originally built in 2022 with a simple vector map,
manual affected population input, and population subtracted from
barangay totals. No household records.

Students rebuilt it in 2025-2026 and added households, population_data,
archive tables — BUT never connected anything together. Every table
is isolated, no meaningful JOINs, map still shows circle markers,
all population numbers are manually typed, household GPS coordinates
are never used for anything.

We are now doing a complete rewrite of the system logic, map, and
data flow. The goal is to connect everything through a proper
hierarchy and auto-compute all population data from household records.

HIERARCHY (strictly follow this top to bottom):
------------------------------------------------
Admin
  └── Creates and manages Barangays
  └── Assigns barangay_staff user to each Barangay
        └── Barangay Staff creates Households in their Barangay
              └── System AUTO-COMPUTES everything from Households:
                    - barangays.population
                    - barangays.pwd_count
                    - barangays.senior_count
                    - barangays.children_count
                    - barangays.infant_count
                    - barangays.pregnant_count
                    - barangays.ip_count
                    - population_data (computed, not manual)
                    - hazard_zones.affected_population (computed)
                    - incident_reports → affected_areas (computed)

GOLDEN RULE:
------------
No population number in this system should ever be typed manually.
All counts must be computed from the households table.
Manual population entry forms must be removed or disabled.
population_data table becomes a computed summary, not manual input.

OUT OF SCOPE FOR THIS VERSION:
-------------------------------
- Real-time live polygon drawing during active disaster events
- Automatic polygon generation from sensor or external data feeds
- Mobile app integration

These are noted in the system UI as future upgrade capabilities.

═══════════════════════════════════════════════════════════════════
Before performing any audit or writing any code, reorganize the project into a feature-based directory structure. Do not place all PHP files in a single root directory.

All system components must be grouped by module.

Example structure:

/config
/core
/modules
    /barangays
    /households
    /hazards
    /incidents
    /population
    /evacuation
/map
/api
/assets
/includes

Rules:

Each module contains its own CRUD files, AJAX handlers, and UI pages.

Shared functions such as handle_sync() must be placed inside /core.

Map logic should be placed inside /map.

API/AJAX endpoints must be placed inside /api.

UI layout components such as header, sidebar, and footer go inside /includes.

All file paths and includes must follow this structure from the start.

Do not generate files in the root directory except for the main entry pages if necessary.
═══════════════════════════════════════════════════════════════════
UI REDESIGN REQUIREMENT (Add this as PHASE 8)
UI GOAL

Redesign the interface to avoid compressed cards and overcrowded pages from the old system design.

The new UI must prioritize:

clarity

larger map workspace

separated workflows

minimal page reloads

faster data entry for staff

Use Bootstrap 5.3, jQuery, and AJAX-based CRUD operations.

Layout Rules
1. Avoid stacked cards

Do NOT stack multiple cards vertically for complex pages.

Old design example (bad):

[Card: Statistics]
[Card: Map]
[Card: Table]
[Card: Form]

This causes long scrolling and cramped layouts.

Instead use:

Tabs
Modals
Split panels
Use Tabs for multi-sections

Pages with multiple related functions should use Bootstrap Tabs.

Example: household_management.php

Tab 1 — Household List
Tab 2 — Add Household
Tab 3 — GPS Data Quality Report
Tab 4 — Members Management

Benefits:

page stays organized

user focuses on one task

less vertical scrolling

Use Modals for CRUD operations

All CRUD forms should open inside Bootstrap Modals.

Example:

Household List
   ├── Add Household (Modal)
   ├── Edit Household (Modal)
   └── Delete Confirmation (Modal)

Benefits:

user stays on same page

faster workflow

avoids full page navigation

Use jQuery AJAX for CRUD

To prevent full page reloads:

Use AJAX for Create / Update / Delete

Submit forms with $.ajax()

Update tables dynamically

Show success messages using Bootstrap alerts or toast

Example flow:

User clicks "Add Household"
      ↓
Modal opens
      ↓
Form submit via AJAX
      ↓
Server saves record
      ↓
handle_sync() runs
      ↓
Table refreshes via AJAX
      ↓
Modal closes

No page reload required.

Map UI Priority

Map pages must give maximum space to the map.

Example layout:

-------------------------------------
| Sidebar |        MAP              |
| Stats   |                         |
| Filters |                         |
-------------------------------------

Rules:

Map should occupy 70–80% of screen width

Sidebar used for filters, legend, or controls

Avoid placing map inside small cards

Table Handling

Use searchable and paginated tables.

Recommended approach:

client-side pagination with jQuery

column filters

quick search bar

Example:

Search: [__________]

| Household Head | Barangay | Members | Actions |
Form Usability

Forms must include:

grouped sections

icons for vulnerability fields

validation feedback

clear GPS input section

Example:

Household Information
Family Composition
GPS Coordinates
Preparedness Data
Notification System

Use:

Bootstrap Toast or Alert

Success / Error / Warning messages

Examples:

✔ Household saved successfully
⚠ Invalid GPS coordinate
✔ Population data updated

═══════════════════════════════════════════════════════════════════
PHASE 0 — AUDIT FIRST, NO CHANGES YET
---------------------------------------
1.  List all PHP files and describe what each one does in one line
2.  Find the main map file — read it completely
3.  For every table, show how it is queried — JOINs or standalone?
4.  Check how hazard_zones.affected_population is currently set
5.  Check if households.latitude/longitude is ever used on the map
6.  Check if barangays.population is ever recomputed from households
7.  Check how population_data is currently entered — manual or computed?
8.  List all AJAX/API endpoints and what they return to the map
9.  Check how risk zones are currently added by staff
10. Check current map tile layer — is it only OpenStreetMap?
11. List all existing map legends, icons, colors exactly as they are
12. Report ALL findings before writing a single line of code

═══════════════════════════════════════════════════════════════════

PHASE 1 — DATABASE CLEANUP AND REWIRE
--------------------------------------
Do not create new tables unless specified. Fix existing relationships.

1. Add computed columns to barangays table if not existing:
   - pwd_count        INT DEFAULT 0
   - senior_count     INT DEFAULT 0  
   - children_count   INT DEFAULT 0
   - infant_count     INT DEFAULT 0
   - pregnant_count   INT DEFAULT 0
   - ip_count         INT DEFAULT 0
   - household_count  INT DEFAULT 0
   - boundary_geojson LONGTEXT DEFAULT NULL

2. Create a reusable PHP function sync_barangay($barangay_id):
   - Queries households table for all records WHERE barangay_id matches
   - Computes and updates:
     * barangays.population     = SUM(family_members)
     * barangays.household_count = COUNT(id)
     * barangays.pwd_count      = SUM(pwd_count)
     * barangays.senior_count   = SUM(senior_count)
     * barangays.children_count = SUM(minor_count + child_count)
     * barangays.infant_count   = SUM(infant_count)
     * barangays.pregnant_count = SUM(pregnant_count)
     * barangays.ip_count       = COUNT where ip_non_ip = 'IP'
   - Call this function after EVERY household insert/update/delete

3. Create a reusable PHP function sync_population_data($barangay_id):
   - Inserts or updates population_data from households aggregates
   - Archives old population_data to population_data_archive before
     overwriting with change_type = 'UPDATE'
   - Call this function after sync_barangay() runs

4. Create a reusable PHP function sync_hazard_zones($barangay_id):
   - Updates hazard_zones.affected_population from
     barangays.population for all zones linked to that barangay_id
   - Call this after sync_population_data() runs

5. Create master function handle_sync($barangay_id) that calls:
   sync_barangay($barangay_id)
   → sync_population_data($barangay_id)
   → sync_hazard_zones($barangay_id)
   in that exact order

6. Remove or disable all manual population input forms
   population_data should never be manually entered again

═══════════════════════════════════════════════════════════════════

PHASE 2 — BARANGAY CRUD (Admin only)
--------------------------------------
Full barangay management. This is where everything starts.

1. Create barangay_management.php (admin only):

   List view:
   - Table of all barangays with columns:
     * Barangay name
     * Assigned staff user
     * Total households
     * Total population (computed)
     * Boundary status: Drawn / Not yet drawn
     * Actions: Edit, Draw Boundary, View Households

   Add Barangay form:
   - Barangay name (required)
   - Area in km2 (optional)
   - Center coordinates (lat/lng) for map default view
   - Assign barangay_staff user from users dropdown
     (only show users with role = barangay_staff)
   - On save: insert to barangays, update users.barangay_id

   Edit Barangay form:
   - Same fields as add
   - Show current computed stats (read only):
     * Total households, population, PWD, seniors, etc.
   - Cannot manually edit population — shown as computed only

   Delete Barangay:
   - Only allow if no households are linked
   - Show warning if households exist

2. Create barangay_boundary.php (admin only):
   - Full screen Leaflet map with ALL map tile options:
     * OpenStreetMap (default)
     * Satellite (Esri World Imagery)
     * Terrain (Esri World Terrain or OpenTopoMap)
     * Hybrid (Esri World Street + Satellite)
   - Side panel listing all barangays with status:
     * Green checkmark = boundary already drawn
     * Red warning = not yet drawn
     * Progress: X of 22 barangays mapped
   - Click barangay name → map zooms to center coordinate
   - Draw polygon using Leaflet Draw plugin
   - If boundary exists → load it on map as editable polygon
   - Save/update boundary_geojson via AJAX
   - Cannot proceed to map upgrade until all boundaries are drawn

═══════════════════════════════════════════════════════════════════

PHASE 3 — HOUSEHOLD CRUD (Barangay Staff only)
-----------------------------------------------
Barangay staff can only manage households in their assigned barangay.
Admin can manage all barangays.

1. Create household_management.php:

   List view:
   - Filter by barangay (admin sees all, staff sees own only)
   - Table columns:
     * Household ID
     * Household Head name
     * Zone / Sitio / Purok
     * Family members count
     * Vulnerability icons (PWD, Senior, Infant, Pregnant, IP)
     * GPS status: Valid / Invalid / Missing
     * Actions: View, Edit, Delete
   - Summary cards at top (computed from households):
     * Total Households
     * Total Population
     * PWD Count
     * Senior Count
     * Children Count
     * IP Count

   Add Household form:
   - Household Head name (required)
   - Barangay (admin: dropdown, staff: auto-set to their barangay)
   - Zone / Sitio / Purok
   - Sex, Age, Gender of household head
   - House type
   - IP / Non-IP
   - Household ID (hh_id)
   - Educational attainment
   - Preparedness kit: Yes/No

   Family composition (auto-updates counts):
   - Family members total
   - PWD count
   - Pregnant count
   - Senior count (60+)
   - Infant count (0-2)
   - Minor count
   - Child count
   - Adolescent count
   - Young adult count
   - Adult count
   - Middle aged count

   GPS Coordinates section (REQUIRED — cannot save without valid GPS):

     Option A — Click on Map (office encoding):
     - Leaflet map picker inside the form
     - Click anywhere on map to drop a marker
     - Latitude/longitude fields auto-fill from click
     - Map defaults to barangay center coordinate
     - All 4 tile layers available:
       * OpenStreetMap
       * Satellite (Esri World Imagery)
       * Terrain
       * Hybrid
     - Satellite view recommended for finding exact house location

     Option B — Use Device GPS (field data collection):
     - "Use My Current Location" button
     - Gets coordinates from browser geolocation API
     - Auto-fills latitude/longitude and drops marker on map
     - Designed for staff physically at the household location

     Option C — Manual coordinate input (fallback):
     - Direct text input for latitude and longitude
     - Updates map marker in real time as user types
     - Useful when coordinates are known from another source

     Validation (apply to all 3 options):
     - Latitude must be between 12.50 and 13.20
     - Longitude must be between 120.50 and 121.20
     - Reject 0,0 or 99.999 or any obviously invalid value
     - Show error message if out of bounds
     - GPS is REQUIRED — form cannot submit without valid coords

   On save:
   - Insert household record
   - Call handle_sync($barangay_id) to recompute everything

   Edit Household form:
   - Same as Add form
   - Existing coordinates shown as draggable marker on map
   - Staff can drag marker to correct wrong position
   - All 4 tile layers available for easy location finding
   - On save: call handle_sync($barangay_id)

   Delete Household:
   - Confirm dialog
   - On delete: call handle_sync($barangay_id)

2. GPS Data Quality Report (admin only):
   - List all households with missing or invalid coordinates
   - Count per barangay
   - Quick link to edit each flagged household
   - Shows % of GPS-verified households per barangay

3. Household Members sub-form:
   - Add/edit/delete individual members per household
   - Fields: full name, age, gender, relationship
   - Flags: is_pwd, is_pregnant, is_senior, is_infant, is_minor
   - On any change: call handle_sync($barangay_id)

═══════════════════════════════════════════════════════════════════

PHASE 4 — HAZARD ZONE MANAGEMENT
----------------------------------
Hazard zones are pre-mapped areas where disaster MIGHT occur.
This is NOT the same as incident reports.
Hazard zones are permanent risk assessments, not disaster events.

1. Update existing hazard zone add/edit form:
   - Remove manual affected_population input field completely
   - affected_population is now read-only, computed from
     barangays.population of the linked barangay_id
   - Keep all other fields: hazard_type, barangay, risk_level,
     area_km2, coordinates, description
   - Add map picker for coordinates using all 4 tile layers
   - Satellite/terrain view essential for identifying actual
     hazard geography (flood plains, slopes, coastlines)

═══════════════════════════════════════════════════════════════════

PHASE 5 — INCIDENT REPORTS (New feature)
-----------------------------------------
Incident reports record actual disaster events that happened.
This is completely separate from hazard_zones.

hazard_zones  = where disaster MIGHT happen (permanent)
incident_reports = disaster that DID happen (event-based)

1. Create new table incident_reports:
   - id
   - title VARCHAR(255)
   - hazard_type_id (FK to hazard_types)
   - incident_date DATE
   - status ENUM('ongoing','resolved','monitoring')
   - affected_polygon LONGTEXT (GeoJSON of drawn polygon)
   - description TEXT
   - reported_by INT (FK to users)
   - created_at TIMESTAMP
   - updated_at TIMESTAMP

2. Create new table affected_areas:
   - id
   - incident_id (FK to incident_reports)
   - barangay_id (FK to barangays)
   - affected_households INT
   - affected_population INT
   - affected_pwd INT
   - affected_seniors INT
   - affected_infants INT
   - affected_minors INT
   - affected_pregnant INT
   - ip_count INT
   - computed_at TIMESTAMP

3. Create incident_reports.php (admin and barangay_staff):

   List view:
   - Table: title, disaster type, date, status,
     total affected population, actions
   - Filter by status, hazard type, date range
   - Summary: total incidents, total affected population

   Add Incident Report form:
   - Incident title (required)
   - Disaster type from hazard_types dropdown (required)
   - Incident date (required)
   - Status: ongoing / resolved / monitoring
   - Description

   Map section — Draw Affected Area:
   - Full Leaflet map with all 4 tile layers:
     * OpenStreetMap
     * Satellite (Esri World Imagery)
     * Terrain
     * Hybrid
   - Satellite recommended — staff can see actual affected
     landscape, flooded fields, damaged areas
   - Leaflet Draw plugin — staff draws polygon over affected area
   - Show existing household dots on map as reference
   - Show barangay boundaries as reference layer
   - Polygon coordinates saved as GeoJSON

   Auto-computation on save (PHP server-side):
   - Run point-in-polygon ray casting algorithm in PHP
   - Check every household WHERE latitude AND longitude are valid
     against the drawn polygon GeoJSON
   - For each household inside the polygon, aggregate:
     * affected_households = COUNT of households inside polygon
     * affected_population = SUM(family_members)
     * affected_pwd        = SUM(pwd_count)
     * affected_seniors    = SUM(senior_count)
     * affected_infants    = SUM(infant_count)
     * affected_minors     = SUM(minor_count)
     * affected_pregnant   = SUM(pregnant_count)
     * ip_count            = COUNT where ip_non_ip = 'IP'
   - Group results per barangay
   - Save to affected_areas table per barangay

   Results shown after save:
   - Total affected population
   - Breakdown table per barangay
   - Vulnerability summary: PWD, seniors, infants, pregnant, IP
   - Warning if households with invalid GPS were excluded
     from computation (shows count of excluded households)

   Developer note (show as info box in the form, visible to admin):
   "SYSTEM CAPABILITY NOTE: This system currently supports manual
   polygon drawing for affected area mapping after an incident
   occurs. This can be upgraded in a future version to support
   real-time GPS-based incident tracking where the affected polygon
   is automatically generated from live field reports submitted
   during an active disaster. The household GPS infrastructure is
   already in place to support this upgrade."

═══════════════════════════════════════════════════════════════════

PHASE 6 — MAIN MAP UPGRADE
----------------------------
Complete replacement of the circle marker map.
Remove ALL circle markers — do not keep as fallback.
The new map is fully polygon and GPS point based.

MAP TILE LAYERS (available on ALL map pages in this system):
- OpenStreetMap    — default, shows roads and place names
- Satellite        — Esri World Imagery, shows actual terrain,
                     coastlines, flood plains, forest, farmland
                     ESSENTIAL for hazard identification
- Terrain          — OpenTopoMap or Esri World Terrain,
                     shows elevation, slopes, mountain ranges
                     ESSENTIAL for landslide/ground shaking zones
- Hybrid           — Satellite + street labels overlay
                     Best for finding specific household locations

Load tile layers from CDN:
- OpenStreetMap: https://tile.openstreetmap.org/{z}/{x}/{y}.png
- Esri Satellite: https://server.arcgisonline.com/ArcGIS/rest/
  services/World_Imagery/MapServer/tile/{z}/{y}/{x}
- Esri Terrain: https://server.arcgisonline.com/ArcGIS/rest/
  services/World_Terrain_Base/MapServer/tile/{z}/{y}/{x}
- OpenTopoMap: https://tile.opentopomap.org/{z}/{x}/{y}.png

Add tile layer switcher control (top right of map).

MAP LAYERS (bottom to top order):

Layer 1 — Barangay Boundaries (always visible, cannot toggle off):
- Render boundary_geojson polygons from barangays table
- Neutral light fill color
- Barangay name label in center
- Click = popup:
  * Barangay name
  * Total population (computed from households)
  * Total households
  * PWD count, Senior count, IP count
  * Active hazard types

Layer 2 — Hazard Zones (toggle ON by default):
- REMOVE all circle markers completely
- Render as colored polygon overlays on barangay boundaries
- Color by risk_level:
  * High Susceptible              = red (#e74c3c) semi-transparent
  * Moderate Susceptible          = orange (#e67e22) semi-transparent
  * Low Susceptible               = yellow (#f1c40f) semi-transparent
  * Prone                         = purple (#9b59b6) semi-transparent
  * Generally Susceptible         = blue (#3498db) semi-transparent
  * PEIS VIII Very destructive    = dark red (#922b21) semi-transparent
  * PEIS VII Destructive          = red-orange (#cb4335) semi-transparent
  * General Inundation            = teal (#1abc9c) semi-transparent
  * Not Susceptible               = green (#27ae60) semi-transparent
- Keep ALL existing hazard type icons on top of polygon
- Keep existing map legend panel exactly as designed
- Click = popup: hazard type, risk level, affected population,
  barangay name, area km2

Layer 3 — Household Locations (toggle OFF by default):
- Plot each household as small circle dot
- Only plot households with VALID GPS coordinates
- Color by vulnerability:
  * Red dot   = has PWD, senior, infant, or pregnant member
  * Blue dot  = regular household
- Click = popup:
  * Household head name
  * Family members count
  * Zone / Sitio / Purok
  * Vulnerability: PWD, Senior, Infant, Pregnant, IP flags

Layer 4 — Household Density Heatmap (toggle OFF by default):
- Leaflet.heat plugin (load from CDN)
- Each point = household GPS coordinate
- Intensity = family_members count
- Shows where population is concentrated
- Useful for identifying dense communities in hazard zones

Layer 5 — Active Incidents (toggle ON by default):
- Show all incident_reports WHERE status = 'ongoing' or 'monitoring'
- Render affected_polygon as colored overlay
- Color by hazard_type matching hazard_types.color
- Dashed border to distinguish from hazard zones
- Click = popup:
  * Incident title
  * Disaster type
  * Date
  * Status
  * Total affected population
  * Affected barangays list

Layer 6 — Evacuation Centers (toggle ON by default):
- Plot using evacuation_centers.latitude/longitude
- Custom shelter icon (distinct from hazard icons)
- Color by status:
  * Operational = green icon
  * Maintenance = yellow icon
  * Closed      = red icon
- Click = popup:
  * Name
  * Capacity vs current occupancy (progress bar)
  * Facilities list
  * Contact person and number
  * Status

LAYER CONTROL PANEL:
- Position: top right
- Group Base Layers: OSM | Satellite | Terrain | Hybrid
- Group Overlays:
  * Barangay Boundaries (locked on)
  * Hazard Zones
  * Household Locations
  * Population Heatmap
  * Active Incidents
  * Evacuation Centers

PRESERVE EXACTLY:
- All existing legend panel design and layout
- All existing hazard type icons
- All existing color coding for risk levels
- All existing sidebar stats (Barangays count, Hazard Zones, Total Affected)

═══════════════════════════════════════════════════════════════════

PHASE 7 — POPULATION ARCHIVE AND COMPARISON
---------------------------------------------
Population data is now computed, not entered manually.
Archive happens automatically via handle_sync().

1. Population history view (admin and division_chief):
   - Timeline of population changes per barangay
   - Pulled from population_data_archive table
   - Shows what changed, when, and by how much

2. Population comparison view (admin and division_chief):
   - Side by side table: all barangays
   - Columns:
     * Barangay name
     * Previous population (from archive)
     * Current population (computed from households)
     * Difference: +/- colored green (increase) / red (decrease)
     * Hazard warning flag: if population increased in a barangay
       that has High or Moderate Susceptible hazard zone
   - Filter by barangay, date range, hazard type
   - Export to CSV button

═══════════════════════════════════════════════════════════════════

TECHNICAL CONSTRAINTS (strictly follow):
-----------------------------------------
- Plain PHP only, no framework, no composer
- MySQL / MariaDB database
- Shared hosting compatible (Hostinger)
- No Node.js, no npm builds
- Leaflet.js for all map features (load from CDN)
- Leaflet.draw plugin for polygon drawing (load from CDN)
- Leaflet.heat plugin for heatmap (load from CDN)
- Esri and OpenTopoMap tile layers (load from CDN, no API key needed)
- Bootstrap 5.3 for UI (already in project)
- Font Awesome for icons (already in project)
- Bootstrap Icons for additional icons (already in project)
- Point-in-polygon ray casting algorithm implemented in PHP
- All database queries via PDO with prepared statements
- Sanitize all GET/POST inputs before use
- Role-based access strictly enforced:
  * admin        = full access to everything
  * division_chief = view only, reports, comparison
  * barangay_staff = own barangay households only
- Call handle_sync($barangay_id) after every household change
- All map tile layers available on every map page in the system

═══════════════════════════════════════════════════════════════════

FUTURE VERSION ROADMAP (do not implement, reference only):
-----------------------------------------------------------
The following features are intentionally deferred to the next
development cycle. The current system infrastructure fully
supports all of these upgrades:

1. Real-time live incident polygon generation from field reports
2. Automatic affected area detection during active disaster events
3. Mobile app for field staff to submit live household GPS updates
4. Point-in-polygon computation triggered in real-time as polygon
   is being drawn on the map
5. Integration with PAGASA or PHIVOLCS data feeds for automatic
   hazard zone updates

The household GPS infrastructure, boundary polygons, and
point-in-polygon computation built in this version are the
exact foundation needed for all future upgrades listed above.


[Note: You can redesign the UI for more manageable style. Because this old design is to compressed with to many data card and table showing in one page that can be change into modal style or hidden tab style, You can change CRUD as JQUERY with notify js to prevent all page submit]