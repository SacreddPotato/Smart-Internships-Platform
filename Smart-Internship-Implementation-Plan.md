# Smart Internship Matching Platform - Implementation Plan

> **Approach:** Vertical slices (model -> API -> React view, one feature at a time).
> **Why:** Each slice is a working end-to-end feature. You get visible progress fast, catch integration bugs early, and avoid building "all backend" or "all frontend" in isolation.

---

## Core Principle

**Don't build horizontally** (all migrations -> all models -> all controllers -> all UI).
**Build vertically** (one feature fully working before starting the next).

After every backend slice: **test in Postman before touching React**. If it works in Postman but fails in React, the bug is probably in React. If both fail, the bug is probably in Laravel.

### Where Routes Go In This Project

This project is a Laravel API plus a React single-page app.

| Route type | Put it here | Example |
|---|---|---|
| Laravel JSON API endpoints | `routes/api.php` | `GET /api/v1/internships` |
| React page routes | `resources/js/router/index.jsx` | `/internships`, `/login`, `/dashboard` |
| Laravel web fallback only | `routes/web.php` | returns the React HTML shell |

Do not add normal React page routes to `routes/web.php`. The current `Route::fallback(...)` in `routes/web.php` is there so browser refreshes on React URLs still load the React app.

---

## Phase 0 - Single Project Setup (no features yet)

**Goal:** One Laravel project that contains both the API and the React frontend.

**Team rule:** one repo, one project root, one `.env`, one issue board. Backend and frontend work can still be split by folder, but setup, onboarding, and deployment stay unified.

### Create the App

```bash
composer create-project laravel/laravel smart-internship-platform
cd smart-internship-platform
composer require laravel/sanctum
php artisan install:api
```

- Configure `.env` with DB credentials.
- Run `php artisan migrate` to confirm the DB connection works.
- Keep API routes under `/api/v1` inside `routes/api.php`.

### Add React Inside Laravel

```bash
npm install react react-dom axios react-router-dom
npm install -D @vitejs/plugin-react tailwindcss postcss autoprefixer
npx tailwindcss init -p
```

- Configure `vite.config.js` to load `resources/css/app.css` and `resources/js/main.jsx`.
- Create `resources/js/main.jsx` as the React mount file.
- Create `resources/js/App.jsx` as the main layout/app component.
- Create `resources/js/router/index.jsx` for React Router routes.
- Create `resources/js/api/axios.js` with `baseURL: '/api/v1'`.
- In `vite.config.js`, proxy `/api` to `http://127.0.0.1:8000` during local dev.
- In `routes/web.php`, keep a catch-all/fallback route that returns the React app shell.
- In `resources/js/api/axios.js`, add a request interceptor that reads `token` from `localStorage` and attaches `Authorization: Bearer {token}`.

Example local commands, both run from the same project root:

```bash
php artisan serve
npm run dev
```

### Suggested Folder Boundary

```text
app/                 Laravel domain, services, policies, controllers
routes/api.php       Laravel API routes
routes/web.php       React app fallback route
resources/js/        React app, pages, components, API clients
resources/js/router/ React Router page routes
resources/css/       Tailwind entry CSS
database/            Migrations, factories, seeders
tests/               Laravel feature/unit tests
```

### Exit Criteria

- One project directory contains Laravel + React.
- `php artisan serve` runs on `:8000` from the project root.
- `npm run dev` runs on `:5173` from the same project root.
- Browser can fetch a test endpoint from React through `/api/v1` without CORS errors.

---

## Slice 1 - Authentication (ALWAYS FIRST)

**Why first:** Every other feature depends on knowing who the user is.

### Backend Walkthrough

Work in this exact order. Do not move to React until every Postman check passes.

#### Step 1 - Add roles to users

**Goal:** Every user must have one role: `student`, `company`, or `admin`.

**Why we do this:** The app has different kinds of users. Students apply, companies post internships, and admins manage the platform. The database needs to remember which kind of user each account is.

**What this does:** A migration changes the database structure. After this migration runs, every row in the `users` table will have a `role` value. Later, controllers and middleware will read that value to decide what the user is allowed to do.

1. Run:

```bash
php artisan make:migration add_role_to_users_table --table=users
```

2. Open the generated migration in `database/migrations/`.
3. In `up()`, add a role column:

```php
$table->string('role')->default('student')->after('password');
```

4. In `down()`, remove it:

```php
$table->dropColumn('role');
```

5. Run:

```bash
php artisan migrate
```

6. Check before moving on:

- The command finishes without errors.
- The `users` table now has a `role` column.
- New users will default to `student` if no role is given.

#### Step 2 - Create the `UserRole` enum

**Goal:** Avoid spreading raw strings like `student` and `company` everywhere.

**Why we do this:** Strings are easy to mistype. If you write `compnay` in one file and `company` in another, PHP will not warn you. An enum gives the role values one official home.

**What this does:** `UserRole` becomes a PHP type that represents allowed roles. Instead of guessing role strings across the app, you can use `UserRole::Student`, `UserRole::Company`, and `UserRole::Admin`. It also helps Laravel validate and cast role values cleanly.

1. Create the folder if it does not exist:

```text
app/Enums/
```

2. Create:

```text
app/Enums/UserRole.php
```

3. Add:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Company = 'company';
    case Admin = 'admin';
}
```

4. Check before moving on:

- The namespace is exactly `App\Enums`.
- The enum case values are lowercase strings because those are what you will store in the database.

#### Step 3 - Update the `User` model

**Goal:** Teach Laravel that users can create Sanctum API tokens and that `role` should become a `UserRole` enum.

**Why we do this:** The `User` model is the PHP class Laravel uses when working with rows from the `users` table. If auth depends on users having roles and API tokens, the model needs to know about both.

**What this does:**

- `HasApiTokens` adds Sanctum token methods to the user, especially `createToken(...)`.
- `role` in fillable allows `User::create([... 'role' => ...])` during registration.
- The `role` cast tells Laravel to convert the database string `student` into `UserRole::Student` when you access `$user->role`.
- The `password` cast as `hashed` tells Laravel to automatically hash new passwords when saving them.

1. Open:

```text
app/Models/User.php
```

2. At the top, make sure these imports exist:

```php
use App\Enums\UserRole;
use Laravel\Sanctum\HasApiTokens;
```

3. Inside the `User` class, add the trait:

```php
use HasApiTokens;
```

If the class already has traits like `HasFactory` and `Notifiable`, it is also fine to combine them:

```php
use HasApiTokens, HasFactory, Notifiable;
```

4. Make sure `role` is mass assignable.

If the model uses Laravel attributes, include `role` here:

```php
#[Fillable(['name', 'email', 'password', 'role'])]
```

If the model uses the older property style, include `role` here instead:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
];
```

5. In `casts()`, add:

```php
'role' => UserRole::class,
```

6. Check before moving on:

- `User.php` imports `UserRole`.
- `User.php` imports `HasApiTokens`.
- The class uses the `HasApiTokens` trait.
- `role` is fillable.
- `role` is cast to `UserRole::class`.

Why this matters: later, the controller can call:

```php
$user->createToken('auth-token')->plainTextToken;
```

That method exists because of `HasApiTokens`.

#### Step 4 - Create request validation classes

**Goal:** Keep validation out of the controller.

**Why we do this:** A controller should coordinate the request, not become a giant pile of validation rules. Laravel Form Requests are special request classes that validate input before the controller action runs.

**What `Requests/Auth` means:** `app/Http/Requests` is where Laravel validation request classes live. The `Auth` folder is just organization: it means "these request classes are for authentication." It is not a route and it is not required by Laravel, but it keeps files easier to find.

**What this does:**

- `RegisterRequest` decides what valid registration data looks like.
- `LoginRequest` decides what valid login data looks like.
- If validation fails, Laravel automatically returns a `422` response and the controller never runs.
- If validation passes, the controller can safely call `$request->validated()` and trust the result.

1. Run:

```bash
php artisan make:request Auth/RegisterRequest
php artisan make:request Auth/LoginRequest
```

2. Open:

```text
app/Http/Requests/Auth/RegisterRequest.php
```

3. Make `authorize()` return `true`.
4. In `rules()`, validate:

```php
return [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'max:255', 'unique:users,email'],
    'password' => ['required', 'string', 'min:8', 'confirmed'],
    'role' => ['required', Rule::enum(UserRole::class)],
];
```

You will need these imports:

```php
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
```

5. Open:

```text
app/Http/Requests/Auth/LoginRequest.php
```

6. Make `authorize()` return `true`.
7. In `rules()`, validate:

```php
return [
    'email' => ['required', 'email'],
    'password' => ['required', 'string'],
];
```

8. Check before moving on:

- Bad register data returns `422`.
- `password_confirmation` is required because of the `confirmed` rule.
- Invalid roles are rejected.

#### Step 5 - Create the safe user response

**Goal:** Never accidentally return `password`, `remember_token`, or other private fields.

**Why we do this:** API responses should not expose the raw database model. A model may contain fields the frontend should never see. A Resource lets you control exactly what JSON leaves the backend.

**What a Resource does:** A Laravel Resource is a transformer. It takes a model, like `User`, and turns it into the JSON shape your API wants to return.

Example idea:

```php
new UserResource($user)
```

means:

```text
Take this User model and return only the fields listed in UserResource.
```

**What this does:** `UserResource` becomes the official JSON format for users in the API. Register, login, and `me` can all return users consistently without leaking private columns.

1. Run:

```bash
php artisan make:resource UserResource
```

2. Open:

```text
app/Http/Resources/UserResource.php
```

3. In `toArray()`, return:

```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'email' => $this->email,
    'role' => $this->role,
    'created_at' => $this->created_at,
];
```

4. Check before moving on:

- The resource returns user data only.
- No password fields are included.

#### Step 6 - Create the auth controller

**Goal:** Add the four backend auth actions: register, login, logout, and me.

**Why we do this:** Routes should point somewhere, and controllers are where Laravel usually puts HTTP actions. The auth controller receives validated requests, performs the auth work, and returns JSON responses.

**What this does:**

- `register` creates a new user, creates a token, and returns both.
- `login` checks credentials, creates a token, and returns both.
- `logout` deletes the current token so it cannot be used again.
- `me` returns the currently authenticated user.

**How this connects to Sanctum:** Sanctum token auth works like this:

1. User registers or logs in.
2. Backend creates a token with `$user->createToken(...)`.
3. Frontend stores that token.
4. Frontend sends `Authorization: Bearer TOKEN` on protected API requests.
5. Laravel Sanctum reads the token and figures out which user is making the request.

1. Run:

```bash
php artisan make:controller Api/V1/AuthController
```

2. Open:

```text
app/Http/Controllers/Api/V1/AuthController.php
```

3. Add a `register(RegisterRequest $request)` method:

- Read validated data with `$request->validated()`.
- Create the user with `User::create(...)`.
- Do not manually hash password if your `User` model casts `password` as `hashed`; Laravel will hash it.
- Create a token using `$user->createToken('auth-token')->plainTextToken`.
- Return JSON with `token` and `user`.

4. Add a `login(LoginRequest $request)` method:

- Find the user by email.
- Use `Hash::check($request->password, $user->password)`.
- If invalid, return `422` with an error message.
- If valid, create and return a token plus user.

5. Add a `logout(Request $request)` method:

- Delete the current access token:

```php
$request->user()->currentAccessToken()->delete();
```

- Return a success message.

6. Add a `me(Request $request)` method:

- Return the authenticated user through `UserResource`.

7. Check before moving on:

- Register creates a row in `users`.
- Register returns a plain text token.
- Login with wrong password does not return a token.
- Logout requires a Bearer token.

#### Step 7 - Add auth API routes

**Goal:** Expose the controller through `/api/v1`.

**Why we do this:** A controller method does nothing until a route points to it. API routes define which URL and HTTP method call which controller action.

**What this does:**

- `POST /api/v1/register` calls `AuthController@register`.
- `POST /api/v1/login` calls `AuthController@login`.
- `GET /api/v1/me` calls `AuthController@me`, but only if the request has a valid token.
- `POST /api/v1/logout` calls `AuthController@logout`, but only if the request has a valid token.

**Why this goes in `routes/api.php`:** These endpoints return JSON for React. They are backend API endpoints, not React pages. React page routes like `/login` and `/dashboard` belong in `resources/js/router/index.jsx`.

1. Open:

```text
routes/api.php
```

2. Import the controller:

```php
use App\Http\Controllers\Api\V1\AuthController;
```

3. Inside the existing `Route::prefix('v1')->group(function () { ... });`, add public routes:

```php
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
```

4. Still inside the `/v1` group, add protected routes:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

5. Check before moving on:

- The full URLs are `/api/v1/register`, `/api/v1/login`, `/api/v1/me`, `/api/v1/logout`.
- These routes go in `routes/api.php`, not `routes/web.php`.

#### Step 8 - Create role middleware

**Goal:** Later slices need routes that only `student`, `company`, or `admin` can access.

**Why we do this:** Login only answers "who are you?" Roles answer "are you allowed to do this?" Middleware lets Laravel block a request before the controller runs.

**What middleware does:** Middleware sits between the incoming request and the controller. It can allow the request to continue, or stop it early with an error like `403 Forbidden`.

**What this does:** `RoleMiddleware` checks the authenticated user's role. A route protected with `role:company` will allow company users through and block students/admins unless you allow multiple roles.

Example:

```php
Route::middleware(['auth:sanctum', 'role:company'])->group(function () {
    Route::post('/internships', [InternshipController::class, 'store']);
});
```

means:

```text
First check the user is logged in.
Then check the user has the company role.
Only then run the controller.
```

1. Run:

```bash
php artisan make:middleware RoleMiddleware
```

2. Open:

```text
app/Http/Middleware/RoleMiddleware.php
```

3. Make the middleware accept roles:

```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    $user = $request->user();

    if (! $user || ! in_array($user->role->value, $roles, true)) {
        abort(403);
    }

    return $next($request);
}
```

4. Open:

```text
bootstrap/app.php
```

5. Inside `->withMiddleware(function (Middleware $middleware): void { ... })`, register the alias:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\RoleMiddleware::class,
]);
```

6. Check before moving on:

- You can now protect future routes with `role:student`, `role:company`, or `role:admin`.
- Do not use this middleware alone; use it together with `auth:sanctum`.

Example:

```php
Route::middleware(['auth:sanctum', 'role:company'])->group(function () {
    // company-only routes later
});
```

### Postman Verification

- [ ] `POST /api/v1/register` creates user and returns token.
- [ ] `POST /api/v1/login` returns token.
- [ ] `GET /api/v1/me` with Bearer token returns user.
- [ ] `GET /api/v1/me` without token returns 401.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create `authApi.js` | Export `register(payload)`, `login(payload)`, `logout()`, `me()`; each should call the matching Laravel API route through the shared Axios client. | `resources/js/api/authApi.js` |
| 2 | Create `AuthContext` | Store `user`, `token`, `isAuthenticated`, and loading state; on login/register save token to `localStorage`; on logout clear token and user; on first app load call `me()` if a token exists. | `resources/js/contexts/AuthContext.jsx` |
| 3 | Wrap the app in the auth provider | Import `AuthProvider` in `resources/js/main.jsx` and wrap the router/app so every page can read auth state. | `resources/js/main.jsx` |
| 4 | Create login/register pages | Build forms that call `authApi` through `AuthContext`; show 422 validation errors from Laravel beside fields; redirect after success. | `resources/js/pages/auth/Login.jsx`, `resources/js/pages/auth/Register.jsx` |
| 5 | Create route guards | `ProtectedRoute` redirects guests to `/login`; `RoleRoute` blocks users without the required role and can render a 403 page or redirect. | `resources/js/components/common/ProtectedRoute.jsx`, `resources/js/components/common/RoleRoute.jsx` |
| 6 | Add React routes | In React Router, add `/login`, `/register`, a protected `/dashboard`, and one test role route like `/company/dashboard`; update `main.jsx` to render `<RouterProvider router={router} />`. | `resources/js/router/index.jsx`, `resources/js/main.jsx` |

### Exit Criteria

- Register a student from UI -> see token in `localStorage`.
- Log out -> token cleared and redirected to login.
- Visit protected route without login -> redirected to login.
- Log in as company -> accessing student-only route shows 403 or redirects.

---

## Slice 2 - Browse Internships (Public, Read-Only)

**Why second:** Establishes the listing pattern used everywhere. No auth required, so you can focus on the Model -> Resource -> List flow.

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create database tables | Add migrations for `company_profiles`, `internships`, `skills`, and `internship_skill`; include foreign keys, useful columns, and indexes on `company_id`, `status`, and skill names. | `database/migrations/*_create_company_profiles_table.php`, `*_create_internships_table.php`, `*_create_skills_table.php`, `*_create_internship_skill_table.php` |
| 2 | Create models and relationships | Create `CompanyProfile`, `Internship`, and `Skill`; wire `Internship belongsTo CompanyProfile`; `Internship belongsToMany Skill`; `CompanyProfile belongsTo User`; `CompanyProfile hasMany Internship`. | `app/Models/CompanyProfile.php`, `app/Models/Internship.php`, `app/Models/Skill.php`, `app/Models/User.php` |
| 3 | Create internship enums | Add `InternshipStatus` values like `open`, `closed`, `archived`; add `InternshipType` values like `remote`, `onsite`, `hybrid`; cast them on the `Internship` model. | `app/Enums/InternshipStatus.php`, `app/Enums/InternshipType.php`, `app/Models/Internship.php` |
| 4 | Seed skills | Create a seeder with about 20 skills such as PHP, Laravel, React, SQL, Git; call it from `DatabaseSeeder`. | `database/seeders/SkillSeeder.php`, `database/seeders/DatabaseSeeder.php` |
| 5 | Seed fake internships | Create factories for `CompanyProfile`, `Internship`, and `Skill` attachments; seed at least 15 open internships with related companies and skills. | `database/factories/*.php`, `database/seeders/DatabaseSeeder.php` |
| 6 | Create API resources | `InternshipResource` should include company and skills when loaded; `InternshipCollection` should preserve pagination metadata. | `app/Http/Resources/InternshipResource.php`, `app/Http/Resources/InternshipCollection.php` |
| 7 | Create read controller methods | Add `index` with pagination, search filter, skill filter, and eager loading; add `show` that loads company and skills. | `app/Http/Controllers/Api/V1/InternshipController.php` |
| 8 | Add public API routes | Inside `routes/api.php` under `/api/v1`, add `GET /internships` and `GET /internships/{internship}` pointing to `index` and `show`. | `routes/api.php` |

### Postman Verification

- [ ] `GET /api/v1/internships` returns paginated list.
- [ ] `GET /api/v1/internships/1` returns one with company + skills nested.
- [ ] `GET /api/v1/internships?search=react` filters results.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create internship API client | Export `fetchAll(filters)` and `fetchOne(id)`; pass search, page, and skill filters as query params. | `resources/js/api/internshipApi.js` |
| 2 | Create card component | Display title, company, location/type, short description, skills, and a link to the detail page. | `resources/js/components/internships/InternshipCard.jsx` |
| 3 | Create list and filters | `InternshipList` renders cards; `InternshipFilters` controls search/skill inputs and updates query state. | `resources/js/components/internships/InternshipList.jsx`, `resources/js/components/internships/InternshipFilters.jsx` |
| 4 | Create browse page | Fetch internships with `internshipApi.fetchAll`; show filters, loading, error, list, and pagination; route path is `/internships`. | `resources/js/pages/internships/Browse.jsx`, `resources/js/router/index.jsx` |
| 5 | Create detail page | Read `id` from React Router params; call `fetchOne(id)`; show full internship, company, and required skills; route path is `/internships/:id`. | `resources/js/pages/internships/Detail.jsx`, `resources/js/router/index.jsx` |
| 6 | Add common UI states | Create reusable loading and error components and use them on browse/detail pages. | `resources/js/components/common/LoadingSpinner.jsx`, `resources/js/components/common/ErrorAlert.jsx` |
| 7 | Add pagination | Read Laravel pagination metadata and render previous/next/page buttons that update the page query. | `resources/js/components/common/Pagination.jsx` |

### Exit Criteria

- `/internships` shows cards from real DB data.
- Clicking a card navigates to detail page.
- Filter by search term works.
- Loading and error states render correctly.

---

## Slice 3 - Company Creates Internships

**Why third:** First write operation. Introduces FormRequest + Policy + auth-scoped routes.

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Wire company profile relationship | Add `companyProfile()` to `User`; ensure company users have a `company_profiles` row, either during registration or with a seed/test setup. | `app/Models/User.php`, `app/Models/CompanyProfile.php`, `app/Http/Controllers/Api/V1/AuthController.php` |
| 2 | Create internship form requests | `StoreInternshipRequest` validates title, description, location, type, skills, dates, and requirements; `UpdateInternshipRequest` allows partial updates. | `app/Http/Requests/Internships/StoreInternshipRequest.php`, `app/Http/Requests/Internships/UpdateInternshipRequest.php` |
| 3 | Create internship policy | Add `create`, `update`, `delete`, and `archive` checks; only company users can create; only the owning company can update/delete/archive. | `app/Policies/InternshipPolicy.php` |
| 4 | Register policy | Register `Internship::class => InternshipPolicy::class` in the provider Laravel is using for auth/policies; create `AuthServiceProvider` if the project does not have one yet. | `app/Providers/AuthServiceProvider.php`, `bootstrap/app.php` if provider registration is needed |
| 5 | Create internship service | Move create/update/archive/delete business logic into methods; sync skills inside the service after saving the internship. | `app/Services/InternshipService.php` |
| 6 | Add write controller methods | Add `store`, `update`, `destroy`, `archive`, `companyIndex`, and `archived`; return `InternshipResource` responses. | `app/Http/Controllers/Api/V1/InternshipController.php` |
| 7 | Add company-only API routes | Under `/api/v1`, create an `auth:sanctum` + `role:company` route group for `POST /internships`, `PUT/PATCH /internships/{internship}`, `DELETE /internships/{internship}`, `PATCH /internships/{internship}/archive`, `GET /company/internships`, and `GET /company/internships/archived`. | `routes/api.php` |
| 8 | Add soft deletes | Add `deleted_at` to the `internships` table and use `SoftDeletes` on the model. | `database/migrations/*_add_deleted_at_to_internships_table.php`, `app/Models/Internship.php` |

### Postman Verification

- [ ] As company: `POST /api/v1/internships` creates one.
- [ ] As student: `POST /api/v1/internships` returns 403.
- [ ] Update someone else's internship -> 403.
- [ ] `DELETE /api/v1/internships/{id}` soft-deletes.
- [ ] `PATCH /api/v1/internships/{id}/archive` changes status.
- [ ] `GET /api/v1/company/internships/archived` returns only archived.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Extend internship API client | Add `create`, `update`, `remove`, `archive`, `fetchMine`, and `fetchArchived`; keep public browse methods in the same client. | `resources/js/api/internshipApi.js` |
| 2 | Create internship form | Controlled inputs for title, description, location, type, dates, requirements, and skill multiselect; accept initial values for edit mode. | `resources/js/components/internships/InternshipForm.jsx` |
| 3 | Create company create page | Render `InternshipForm`; call `internshipApi.create`; redirect to company internship list on success. | `resources/js/pages/company/InternshipCreate.jsx`, `resources/js/router/index.jsx` |
| 4 | Create company edit page | Load internship by id; pass initial values to `InternshipForm`; call `internshipApi.update`; route path like `/company/internships/:id/edit`. | `resources/js/pages/company/InternshipEdit.jsx`, `resources/js/router/index.jsx` |
| 5 | Create company internship list | Fetch the logged-in company's internships; link to create/edit; show archive/delete actions. | `resources/js/pages/company/Internships.jsx` |
| 6 | Create archived page | Fetch archived internships and display them separately from active company internships. | `resources/js/pages/company/ArchivedInternships.jsx` |
| 7 | Add protected company routes | Wrap `/company/internships`, `/company/internships/create`, `/company/internships/:id/edit`, and `/company/internships/archived` in `ProtectedRoute` + `RoleRoute role="company"`. | `resources/js/router/index.jsx` |
| 8 | Map validation errors | When Laravel returns 422, show each field error beside the matching form control. | `resources/js/components/internships/InternshipForm.jsx` |

### Exit Criteria

- Company creates internship from UI -> appears in public Browse.
- Company edits -> change reflected.
- Company archives -> no longer in Browse, shows in Archived tab.
- Validation errors from backend display next to the correct fields.

---

## Slice 4 - Student Profile + CV Upload

**Why now:** Applications need a student profile to exist.

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create student profile tables | Add `student_profiles` with `user_id`, bio, university, major, GPA, graduation year, and `cv_path`; add `student_skill` pivot linking profiles to skills. | `database/migrations/*_create_student_profiles_table.php`, `*_create_student_skill_table.php` |
| 2 | Create model relationships | Create `StudentProfile`; add `studentProfile()` to `User`; add `skills()` belongsToMany on `StudentProfile`. | `app/Models/StudentProfile.php`, `app/Models/User.php`, `app/Models/Skill.php` |
| 3 | Auto-create student profile | In registration flow, if role is `student`, create an empty `StudentProfile` for the new user. If using an `AuthService`, put the logic there; otherwise keep it in `AuthController` until you extract the service. | `app/Http/Controllers/Api/V1/AuthController.php`, optional `app/Services/AuthService.php` |
| 4 | Create profile requests | `UpdateProfileRequest` validates profile fields; `UploadCvRequest` validates required PDF file, MIME type, and max size. | `app/Http/Requests/Student/UpdateProfileRequest.php`, `app/Http/Requests/Student/UploadCvRequest.php` |
| 5 | Create profile service | Add methods to update profile data, sync skills, and store CV files under a predictable disk/path. | `app/Services/StudentProfileService.php` |
| 6 | Create profile controller | Add `show`, `update`, and `uploadCv`; each should use the authenticated student's profile and return a resource. | `app/Http/Controllers/Api/V1/StudentProfileController.php` |
| 7 | Create skill sync controller | Add `index` to list all skills and `sync` to update the current student's selected skills. | `app/Http/Controllers/Api/V1/StudentSkillController.php` |
| 8 | Add student-only API routes | Under `/api/v1`, inside `auth:sanctum` + `role:student`, add `GET/PUT /student/profile`, `POST /student/profile/cv`, `GET /skills`, and `PUT /student/skills`. | `routes/api.php` |
| 9 | Configure public CV access | Run `php artisan storage:link` if CVs should be downloadable through public storage; otherwise create a protected download route later. | `public/storage`, `config/filesystems.php` |

### Postman Verification

- [ ] `GET /api/v1/student/profile` returns profile.
- [ ] `PUT /api/v1/student/profile` updates fields.
- [ ] `POST /api/v1/student/profile/cv` with multipart file stores in `storage/app/...`.
- [ ] File size/type validation rejects oversize or non-PDF.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create student API client | Export `fetchProfile`, `updateProfile`, `uploadCv`, `fetchSkills`, and `syncSkills`. | `resources/js/api/studentApi.js` |
| 2 | Create profile form | Controlled fields for university, major, GPA, graduation year, bio, and any contact/profile fields. | `resources/js/components/student/ProfileForm.jsx` |
| 3 | Create skill selector | Fetch skills and allow selecting/removing many skills; submit selected skill IDs to the sync endpoint. | `resources/js/components/common/SkillSelector.jsx` |
| 4 | Create file upload component | Accept PDF file; show upload progress if using Axios `onUploadProgress`; display success/error messages. | `resources/js/components/common/FileUpload.jsx` |
| 5 | Create profile page | Fetch profile and skills, render form/selector/upload components, and refresh data after successful saves. | `resources/js/pages/student/Profile.jsx` |
| 6 | Add student profile route | Add `/student/profile` as a protected student-only React route. | `resources/js/router/index.jsx` |

### Exit Criteria

- Student updates profile -> persists after refresh.
- CV uploads successfully and link works.
- Skills sync correctly (add/remove).

---

## Slice 5 - Student Applies to Internship

**Why now:** All prerequisites exist (profile, internships, skills).

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create applications table | Add `student_profile_id`, `internship_id`, `status`, `match_score`, timestamps; add unique index on `(student_profile_id, internship_id)`. | `database/migrations/*_create_applications_table.php` |
| 2 | Create model and enum | Create `Application`; add relationships to `StudentProfile` and `Internship`; create `ApplicationStatus` enum with values like `pending`, `reviewed`, `accepted`, `rejected`; cast status. | `app/Models/Application.php`, `app/Enums/ApplicationStatus.php` |
| 3 | Create apply request | Validate optional cover letter/message and ensure the internship exists and is open. | `app/Http/Requests/Applications/StoreApplicationRequest.php` |
| 4 | Create application policy | Student can view/apply to own applications; company can view applications for internships owned by its company profile. | `app/Policies/ApplicationPolicy.php`, `app/Providers/AuthServiceProvider.php` |
| 5 | Create application service | `apply()` should prevent duplicates, create the application, calculate/store match score, and return the created model. | `app/Services/ApplicationService.php` |
| 6 | Create application resource | Return id, internship summary, student summary when allowed, status, match score, and timestamps. | `app/Http/Resources/ApplicationResource.php` |
| 7 | Create controller methods | Add `store`, `studentIndex`, `companyIndex`, and `show`; use policies and eager loading. | `app/Http/Controllers/Api/V1/ApplicationController.php` |
| 8 | Add role-scoped API routes | Add student routes: `POST /internships/{internship}/applications`, `GET /student/applications`; add shared/protected `GET /applications/{application}`; add company route `GET /company/applications`. | `routes/api.php` |

### Postman Verification

- [ ] Student applies -> 201 with `match_score`.
- [ ] Student applies to same internship twice -> 422.
- [ ] `GET /api/v1/student/applications` returns only own.
- [ ] `GET /api/v1/company/applications` returns only to company's internships.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create application API client | Export `apply(internshipId, payload)`, `fetchMine()`, `fetchForCompany()`, and later `updateStatus()`. | `resources/js/api/applicationApi.js` |
| 2 | Add apply UI to detail page | Add an `ApplyModal` or inline form on internship detail; submit to `applicationApi.apply`; show success and duplicate errors. | `resources/js/components/applications/ApplyModal.jsx`, `resources/js/pages/internships/Detail.jsx` |
| 3 | Create application card | Show internship title, company, status, match score, and applied date. | `resources/js/components/applications/ApplicationCard.jsx` |
| 4 | Create student applications page | Fetch the student's applications and render `ApplicationCard` list. | `resources/js/pages/student/Applications.jsx` |
| 5 | Add student applications route | Add `/student/applications` as a protected student-only React route. | `resources/js/router/index.jsx` |
| 6 | Disable duplicate apply | If the detail API includes application state, disable the Apply button; otherwise handle 422 from backend and show "already applied". | `resources/js/pages/internships/Detail.jsx` |

### Exit Criteria

- Student applies from detail page -> confirmation + redirect.
- Reapplying shows "already applied".
- Applications list shows status + match score.

---

## Slice 6 - Match Score Logic

**Why now:** Applications exist to display the score against. Also a small standalone feature that is easy to test in isolation.

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create match score service | Add a pure `calculate(StudentProfile $studentProfile, Internship $internship): int` method; eager load skills before calculation. | `app/Services/MatchScoreService.php` |
| 2 | Implement skill overlap | Compare student skill IDs to internship skill IDs; base score is `matched / required * 100`; handle internships with zero required skills safely. | `app/Services/MatchScoreService.php` |
| 3 | Add bonus rules | Add +5 if GPA > 3.5 and +5 if graduation year matches the internship preference if that field exists; clamp final score to 100. | `app/Services/MatchScoreService.php` |
| 4 | Create match controller | Add `score` for one internship and `recommendations` for top matched open internships for the current student. | `app/Http/Controllers/Api/V1/MatchController.php` |
| 5 | Add match API routes | Under `/api/v1`, inside `auth:sanctum` + `role:student`, add `GET /internships/{internship}/match-score` and `GET /student/recommendations`. | `routes/api.php` |
| 6 | Add unit tests | Create tests with known skills/GPA/year inputs and assert exact expected scores. | `tests/Unit/MatchScoreServiceTest.php` |

### Postman Verification

- [ ] `GET /api/v1/internships/{id}/match-score` returns number 0-100.
- [ ] `GET /api/v1/student/recommendations` returns top-matched internships.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create match API client | Export `fetchScore(internshipId)` and `fetchRecommendations()`. | `resources/js/api/matchApi.js` |
| 2 | Create score badge | Display score and color tier, for example green >= 80, amber >= 50, red below 50. | `resources/js/components/match/MatchScoreBadge.jsx` |
| 3 | Show badge on internship cards | If logged-in user role is `student`, request/display match score or use score from recommendations payload. | `resources/js/components/internships/InternshipCard.jsx` |
| 4 | Create recommendations page | Fetch recommendations, sort/display by score descending, and link to internship detail pages. | `resources/js/pages/student/Recommendations.jsx` |
| 5 | Add recommendations route | Add `/student/recommendations` as a protected student-only React route. | `resources/js/router/index.jsx` |

### Exit Criteria

- Students see match score on every internship where appropriate.
- Recommendations page sorted by score descending.
- Unit tests for match logic pass.

---

## Slice 7 - Company Manages Applicants

**Why now:** Companies need to see who applied.

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create status update request | Validate status is one of the `ApplicationStatus` enum values; optionally restrict invalid transitions. | `app/Http/Requests/Applications/UpdateStatusRequest.php` |
| 2 | Add status update method | Add `updateStatus` to load application with internship/company, authorize, change status, save, and return `ApplicationResource`. | `app/Http/Controllers/Api/V1/ApplicationController.php` |
| 3 | Enforce ownership policy | Add/update policy method so only the company that owns the internship can update its application status. | `app/Policies/ApplicationPolicy.php` |
| 4 | Add company status route | Under `/api/v1`, inside `auth:sanctum` + `role:company`, add `PATCH /company/applications/{application}/status`. | `routes/api.php` |

### Postman Verification

- [ ] Company sets status to accepted/rejected/reviewed.
- [ ] Other company cannot change it -> 403.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Extend application API client | Add `fetchForCompany()` and `updateStatus(applicationId, status)`. | `resources/js/api/applicationApi.js` |
| 2 | Create applicants page | Fetch company applications and render applicant rows grouped by internship or as a flat table. | `resources/js/pages/company/Applicants.jsx` |
| 3 | Create applicant row | Show student name/profile summary, internship title, match score, current status, and status dropdown. | `resources/js/components/applications/ApplicantRow.jsx` |
| 4 | Add CV download link | Use the CV URL from the API resource if available; hide or disable the link when no CV exists. | `resources/js/components/applications/ApplicantRow.jsx`, `app/Http/Resources/ApplicationResource.php` |
| 5 | Add company applicants route | Add `/company/applicants` as a protected company-only React route. | `resources/js/router/index.jsx` |

### Exit Criteria

- Company reviews applications and changes status.
- Student sees updated status on their side.

---

## Slice 8 - Admin Dashboard

**Why last:** Aggregates from all the data now in the system.

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Seed an admin user | Create a known admin user in `DatabaseSeeder` using `UserRole::Admin`; do not hardcode production passwords outside local seed data. | `database/seeders/DatabaseSeeder.php` |
| 2 | Create dashboard service | Calculate total users, companies, students, internships, active internships, applications, applications per day, and top companies. | `app/Services/AdminDashboardService.php` |
| 3 | Create dashboard controller | Add `index` that calls the service and returns an admin dashboard resource/array. | `app/Http/Controllers/Api/V1/AdminDashboardController.php` |
| 4 | Create admin user controller | Add `index` for user list and `destroy` for deleting/deactivating users; protect against deleting yourself if desired. | `app/Http/Controllers/Api/V1/AdminUserController.php` |
| 5 | Create admin resources | Shape dashboard stats and user rows so the frontend receives only needed fields. | `app/Http/Resources/AdminDashboardResource.php`, `app/Http/Resources/AdminUserResource.php` |
| 6 | Add admin API routes | Under `/api/v1`, inside `auth:sanctum` + `role:admin`, add `GET /admin/dashboard`, `GET /admin/users`, and `DELETE /admin/users/{user}`. | `routes/api.php` |

### Postman Verification

- [ ] `GET /api/v1/admin/dashboard` returns aggregated object.
- [ ] Non-admin -> 403.

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Create admin API client | Export `fetchDashboard`, `fetchUsers`, and `deleteUser`. | `resources/js/api/adminApi.js` |
| 2 | Create stats cards | Render total users, internships, applications, and active internships from dashboard payload. | `resources/js/components/admin/StatsCards.jsx` |
| 3 | Create users table | Fetch users, show role/email/name, and add delete action with confirmation. | `resources/js/components/admin/UsersTable.jsx` |
| 4 | Create internships table | Show active/recent internships and useful status/company info from dashboard or a dedicated endpoint. | `resources/js/components/admin/InternshipsTable.jsx` |
| 5 | Create dashboard page | Fetch dashboard data, render stats and tables, and handle loading/error states. | `resources/js/pages/admin/Dashboard.jsx` |
| 6 | Add admin route | Add `/admin/dashboard` as a protected admin-only React route. | `resources/js/router/index.jsx` |

### Exit Criteria

- Admin sees real-time stats.
- Admin can delete or disable misbehaving users.
- Non-admin accounts blocked from `/admin/*` routes.

---

## Slice 9 - Polish, Test, Document

**Final pass before submission.**

### Backend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Add feature tests | Test successful and forbidden flows for auth, internships, applications, match score, and admin endpoints. | `tests/Feature/AuthTest.php`, `InternshipTest.php`, `ApplicationTest.php`, `AdminTest.php` |
| 2 | Run full test suite | Run `php artisan test`; fix failures before final submission. | terminal |
| 3 | Add database indexes | Add indexes on hot lookup columns like `company_profile_id`, `student_profile_id`, `internship_id`, `status`, and `created_at`. | new migration in `database/migrations/` |
| 4 | Check eager loading | Review controller index/show methods and add `with(...)` to avoid N+1 queries. | `app/Http/Controllers/Api/V1/*.php` |
| 5 | Add rate limiting | Apply a stricter limiter to login/register routes if needed. | `routes/api.php`, `bootstrap/app.php` |
| 6 | Review security | Confirm every write route uses auth, role middleware, policies, and Form Requests; confirm resources hide sensitive fields. | `routes/api.php`, `app/Policies/`, `app/Http/Resources/` |

### Frontend Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Responsive QA | Test pages at mobile, tablet, and desktop widths; fix layout overflow and unreadable controls. | `resources/js/pages/`, `resources/js/components/` |
| 2 | Loading/error audit | Ensure every API call has loading, empty, and error states. | `resources/js/pages/`, `resources/js/components/common/` |
| 3 | Form validation audit | Ensure every form displays Laravel 422 validation errors beside the correct fields. | `resources/js/components/**/*Form.jsx` |
| 4 | Accessibility pass | Add labels, sensible button text, keyboard-friendly controls, and visible focus states. | `resources/js/components/`, `resources/js/pages/` |
| 5 | Production build test | Run `npm run build`; fix Vite/React build errors. | terminal, `vite.config.js` if needed |

### Documentation Tasks

| # | Task | Do this specifically | File(s) |
|---|------|----------------------|---------|
| 1 | Update README | Add install, `.env`, migrate, seed, serve, Vite, test, and default admin credentials for local dev. | `README.md` |
| 2 | Update environment example | Make sure required DB, app URL, and Sanctum-related variables are documented. | `.env.example` |
| 3 | Export Postman collection | Save tested endpoints and example tokens/payloads without real secrets. | `docs/postman.json` |
| 4 | Optional API docs | If time allows, add Swagger/OpenAPI documentation. | optional `config/l5-swagger.php`, annotations/docs |
| 5 | Optional deployment files | If deployment is required, add Docker or VPS notes. | optional `Dockerfile`, `docker-compose.yml`, `README.md` |

---

## Rubric Alignment Cheat Sheet

| Rubric Area | Weight | Where it's earned |
|---|---|---|
| Laravel Architecture & Best Practices | 30% | Slices 1-8: services, enums, policies, resources, form requests |
| Advanced PHP & OOP | 10% | Enums, services, typed methods, resources, policies |
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
| Adding React page routes to `routes/web.php` | Add page routes to `resources/js/router/index.jsx`; keep `routes/web.php` as the React fallback. |
| Building all models first, then all controllers | Stop. Switch to vertical slices. |
| Writing logic in controllers | Extract to services once the controller starts doing more than request/response coordination. |
| Returning raw `$model->toArray()` | Use API Resources. |
| Hardcoding `student`, `open`, etc. everywhere | Use enums and constants. |
| No validation / `$request->all()` | Always use Form Requests for create/update actions. |
| Public routes returning sensitive fields | Filter output in Resources. |
| Repeating "does user own this?" in controllers | Move ownership checks to Policies. |
| N+1 queries in index endpoints | Use `with([...])` eager loading. |
| Frontend shows stale data after mutation | Refetch or update context/state on success. |

---

## Optional Stretch Goals (after submission, not required)

- Email notifications when application status changes.
- Real-time updates via Laravel Reverb / Pusher.
- Analytics charts in admin with Recharts.
- PDF generation for offer letters.
- OAuth login with Google.
- i18n in English/Arabic.
- Deploy with Laravel Forge/VPS and built Vite assets, or Docker + VPS.

---

> **Start with Phase 0 + Slice 1 today.** Once login works end-to-end, the rest is repeating the same pattern with different models. Momentum is everything.
