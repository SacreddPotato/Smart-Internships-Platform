# Smart Internship Matching Platform — Implementation Plan

> **Approach:** Vertical slices (model → view, one feature at a time).
> **Why:** Each slice is a working end-to-end feature. You get visible progress fast, catch integration bugs early, and never have "all backend done, no frontend" or vice versa.

---

## Core Principle

**Don't build horizontally** (all migrations → all models → all controllers → all UI).
**Build vertically** (one feature fully working before starting the next).

After every backend slice: **test in Postman before touching React**. If it works in Postman but fails in React → bug is in React. If both fail → bug is in backend. Cuts debugging time in half.

---

## Phase 0 — Project Setup (no features yet)

**Goal:** Two empty projects that can talk to each other.

### Backend
```bash
composer create-project laravel/laravel smart-internship-api
cd smart-internship-api
composer require laravel/sanctum
php artisan install:api
```

- Configure `.env` with DB credentials (MySQL or PostgreSQL)
- Run `php artisan migrate` (confirms DB connection works)
- Enable CORS in `config/cors.php` for `http://localhost:5173`

### Frontend
```bash
npm create vite@latest smart-internship-frontend -- --template react
cd smart-internship-frontend
npm install axios react-router-dom
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p
```

- Create `src/api/axios.js` with base URL `http://localhost:8000/api/v1`
- Add request interceptor that reads token from `localStorage` and attaches `Authorization: Bearer {token}`

### Exit Criteria
- `php artisan serve` runs on :8000
- `npm run dev` runs on :5173
- Browser can fetch a test endpoint from React without CORS errors

---

## Slice 1 — Authentication (ALWAYS FIRST)

**Why first:** Every other feature depends on knowing who the user is.

### Backend Tasks
| # | Task | File(s) |
|---|------|---------|
| 1 | Add `role` column migration | `xxxx_add_role_to_users_table.php` |
| 2 | Create `UserRole` enum | `app/Enums/UserRole.php` |
| 3 | Update `User` model — cast role, `HasApiTokens` trait | `app/Models/User.php` |
| 4 | Create `RegisterRequest` + `LoginRequest` | `app/Http/Requests/Auth/` |
| 5 | Create `AuthController` with `register`, `login`, `logout`, `me` | `app/Http/Controllers/Api/V1/AuthController.php` |
| 6 | Create `UserResource` | `app/Http/Resources/UserResource.php` |
| 7 | Register routes under `/api/v1` prefix | `routes/api.php` |
| 8 | Create `RoleMiddleware` | `app/Http/Middleware/RoleMiddleware.php` |

### Postman Verification
- [ ] `POST /api/v1/register` — creates user, returns token
- [ ] `POST /api/v1/login` — returns token
- [ ] `GET /api/v1/me` — with Bearer token returns user
- [ ] `GET /api/v1/me` — without token returns 401

### Frontend Tasks
| # | Task | File(s) |
|---|------|---------|
| 1 | `AuthContext` — stores user + token, exposes `login`, `logout`, `register` | `src/contexts/AuthContext.jsx` |
| 2 | `authApi.js` — wraps register/login/logout/me calls | `src/api/authApi.js` |
| 3 | `Login.jsx` + `Register.jsx` pages | `src/pages/` |
| 4 | `ProtectedRoute.jsx` wrapper | `src/components/common/` |
| 5 | `RoleRoute.jsx` wrapper | `src/components/common/` |
| 6 | Router setup with public + protected routes | `src/router/index.jsx` |

### Exit Criteria
- Register a student from UI → see token in localStorage
- Log out → token cleared, redirected to login
- Visit protected route without login → redirected to login
- Log in as company → accessing student-only route shows 403/redirect

---

## Slice 2 — Browse Internships (Public, Read-Only)

**Why second:** Establishes the listing pattern used everywhere. No auth required, so you can focus on the Model → Resource → List flow.

### Backend Tasks
| # | Task |
|---|------|
| 1 | Migrations: `company_profiles`, `internships`, `skills`, `internship_skill` |
| 2 | Models + relationships (`Internship belongsTo company`, `belongsToMany skills`) |
| 3 | `InternshipStatus`, `InternshipType` enums |
| 4 | `SkillSeeder` — insert ~20 common skills |
| 5 | `InternshipFactory` + seeder with 15 fake internships |
| 6 | `InternshipResource` + `InternshipCollection` |
| 7 | `InternshipController@index` (paginated) + `@show` |
| 8 | Routes (public): `GET /internships`, `GET /internships/{id}` |

### Postman Verification
- [ ] `GET /api/v1/internships` returns paginated list
- [ ] `GET /api/v1/internships/1` returns one with company + skills nested
- [ ] `GET /api/v1/internships?search=react` filters results

### Frontend Tasks
| # | Task |
|---|------|
| 1 | `internshipApi.js` — `fetchAll(filters)`, `fetchOne(id)` |
| 2 | `InternshipCard.jsx` component |
| 3 | `InternshipList.jsx` + `InternshipFilters.jsx` |
| 4 | `Browse.jsx` page at `/internships` |
| 5 | `Detail.jsx` page at `/internships/:id` |
| 6 | `LoadingSpinner` + `ErrorAlert` common components |
| 7 | `Pagination.jsx` component |

### Exit Criteria
- `/internships` shows cards from real DB data
- Clicking a card navigates to detail page
- Filter by search term works
- Loading and error states render correctly

---

## Slice 3 — Company Creates Internships

**Why third:** First write operation. Introduces FormRequest + Policy + auth-scoped routes.

### Backend Tasks
| # | Task |
|---|------|
| 1 | `CompanyProfile` relationship wiring on `User` |
| 2 | `StoreInternshipRequest` + `UpdateInternshipRequest` |
| 3 | `InternshipPolicy` (create/update/delete — only owning company) |
| 4 | Register policy in `AuthServiceProvider` |
| 5 | `InternshipService::create()`, `update()`, `archive()`, `delete()` |
| 6 | `InternshipController@store`, `@update`, `@destroy`, `@archive`, `@companyIndex`, `@archived` |
| 7 | Routes with `role:company` middleware |
| 8 | Add `SoftDeletes` trait to `Internship` model |

### Postman Verification
- [ ] As company: `POST /internships` creates one
- [ ] As student: `POST /internships` returns 403
- [ ] Update someone else's internship → 403 (policy works)
- [ ] `DELETE` soft-deletes (row still exists with `deleted_at`)
- [ ] `PATCH /internships/{id}/archive` changes status
- [ ] `GET /company/internships/archived` returns only archived

### Frontend Tasks
| # | Task |
|---|------|
| 1 | `InternshipForm.jsx` — controlled inputs, skill multiselect |
| 2 | `InternshipCreate.jsx` page |
| 3 | `InternshipEdit.jsx` page |
| 4 | `Internships.jsx` (company's own list) |
| 5 | `ArchivedInternships.jsx` |
| 6 | Archive/delete buttons with confirmation |
| 7 | Form validation errors mapped from 422 response |

### Exit Criteria
- Company creates internship from UI → appears in public Browse
- Company edits → change reflected
- Company archives → no longer in Browse, shows in Archived tab
- Validation errors from backend display next to the correct fields

---

## Slice 4 — Student Profile + CV Upload

**Why now:** Applications need a student profile to exist.

### Backend Tasks
| # | Task |
|---|------|
| 1 | `student_profiles` migration + `student_skill` pivot |
| 2 | `StudentProfile` model + relationships |
| 3 | Auto-create profile on student registration (in `AuthService`) |
| 4 | `UpdateProfileRequest` + `UploadCvRequest` |
| 5 | `StudentProfileService` — handles profile update + file storage |
| 6 | `StudentProfileController` — `show`, `update`, `uploadCv` |
| 7 | `StudentSkillController` — `index`, `sync` |
| 8 | Configure `storage:link` for public CV access (if needed) |

### Postman Verification
- [ ] `GET /student/profile` returns profile
- [ ] `PUT /student/profile` updates fields
- [ ] `POST /student/profile/cv` with multipart file → stores in `storage/app/cvs`
- [ ] File size/type validation rejects oversize or non-PDF

### Frontend Tasks
| # | Task |
|---|------|
| 1 | `studentApi.js` — profile + skill endpoints |
| 2 | `ProfileForm.jsx` — editable fields |
| 3 | `SkillSelector.jsx` — multiselect |
| 4 | `FileUpload.jsx` reusable component with progress |
| 5 | `Profile.jsx` page |

### Exit Criteria
- Student updates profile → persists after refresh
- CV uploads successfully, link works
- Skills sync correctly (add/remove)

---

## Slice 5 — Student Applies to Internship

**Why now:** All prerequisites exist (profile, internships, skills).

### Backend Tasks
| # | Task |
|---|------|
| 1 | `applications` migration — unique constraint on `(student_id, internship_id)` |
| 2 | `Application` model + `ApplicationStatus` enum |
| 3 | `StoreApplicationRequest` |
| 4 | `ApplicationPolicy` — student can view own, company can view those to their internships |
| 5 | `ApplicationService::apply()` — create + calculate match score |
| 6 | `ApplicationController` — `store`, `studentIndex`, `companyIndex`, `show` |
| 7 | Routes with appropriate role middleware |

### Postman Verification
- [ ] Student applies → 201 with match_score
- [ ] Student applies to same internship twice → 422 (unique)
- [ ] `GET /student/applications` returns only own
- [ ] `GET /company/applications` returns only to company's internships

### Frontend Tasks
| # | Task |
|---|------|
| 1 | `applicationApi.js` — apply, list, updateStatus |
| 2 | `ApplyModal.jsx` on internship detail page |
| 3 | `ApplicationCard.jsx` |
| 4 | `Applications.jsx` page (student) |
| 5 | Disable Apply button if already applied |

### Exit Criteria
- Student applies from detail page → confirmation + redirect
- Reapplying shows "already applied"
- Applications list shows status + match score

---

## Slice 6 — Match Score Logic

**Why now:** Applications exist to display the score against. Also a small standalone feature — easy to test in isolation.

### Backend Tasks
| # | Task |
|---|------|
| 1 | `MatchScoreService::calculate(Student, Internship)` pure function |
| 2 | Skill overlap: `matched / required * 100` |
| 3 | Bonus: +5 if GPA > 3.5, +5 if graduation year matches |
| 4 | `MatchController@score` and `@recommendations` |
| 5 | Unit test for `MatchScoreService` with known inputs/outputs |

### Postman Verification
- [ ] `GET /internships/{id}/match-score` returns number 0-100
- [ ] `GET /student/recommendations` returns top-matched internships

### Frontend Tasks
| # | Task |
|---|------|
| 1 | `matchApi.js` |
| 2 | `MatchScoreBadge.jsx` — colored by score tier |
| 3 | Show badge on `InternshipCard` when student is logged in |
| 4 | `Recommendations.jsx` page |

### Exit Criteria
- Students see match score on every internship
- Recommendations page sorted by score desc
- Unit tests for match logic pass

---

## Slice 7 — Company Manages Applicants

**Why now:** Companies need to see who applied.

### Backend Tasks
| # | Task |
|---|------|
| 1 | `UpdateStatusRequest` — validates status transitions |
| 2 | `ApplicationController@updateStatus` |
| 3 | Policy check: only company that owns the internship |

### Postman Verification
- [ ] Company sets status to accepted/rejected/reviewed
- [ ] Other company can't change it → 403

### Frontend Tasks
| # | Task |
|---|------|
| 1 | `Applicants.jsx` page — list of applications with student info |
| 2 | `ApplicantRow.jsx` with status dropdown |
| 3 | CV download link |

### Exit Criteria
- Company reviews applications, changes status
- Student sees updated status on their side

---

## Slice 8 — Admin Dashboard

**Why last:** Aggregates from all the data now in the system.

### Backend Tasks
| # | Task |
|---|------|
| 1 | Seed an admin user |
| 2 | `AdminDashboardService` — counts, trends (applications per day, active internships, top companies) |
| 3 | `AdminDashboardController@index` |
| 4 | `AdminUserController@index`, `@destroy` |
| 5 | `AdminDashboardResource` |

### Postman Verification
- [ ] `GET /admin/dashboard` returns aggregated object
- [ ] Non-admin → 403

### Frontend Tasks
| # | Task |
|---|------|
| 1 | `adminApi.js` |
| 2 | `StatsCards.jsx` — total users, internships, applications |
| 3 | `UsersTable.jsx` with delete action |
| 4 | `InternshipsTable.jsx` |
| 5 | `Admin/Dashboard.jsx` page |

### Exit Criteria
- Admin sees real-time stats
- Admin can delete misbehaving users
- Non-admin accounts blocked from `/admin/*` routes

---

## Slice 9 — Polish, Test, Document

**Final pass before submission.**

### Backend
- [ ] Feature tests for each controller (`AuthTest`, `InternshipTest`, `ApplicationTest`, `MatchScoreTest`)
- [ ] Run `php artisan test` — all green
- [ ] Add indexes on hot columns (`company_id`, `student_id`, `status`)
- [ ] Check eager loading to eliminate N+1 queries
- [ ] Rate limiting on auth routes
- [ ] CSRF + sanitization review

### Documentation
- [ ] `README.md` with setup steps (install, migrate, seed, serve)
- [ ] `.env.example` committed
- [ ] Postman collection exported to `/docs/postman.json`
- [ ] Optional: Swagger/OpenAPI via `l5-swagger`
- [ ] Optional: `Dockerfile` + `docker-compose.yml`

### Frontend
- [ ] Responsive check: mobile, tablet, desktop
- [ ] Loading/error states on every API call
- [ ] Form validation feedback on every form
- [ ] Accessible labels + keyboard navigation
- [ ] Production build test: `npm run build && npm run preview`

---

## Rubric Alignment Cheat Sheet

| Rubric Area | Weight | Where it's earned |
|---|---|---|
| Laravel Architecture & Best Practices | 30% | Slices 1-8: Service/Repository pattern, enums, policies, resources, form requests |
| Advanced PHP & OOP | 10% | Enums, interfaces (repository contracts), traits (`ApiResponse`), type hints everywhere |
| API Design & RESTful Standards | 15% | Versioned `/api/v1` prefix, proper HTTP verbs + status codes, Sanctum tokens, Resources |
| Database Design | 10% | Slice 2, 4, 5: normalized schema, FKs, indexes, pivot tables, soft deletes |
| Security | 10% | Slices 1, 3, 5: Sanctum + RoleMiddleware + Policies + Form Requests validation |
| Frontend Integration & UI/UX | 20% | All frontend tasks + Slice 9 polish |
| Documentation & Deployment | 5% | Slice 9 |

---

## Daily Flow (suggested)

1. **Morning:** Pick one slice. Read its backend tasks.
2. **Midday:** Implement backend. Test every endpoint in Postman as you go.
3. **Afternoon:** Switch to frontend. Consume the endpoints.
4. **Evening:** Manual QA in browser. Commit. Update checklist.

**Rule:** never start a new slice until the current one's exit criteria are all checked.

---

## Common Pitfalls

| Pitfall | Fix |
|---|-----|
| Building all models first, then all controllers | Stop. Switch to vertical slices. |
| Writing logic in controllers | Extract to Services. |
| Returning raw `$model->toArray()` | Use API Resources. |
| Hardcoding `'student'`, `'open'` strings | Use enums. |
| No validation / `$request->all()` | Always use Form Requests. |
| Public routes returning sensitive fields (passwords, internal notes) | Filter in Resources. |
| Repeating "does user own this?" in controllers | Move to Policies. |
| N+1 queries in index endpoints | `with(['company', 'skills'])` eager loading. |
| Frontend shows stale data after mutation | Refetch or update context on success. |

---

## Optional Stretch Goals (after submission, not required)

- Email notifications when application status changes
- Real-time updates via Laravel Reverb / Pusher
- Analytics charts in admin (recharts)
- PDF generation for offer letters
- OAuth login (Google)
- i18n (English/Arabic)
- Deploy: Laravel Forge + Vercel, or Docker + VPS

---

> **Start with Phase 0 + Slice 1 today.** Once login works end-to-end, the rest is repeating the same pattern with different models. Momentum is everything.
