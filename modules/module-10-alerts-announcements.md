# Module 10 — Alerts & Announcements

## Goal
Two distinct notification features:
- **Alerts** — short system-wide or barangay-targeted hazard/emergency banners created by admin only; displayed prominently in the navbar (badge) and on dashboards.
- **Announcements** — longer-form posts created by admin or division chief; scoped by `target_audience` so only the intended roles see them.

Both support inline CRUD on a single index page (no separate create/edit pages).

---

## Routes

### Alerts (admin only)

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | `/alerts` | `alerts.index` | admin |
| POST | `/alerts` | `alerts.store` | admin |
| PUT | `/alerts/{alert}` | `alerts.update` | admin |
| DELETE | `/alerts/{alert}` | `alerts.destroy` | admin |
| PATCH | `/alerts/{alert}/toggle` | `alerts.toggle` | admin |

### Announcements (admin + division_chief)

| Method | URI | Name | Access |
|--------|-----|------|--------|
| GET | `/announcements` | `announcements.index` | all roles |
| POST | `/announcements` | `announcements.store` | admin, division_chief |
| PUT | `/announcements/{announcement}` | `announcements.update` | admin, division_chief |
| DELETE | `/announcements/{announcement}` | `announcements.destroy` | admin, division_chief |
| PATCH | `/announcements/{announcement}/toggle` | `announcements.toggle` | admin, division_chief |

> `create`, `edit`, and `show` resource routes are excluded — all CRUD is handled via inline modals on the index page.

---

## Files Created

```
app/Http/Controllers/AlertController.php
app/Http/Controllers/AnnouncementController.php
resources/views/alerts/
  └── index.blade.php
resources/views/announcements/
  └── index.blade.php
modules/module-10-alerts-announcements.md
```

*(Models `Alert` and `Announcement` already existed from Module 1)*

---

## Business Rules

### Alerts
1. **Admin-only** — `AlertController` calls `authorizeAdmin()` on every method; non-admins get 403.
2. **Types**: `warning`, `info`, `danger` — each renders with a matching Bootstrap colour.
3. **Barangay targeting** — `barangay_id` is nullable; null means system-wide.
4. **Active flag** — `is_active` controls visibility across the UI; can be toggled without editing.
5. **Navbar badge** — `layouts/app.blade.php` shows a count of active alerts on the bell icon.

### Announcements
1. **Write access** — admin and division chief; barangay staff are read-only.
2. **Audience scoping** — `target_audience` ∈ `[all, barangay_staff, division_chief, admin]`.
3. **`visibleTo` scope** — `Announcement::visibleTo($user)` query scope filters records so users only see announcements directed at their role (or `all`).
4. **Active flag** — same toggle pattern as alerts.

---

## Views

### alerts/index.blade.php
- Inline **create form** at the top (title, message, type, barangay, is_active checkbox).
- **Table** of all alerts with columns: Title, Type badge, Barangay (or "System-wide"), Status, Created At, Actions (Toggle / Edit modal / Delete).
- Edit uses a Bootstrap modal with a pre-filled form and hidden `PUT` spoofing.
- Delete uses a confirmation modal.
- Page restricted to admin only; other roles are redirected.

### announcements/index.blade.php
- Inline **create form** (title, message, type, target audience, is_active) — visible to admin + division chief.
- **Card list** of announcements: title, type badge, audience badge, active status, creator, date, message preview.
- Edit (modal) and delete (confirmation modal) visible to admin + division chief.
- Barangay staff see only their relevant announcements with no write controls.

---

## Acceptance Criteria

- [x] Admin can create/edit/delete/toggle alerts
- [x] Non-admin users get 403 on all alert write routes
- [x] Alerts can be scoped to a specific barangay or system-wide
- [x] Active alerts count shown as badge in navbar for all roles
- [x] Admin and division chief can post/edit/delete announcements
- [x] Barangay staff can view (but not write) announcements targeted at them
- [x] `visibleTo` scope correctly filters announcements per role
- [x] Toggle (activate/deactivate) works without a full edit
- [x] All CRUD handled inline on the index page (no separate create/edit routes)
