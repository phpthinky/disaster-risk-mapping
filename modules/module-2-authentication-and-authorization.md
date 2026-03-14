# Module 2 — Authentication & Authorization

## Goal
Secure the application with username-based login, role-based access control middleware, barangay-scoped policies, and role-aware redirect after login. No public registration — admin manages users.

---

## Scope

### Changes to Existing Files
| File | Change |
|------|--------|
| `LoginController.php` | Override `username()` → use `username` field; override `redirectTo()` → role-based; add `is_active` check |
| `resources/views/auth/login.blade.php` | Replace email field with username field |
| `routes/web.php` | Add role-guarded route groups; disable register route |
| `bootstrap/app.php` | Register `role` and `active` middleware aliases |

### New Files
| File | Purpose |
|------|---------|
| `app/Http/Middleware/CheckRole.php` | Gate by role: `->middleware('role:admin')` |
| `app/Http/Middleware/EnsureUserIsActive.php` | Reject login if `is_active = false` |
| `app/Http/Controllers/DashboardController.php` | Stub controller for role-based dashboard redirect |
| `app/Policies/BarangayPolicy.php` | Authorize barangay-scoped access for barangay_staff |
| `app/Policies/HouseholdPolicy.php` | Authorize household-scoped access |

---

## Role Access Matrix

| Route Group | admin | barangay_staff | division_chief |
|-------------|-------|----------------|---------------|
| `/dashboard` | ✅ | redirect → own | redirect → division |
| `/barangays` | ✅ CRUD | ✅ view own only | ✅ view all |
| `/households` | ✅ CRUD all | ✅ CRUD own barangay | ✅ view only |
| `/hazards` | ✅ CRUD | ✅ view | ✅ view |
| `/incidents` | ✅ CRUD | ✅ create/edit own | ✅ view |
| `/population` | ✅ view | ✅ view own | ✅ view all |
| `/users` | ✅ CRUD | ❌ | ❌ |
| `/announcements` | ✅ CRUD | ✅ view | ✅ view |
| `/alerts` | ✅ CRUD | ✅ view | ✅ view |

---

## Authentication Flow

```
POST /login (username + password)
  → EnsureUserIsActive check (is_active = true?)
  → authenticate (username field)
  → redirect based on role:
      admin          → /dashboard
      division_chief → /dashboard/division
      barangay_staff → /dashboard/barangay
```

---

## Files Created in This Module

```
app/Http/Middleware/
  ├── CheckRole.php
  └── EnsureUserIsActive.php
app/Http/Controllers/
  └── DashboardController.php
app/Policies/
  ├── BarangayPolicy.php
  └── HouseholdPolicy.php
```

---

## Acceptance Criteria

- [ ] Login with `username` field (not email)
- [ ] Inactive users (`is_active = false`) are rejected at login
- [ ] After login, each role redirects to its own dashboard route
- [ ] `->middleware('role:admin')` blocks non-admins with 403
- [ ] `->middleware('role:admin,division_chief')` allows either role
- [ ] Barangay staff can only view/edit their own barangay's data (policy)
- [ ] `/register` route is disabled
- [ ] Logout works and redirects to `/login`
