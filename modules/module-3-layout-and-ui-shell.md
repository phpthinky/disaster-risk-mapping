# Module 3 — Layout & UI Shell

## Goal
Create a consistent, responsive shell layout matching the old-app visual design: dark collapsible sidebar, dark top navbar, footer, role-aware menu items, and all third-party assets (Bootstrap 5, Font Awesome 6, Leaflet.js, Chart.js) loaded via CDN from the master layout.

---

## Scope

### Master Layout
| File | Description |
|------|-------------|
| `resources/views/layouts/app.blade.php` | Full shell: navbar + sidebar + content slot + footer |

### Blade Components
| Component | Description |
|-----------|-------------|
| `resources/views/components/sidebar.blade.php` | Dark collapsible sidebar with role-aware menu |
| `resources/views/components/navbar.blade.php` | Dark top navbar with user menu + active-alert badge |

### SCSS Update
| File | Change |
|------|--------|
| `resources/sass/app.scss` | CSS custom properties, sidebar/layout styles, card/stat styles |

### Updated Views
| File | Change |
|------|--------|
| `resources/views/dashboards/admin.blade.php` | Extend new layout with real section structure |
| `resources/views/dashboards/division.blade.php` | Same |
| `resources/views/dashboards/barangay.blade.php` | Same |

---

## Layout Structure

```
<body>
  [Navbar] — fixed top, full-width dark bar
  [Sidebar] — fixed left, dark, collapsible
  [main-wrapper]
    [content-wrapper]  ← @yield('content')
    [Footer]
  [Stack: scripts]     ← @stack('scripts')
</body>
```

---

## Sidebar Menu by Role

| Menu Item | Admin | Barangay Staff | Division Chief |
|-----------|-------|----------------|----------------|
| Dashboard | ✅ | ✅ | ✅ |
| Map View | ✅ | ✅ | ✅ |
| Households | ✅ | ✅ | ✅ (view) |
| Hazard Zones | ✅ | ✅ | ✅ |
| Incidents | ✅ | ✅ | ✅ |
| Population Data | ✅ | ✅ | ✅ |
| Evacuation Centers | ✅ | ✅ | ✅ |
| Barangays | ✅ | ✅ (own) | ✅ |
| Announcements | ✅ | ✅ | ✅ |
| Alerts | ✅ | ✅ | ✅ |
| Users | ✅ | ❌ | ❌ |
| Reports | ✅ | ✅ | ✅ |

---

## Files Created/Updated

```
resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php        (full shell layout)
│   ├── components/
│   │   ├── sidebar.blade.php    (role-aware sidebar)
│   │   └── navbar.blade.php     (top navbar)
│   └── dashboards/
│       ├── admin.blade.php      (updated to extend new layout)
│       ├── division.blade.php   (updated)
│       └── barangay.blade.php   (updated)
└── sass/
    └── app.scss                 (sidebar + layout styles)
```

---

## Acceptance Criteria

- [ ] Layout renders with sidebar + navbar + footer for authenticated users
- [ ] Sidebar collapses/expands on toggle with localStorage persistence
- [ ] Active menu item highlighted based on current route
- [ ] Sidebar menu items filtered by role (Users link hidden from non-admins)
- [ ] User avatar initial + name + role displayed in navbar dropdown
- [ ] Logout works via POST form
- [ ] Bootstrap 5, Font Awesome 6, Leaflet, Chart.js available on all pages
- [ ] `@stack('scripts')` slot available for page-specific JS
- [ ] Mobile responsive: sidebar off-canvas on small screens
