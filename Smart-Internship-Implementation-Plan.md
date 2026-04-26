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

### Frontend Walkthrough

#### Step 1 - Create the auth API client

**Goal:** Put all auth HTTP calls in one file.

**Why we do this:** React pages should not manually remember endpoint URLs everywhere. If `/login` changes later, you update one API file instead of hunting through components.

**What this does:** `authApi.js` becomes a small wrapper around Axios for `register`, `login`, `logout`, and `me`.

**Do this:** Create `resources/js/api/authApi.js`; import the shared Axios client; export functions that call `POST /register`, `POST /login`, `POST /logout`, and `GET /me`.

**Check:** No React component should call `/api/v1/login` directly; components should call `authApi.login(...)`.

#### Step 2 - Create `AuthContext`

**Goal:** Give the whole React app access to the logged-in user.

**Why we do this:** Many components need to know whether the user is logged in. Passing `user` through props everywhere gets messy fast. Context gives shared auth state to the app.

**What this does:** `AuthContext` stores `user`, `token`, `isAuthenticated`, loading state, and functions like `login`, `register`, and `logout`.

**Do this:** Create `resources/js/contexts/AuthContext.jsx`; on successful login/register, save the token to `localStorage`; on logout, remove it; on first load, call `authApi.me()` if a token already exists.

**Minimum code shape:**

```jsx
import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import * as authApi from '../api/authApi';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(() => localStorage.getItem('token'));
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!token) {
            setLoading(false);
            return;
        }

        authApi.me()
            .then((response) => {
                setUser(response.data.data ?? response.data.user ?? response.data);
            })
            .catch(() => {
                localStorage.removeItem('token');
                setToken(null);
                setUser(null);
            })
            .finally(() => {
                setLoading(false);
            });
    }, [token]);

    async function register(payload) {
        setError(null);
        const response = await authApi.register(payload);
        const nextToken = response.data.token;

        localStorage.setItem('token', nextToken);
        setToken(nextToken);
        setUser(response.data.user);

        return response;
    }

    async function login(payload) {
        setError(null);
        const response = await authApi.login(payload);
        const nextToken = response.data.token;

        localStorage.setItem('token', nextToken);
        setToken(nextToken);
        setUser(response.data.user);

        return response;
    }

    async function logout() {
        try {
            await authApi.logout();
        } finally {
            localStorage.removeItem('token');
            setToken(null);
            setUser(null);
        }
    }

    const value = useMemo(() => ({
        user,
        token,
        loading,
        error,
        isAuthenticated: Boolean(user && token),
        register,
        login,
        logout,
    }), [user, token, loading, error]);

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth must be used inside AuthProvider');
    }

    return context;
}
```

**What to export:** Export `AuthProvider` and `useAuth`. You usually do not need to export `AuthContext` itself.

**What components will use:** Pages call `const { user, login, logout, isAuthenticated, loading } = useAuth();`.

**Check:** Refreshing the browser should keep the user logged in if the token is still valid.

#### Step 3 - Wrap the app in the auth provider

**Goal:** Make auth state available to every page.

**Why we do this:** Creating context is not enough. React components can only read it if a provider wraps them.

**What this does:** `main.jsx` becomes the root place where the auth provider surrounds the router/app.

**Do this:** In `resources/js/main.jsx`, import `AuthProvider` and wrap the rendered app or router with it.

**Check:** A page can call your auth hook/context and read `user` without errors.

#### Step 4 - Create login and register pages

**Goal:** Let real users create accounts and sign in from the UI.

**Why we do this:** The backend works through Postman first, but the product needs browser forms that call those backend endpoints.

**What this does:** `Login.jsx` and `Register.jsx` collect form data, call the auth context functions, show validation errors, and redirect after success.

**Do this:** Create `resources/js/pages/auth/Login.jsx` and `resources/js/pages/auth/Register.jsx`; include fields matching your backend requests, including `password_confirmation` on register.

**Check:** A bad form submission shows Laravel `422` errors; a good submission stores a token and redirects.

#### Step 5 - Create route guards

**Goal:** Stop guests and wrong-role users from seeing protected pages.

**Why we do this:** The backend still enforces security, but the frontend should also guide users away from pages they cannot use.

**What this does:** `ProtectedRoute` checks login state; `RoleRoute` checks the user's role.

**Do this:** Create `resources/js/components/common/ProtectedRoute.jsx` and `resources/js/components/common/RoleRoute.jsx`.

**Check:** Visiting `/dashboard` while logged out redirects to `/login`; visiting a company-only page as a student shows a 403 page or redirects.

#### Step 6 - Add React routes

**Goal:** Connect URLs like `/login` and `/dashboard` to React pages.

**Why we do this:** Laravel's `routes/web.php` only loads the React app shell. React Router decides which page component appears after the app loads.

**What this does:** `resources/js/router/index.jsx` becomes the frontend route map.

**Do this:** Create `resources/js/router/index.jsx`; add `/login`, `/register`, `/dashboard`, and one test role route like `/company/dashboard`; update `main.jsx` to render the router.

**Check:** Browser navigation works, and refreshing a React route still loads because `routes/web.php` has the fallback.

### Exit Criteria

- Register a student from UI -> see token in `localStorage`.
- Log out -> token cleared and redirected to login.
- Visit protected route without login -> redirected to login.
- Log in as company -> accessing student-only route shows 403 or redirects.

---

## Slice 2 - Browse Internships (Public, Read-Only)

**Why second:** Establishes the listing pattern used everywhere. No auth required, so you can focus on the Model -> Resource -> List flow.

### Backend Walkthrough

#### Step 1 - Create internship-related tables

**Goal:** Store companies, internships, skills, and the many-to-many link between internships and skills.

**Why we do this:** Browse pages need real database rows. Internships also need related company and skill data, so the database structure comes first.

**What this does:** Migrations create `company_profiles`, `internships`, `skills`, and `internship_skill`.

**Do this:** Create migrations for each table. Add foreign keys, timestamps, and indexes on commonly filtered fields like company, status, and skill name.

**Check:** `php artisan migrate` succeeds and the tables exist.

#### Step 2 - Create models and relationships

**Goal:** Teach Laravel how these tables connect.

**Why we do this:** Laravel models let you write `$internship->skills` instead of manually joining tables every time.

**What this does:** Relationships become reusable object links: company has internships, internship belongs to company, internship belongs to many skills.

**Do this:** Create `CompanyProfile`, `Internship`, and `Skill`; add relationships in those models and add `companyProfile()` to `User`.

**Check:** In Tinker, you can create an internship and access its company and skills through relationships.

#### Step 3 - Create internship enums

**Goal:** Make status and type values official.

**Why we do this:** Values like `open`, `archived`, `remote`, and `hybrid` are business rules. Enums keep them consistent.

**What this does:** `InternshipStatus` and `InternshipType` become typed values that Laravel can cast from database strings.

**Do this:** Create `app/Enums/InternshipStatus.php` and `app/Enums/InternshipType.php`; cast them in the `Internship` model.

**Check:** `$internship->status` returns an enum instead of a plain string.

#### Step 4 - Seed skills

**Goal:** Give the app a starting list of reusable skills.

**Why we do this:** Browse filters, internship requirements, and match scoring all need skills. You should not manually type the same skills for every test.

**What this does:** `SkillSeeder` inserts known skill rows such as PHP, Laravel, React, SQL, and Git.

**Do this:** Create `database/seeders/SkillSeeder.php`; call it from `DatabaseSeeder`.

**Check:** `php artisan db:seed --class=SkillSeeder` creates skill records.

#### Step 5 - Seed fake internships

**Goal:** Make the browse page useful before companies can create internships.

**Why we do this:** A list page is hard to build against an empty database. Seeded records let you test pagination, search, and detail pages immediately.

**What this does:** Factories create fake companies, internships, and skill attachments.

**Do this:** Create factories for `CompanyProfile` and `Internship`; seed at least 15 open internships with related skills.

**Check:** `php artisan migrate:fresh --seed` gives you visible internships in the database.

#### Step 6 - Create internship resources

**Goal:** Control the JSON shape returned to React.

**Why we do this:** The frontend needs clean, predictable JSON, not raw database objects.

**What this does:** `InternshipResource` transforms one internship; `InternshipCollection` transforms paginated lists and keeps pagination metadata.

**Do this:** Create `app/Http/Resources/InternshipResource.php` and `InternshipCollection.php`; include company and skills when loaded.

**Check:** The API response includes internship fields plus nested company and skills, without unrelated columns.

#### Step 7 - Create read controller methods

**Goal:** Provide list and detail endpoints.

**Why we do this:** React needs one endpoint for browsing many internships and one endpoint for viewing a single internship.

**What this does:** `index` returns paginated/filterable internships; `show` returns one internship with relationships.

**Do this:** Create `InternshipController`; add `index` with search/filter/pagination and `show` with eager loading.

**Check:** Postman can fetch a list and one internship by id.

#### Step 8 - Add public API routes

**Goal:** Make the browse endpoints reachable.

**Why we do this:** Controller methods do nothing until API routes point to them.

**What this does:** Adds public JSON endpoints under `/api/v1`.

**Do this:** In `routes/api.php`, add `GET /internships` and `GET /internships/{internship}` inside the `/v1` group.

**Check:** `GET /api/v1/internships` works without a token.

### Postman Verification

- [ ] `GET /api/v1/internships` returns paginated list.
- [ ] `GET /api/v1/internships/1` returns one with company + skills nested.
- [ ] `GET /api/v1/internships?search=react` filters results.

### Frontend Walkthrough

#### Step 1 - Create the internship API client

**Goal:** Centralize all internship HTTP calls.

**Why we do this:** Pages should ask for internships through one small API layer instead of duplicating Axios logic.

**What this does:** `internshipApi.js` exposes `fetchAll(filters)` and `fetchOne(id)`.

**Do this:** Create `resources/js/api/internshipApi.js`; pass `search`, `page`, and skill filters as query params.

**Check:** Calling `fetchAll({ search: 'react' })` hits `/api/v1/internships?search=react`.

#### Step 2 - Create `InternshipCard`

**Goal:** Display one internship summary consistently.

**Why we do this:** Lists, recommendations, and dashboards can reuse the same card instead of rebuilding the same markup.

**What this does:** The card shows title, company, type/location, short description, skills, and a link to detail.

**Do this:** Create `resources/js/components/internships/InternshipCard.jsx`.

**Check:** The component works with one internship object from the API response.

#### Step 3 - Create list and filter components

**Goal:** Separate list rendering from filter controls.

**Why we do this:** Keeping filtering UI separate makes the Browse page easier to read and test.

**What this does:** `InternshipList` renders cards; `InternshipFilters` manages search/skill inputs.

**Do this:** Create `InternshipList.jsx` and `InternshipFilters.jsx`.

**Check:** Typing a search term updates the browse query state.

#### Step 4 - Create the browse page

**Goal:** Build the public `/internships` page.

**Why we do this:** This is the first real React page powered by Laravel data.

**What this does:** `Browse.jsx` fetches internships, renders filters, shows loading/error states, and displays the list.

**Do this:** Create `resources/js/pages/internships/Browse.jsx`; add `/internships` to `resources/js/router/index.jsx`.

**Check:** Visiting `/internships` shows seeded internships.

#### Step 5 - Create the detail page

**Goal:** Show one internship in full.

**Why we do this:** Users need a detail page before they can later apply.

**What this does:** `Detail.jsx` reads `id` from the URL and calls `fetchOne(id)`.

**Do this:** Create `resources/js/pages/internships/Detail.jsx`; add `/internships/:id` to the React router.

**Check:** Clicking a card opens the correct internship detail page.

#### Step 6 - Add common loading and error components

**Goal:** Handle waiting and failure states consistently.

**Why we do this:** Every API page needs loading and error UI. Reusing components keeps the app coherent.

**What this does:** `LoadingSpinner` and `ErrorAlert` become shared UI pieces.

**Do this:** Create `resources/js/components/common/LoadingSpinner.jsx` and `ErrorAlert.jsx`; use them in Browse and Detail.

**Check:** Temporarily break the API URL and confirm the error state appears.

#### Step 7 - Add pagination

**Goal:** Let users move through paginated Laravel results.

**Why we do this:** The backend returns chunks of data, not every internship at once. The frontend must understand page metadata.

**What this does:** `Pagination.jsx` reads metadata and triggers page changes.

**Do this:** Create `resources/js/components/common/Pagination.jsx`; update query/page state when users click page controls.

**Check:** Page 2 fetches a different API page.

### Exit Criteria

- `/internships` shows cards from real DB data.
- Clicking a card navigates to detail page.
- Filter by search term works.
- Loading and error states render correctly.

---

## Slice 3 - Company Creates Internships

**Why third:** First write operation. Introduces FormRequest + Policy + auth-scoped routes.

### Backend Walkthrough

#### Step 1 - Wire company profiles to users

**Goal:** Connect company accounts to their company profile.

**Why we do this:** A user is the login identity; a company profile is the business information that owns internships.

**What this does:** `User` can access `$user->companyProfile`, and `CompanyProfile` can access its user and internships.

**Do this:** Add `companyProfile()` to `User`; ensure company registration or seeding creates a `company_profiles` row.

**Check:** A company user has exactly one company profile before trying to create internships.

#### Step 2 - Create internship form requests

**Goal:** Validate create and update payloads before controller logic runs.

**Why we do this:** Company-submitted data needs rules: required title, valid type, valid skills, dates, and safe text lengths.

**What this does:** `StoreInternshipRequest` protects create; `UpdateInternshipRequest` protects edit.

**Do this:** Create both request classes under `app/Http/Requests/Internships/`; make `authorize()` return `true` and put ownership/security in policies.

**Check:** Missing title or invalid skill IDs return `422`.

#### Step 3 - Create `InternshipPolicy`

**Goal:** Keep ownership rules out of the controller.

**Why we do this:** Controllers should not repeat "does this company own this internship?" everywhere.

**What this does:** The policy answers whether a user can create, update, delete, or archive an internship.

**Do this:** Create `app/Policies/InternshipPolicy.php`; allow create for company users; allow update/delete/archive only for the owning company.

**Check:** A second company cannot edit the first company's internship.

#### Step 4 - Register the policy

**Goal:** Make Laravel know which policy belongs to `Internship`.

**Why we do this:** A policy file is not used automatically unless Laravel can discover or is told about it.

**What this does:** Calls like `$this->authorize('update', $internship)` use `InternshipPolicy`.

**Do this:** Register `Internship::class => InternshipPolicy::class` in the auth provider your Laravel version uses.

**Check:** Calling `authorize()` uses your policy instead of silently doing nothing.

#### Step 5 - Create `InternshipService`

**Goal:** Move business steps out of controller methods.

**Why we do this:** Creating an internship is more than saving one row: you also sync skills and choose default status.

**What this does:** The service owns create, update, archive, and delete behavior.

**Do this:** Create `app/Services/InternshipService.php`; add methods like `create(User $user, array $data)`, `update(...)`, `archive(...)`, and `delete(...)`.

**Check:** Controller methods become short: validate, authorize, call service, return resource.

#### Step 6 - Add write controller methods

**Goal:** Add API actions for company internship management.

**Why we do this:** Slice 2 only reads internships. Companies now need create/edit/archive/delete endpoints.

**What this does:** `store`, `update`, `destroy`, `archive`, `companyIndex`, and `archived` become available controller actions.

**Do this:** Add these methods to `InternshipController`; use Form Requests, policy authorization, `InternshipService`, and `InternshipResource`.

**Check:** Each method returns JSON and the correct HTTP status.

#### Step 7 - Add company-only API routes

**Goal:** Expose company internship endpoints safely.

**Why we do this:** These endpoints change data, so only logged-in company users should reach them.

**What this does:** Routes under `/api/v1` use both `auth:sanctum` and `role:company`.

**Do this:** In `routes/api.php`, add company routes for create, update, delete, archive, company list, and archived list.

**Check:** A student token gets `403`; no token gets `401`.

#### Step 8 - Add soft deletes

**Goal:** Let companies delete internships without permanently losing records.

**Why we do this:** Admins, reporting, and audit history often need old records. Soft delete marks a row deleted using `deleted_at`.

**What this does:** Deleted internships are hidden by default but still exist in the database.

**Do this:** Add a migration with `$table->softDeletes()` and use the `SoftDeletes` trait on `Internship`.

**Check:** Delete sets `deleted_at`; the row remains in the table.

### Postman Verification

- [ ] As company: `POST /api/v1/internships` creates one.
- [ ] As student: `POST /api/v1/internships` returns 403.
- [ ] Update someone else's internship -> 403.
- [ ] `DELETE /api/v1/internships/{id}` soft-deletes.
- [ ] `PATCH /api/v1/internships/{id}/archive` changes status.
- [ ] `GET /api/v1/company/internships/archived` returns only archived.

### Frontend Walkthrough

#### Step 1 - Extend the internship API client

**Goal:** Add write operations to the same API file used for browsing.

**Why we do this:** The frontend should have one internship API module for both public and company actions.

**What this does:** Adds `create`, `update`, `remove`, `archive`, `fetchMine`, and `fetchArchived`.

**Do this:** Update `resources/js/api/internshipApi.js`.

**Check:** Each function maps to one Laravel route from this slice.

#### Step 2 - Create `InternshipForm`

**Goal:** Reuse one form for create and edit.

**Why we do this:** Create and edit fields are nearly the same. One form prevents duplicated validation/error UI.

**What this does:** The form owns controlled inputs and calls an `onSubmit` prop.

**Do this:** Create `resources/js/components/internships/InternshipForm.jsx`; support initial values for edit mode.

**Check:** The same component works with empty values and loaded internship values.

#### Step 3 - Create the company create page

**Goal:** Give companies a page to post internships.

**Why we do this:** This is the first frontend write workflow for internships.

**What this does:** `InternshipCreate.jsx` renders the form, calls `internshipApi.create`, and redirects after success.

**Do this:** Create `resources/js/pages/company/InternshipCreate.jsx`.

**Check:** Creating from UI makes the internship appear in public Browse.

#### Step 4 - Create the company edit page

**Goal:** Let companies update their internships.

**Why we do this:** Real data changes after posting: title, dates, description, and skills may need edits.

**What this does:** `InternshipEdit.jsx` loads by id, fills the form, and submits updates.

**Do this:** Create `resources/js/pages/company/InternshipEdit.jsx`; route it as `/company/internships/:id/edit`.

**Check:** Editing updates the database and the public detail page.

#### Step 5 - Create the company internship list

**Goal:** Show a company its own internships.

**Why we do this:** Companies need a management view separate from public Browse.

**What this does:** `Internships.jsx` fetches company-owned internships and shows create/edit/archive/delete actions.

**Do this:** Create `resources/js/pages/company/Internships.jsx`.

**Check:** A company sees only its own internships.

#### Step 6 - Create the archived page

**Goal:** Show archived internships separately.

**Why we do this:** Archived internships should not clutter active management, but companies may still need to review them.

**What this does:** `ArchivedInternships.jsx` calls `fetchArchived()` and renders archived results.

**Do this:** Create `resources/js/pages/company/ArchivedInternships.jsx`.

**Check:** Archiving moves an internship out of active list and into archived list.

#### Step 7 - Add protected company routes

**Goal:** Connect company URLs to pages and guard them.

**Why we do this:** React should not show company tools to guests or students.

**What this does:** React Router wraps company pages in `ProtectedRoute` and `RoleRoute`.

**Do this:** Add `/company/internships`, `/company/internships/create`, `/company/internships/:id/edit`, and `/company/internships/archived` to `resources/js/router/index.jsx`.

**Check:** Company routes reject guests and non-company users.

#### Step 8 - Map validation errors

**Goal:** Show backend validation errors beside matching fields.

**Why we do this:** Laravel is the source of truth for valid data, and users need useful feedback.

**What this does:** A `422` response becomes field-level messages in `InternshipForm`.

**Do this:** Store `error.response.data.errors` and render errors next to each form input.

**Check:** Submitting an invalid form shows specific field errors.

### Exit Criteria

- Company creates internship from UI -> appears in public Browse.
- Company edits -> change reflected.
- Company archives -> no longer in Browse, shows in Archived tab.
- Validation errors from backend display next to the correct fields.

---

## Slice 4 - Student Profile + CV Upload

**Why now:** Applications need a student profile to exist.

### Backend Walkthrough

#### Step 1 - Create student profile tables

**Goal:** Store student details separately from login details.

**Why we do this:** `users` should hold account/auth data. Student-specific fields like GPA, university, major, skills, and CV belong in profile tables.

**What this does:** `student_profiles` stores one profile per student; `student_skill` links many students to many skills.

**Do this:** Create migrations for `student_profiles` and `student_skill`.

**Check:** A student profile can store bio, university, major, GPA, graduation year, and `cv_path`.

#### Step 2 - Create model relationships

**Goal:** Let Laravel navigate user -> student profile -> skills.

**Why we do this:** Applications and match scoring will need the student's profile and skills often.

**What this does:** `User` has one `StudentProfile`; `StudentProfile` belongs to many `Skill`.

**Do this:** Create `StudentProfile`; add `studentProfile()` to `User`; add `skills()` to `StudentProfile`.

**Check:** In Tinker, `$user->studentProfile->skills` works for a seeded student.

#### Step 3 - Auto-create student profiles

**Goal:** Ensure every student account has a profile row.

**Why we do this:** If profile creation is manual, later features can crash because a student exists without a profile.

**What this does:** Registration creates a blank `StudentProfile` whenever the selected role is `student`.

**Do this:** In the registration flow, after creating a student user, create its profile. Keep this in `AuthController` for now or move it to `AuthService` if you already made one.

**Check:** Registering a student creates both `users` and `student_profiles` rows.

#### Step 4 - Create profile request classes

**Goal:** Validate profile updates and CV uploads.

**Why we do this:** Profile fields and files have different validation rules. Keeping them separate makes errors clearer.

**What this does:** `UpdateProfileRequest` validates text/numeric fields; `UploadCvRequest` validates PDF file type and size.

**Do this:** Create both under `app/Http/Requests/Student/`.

**Check:** Oversized or non-PDF files return `422`.

#### Step 5 - Create `StudentProfileService`

**Goal:** Keep update, skill sync, and file storage logic out of controllers.

**Why we do this:** File uploads have several steps: validate, store, save path, maybe delete/replace old file.

**What this does:** The service updates profile fields, syncs skills, and stores CVs in one predictable place.

**Do this:** Create `app/Services/StudentProfileService.php`.

**Check:** Controller methods stay short and call the service.

#### Step 6 - Create profile controller

**Goal:** Add endpoints for viewing and editing the current student's profile.

**Why we do this:** React needs API endpoints for the profile page.

**What this does:** `show`, `update`, and `uploadCv` act on the authenticated student's profile.

**Do this:** Create `StudentProfileController`; return a profile resource or consistent JSON.

**Check:** A student can fetch and update only their own profile.

#### Step 7 - Create skill sync controller

**Goal:** Let students select and update their skill list.

**Why we do this:** Skills are used later for match scoring, recommendations, and application review.

**What this does:** `index` lists all available skills; `sync` replaces the student's selected skill IDs.

**Do this:** Create `StudentSkillController` with `index` and `sync`.

**Check:** Sending `[1, 3, 5]` as skill IDs updates the pivot table.

#### Step 8 - Add student-only API routes

**Goal:** Expose profile and skill endpoints safely.

**Why we do this:** Profile data is private to the logged-in student.

**What this does:** Routes use `auth:sanctum` and `role:student`.

**Do this:** In `routes/api.php`, add `GET/PUT /student/profile`, `POST /student/profile/cv`, `GET /skills`, and `PUT /student/skills`.

**Check:** Company users receive `403`; guests receive `401`.

#### Step 9 - Configure CV access

**Goal:** Make uploaded CVs retrievable when needed.

**Why we do this:** Uploading is only half the workflow. Later, companies need to view/download CVs.

**What this does:** `php artisan storage:link` exposes public storage through `public/storage`, or you can later build a protected download route.

**Do this:** Decide whether CVs are public-link files or protected downloads. For a student project, public storage is simpler; protected download is safer.

**Check:** After upload, the saved path can be opened or downloaded according to your chosen approach.

### Postman Verification

- [ ] `GET /api/v1/student/profile` returns profile.
- [ ] `PUT /api/v1/student/profile` updates fields.
- [ ] `POST /api/v1/student/profile/cv` with multipart file stores in `storage/app/...`.
- [ ] File size/type validation rejects oversize or non-PDF.

### Frontend Walkthrough

#### Step 1 - Create the student API client

**Goal:** Put all student profile calls in one file.

**Why we do this:** Profile pages, skill selectors, and CV uploads should not duplicate URLs.

**What this does:** `studentApi.js` exposes profile, skill, and upload functions.

**Do this:** Create `resources/js/api/studentApi.js` with `fetchProfile`, `updateProfile`, `uploadCv`, `fetchSkills`, and `syncSkills`.

**Check:** Upload uses `multipart/form-data` through `FormData`.

#### Step 2 - Create `ProfileForm`

**Goal:** Let students edit text and numeric profile fields.

**Why we do this:** The form separates profile editing UI from the full page layout.

**What this does:** `ProfileForm` controls fields like university, major, GPA, graduation year, and bio.

**Do this:** Create `resources/js/components/student/ProfileForm.jsx`.

**Check:** Saved values remain after refresh.

#### Step 3 - Create `SkillSelector`

**Goal:** Let students choose multiple skills.

**Why we do this:** Skills are a many-to-many relationship, so the UI needs a multi-select style control.

**What this does:** The selector displays available skills and submits selected skill IDs.

**Do this:** Create `resources/js/components/common/SkillSelector.jsx`.

**Check:** Adding/removing skills updates the pivot table after save.

#### Step 4 - Create `FileUpload`

**Goal:** Let students upload a CV PDF.

**Why we do this:** File upload handling is different from normal JSON forms.

**What this does:** `FileUpload` accepts a file, sends `FormData`, and optionally shows upload progress.

**Do this:** Create `resources/js/components/common/FileUpload.jsx`.

**Check:** Non-PDF files show the backend validation error.

#### Step 5 - Create the profile page

**Goal:** Compose profile form, skill selector, and CV upload into one page.

**Why we do this:** The page coordinates fetching data and refreshing after saves.

**What this does:** `Profile.jsx` loads profile/skills, renders child components, and handles loading/error states.

**Do this:** Create `resources/js/pages/student/Profile.jsx`.

**Check:** Profile, skills, and CV upload all work from the same page.

#### Step 6 - Add the student profile route

**Goal:** Make `/student/profile` open the profile page.

**Why we do this:** React pages need route entries.

**What this does:** The route is protected by login and student role checks.

**Do this:** Add `/student/profile` to `resources/js/router/index.jsx`.

**Check:** Guests go to login; companies/admins are blocked.

### Exit Criteria

- Student updates profile -> persists after refresh.
- CV uploads successfully and link works.
- Skills sync correctly (add/remove).

---

## Slice 5 - Student Applies to Internship

**Why now:** All prerequisites exist (profile, internships, skills).

### Backend Walkthrough

#### Step 1 - Create the applications table

**Goal:** Store each student's application to an internship.

**Why we do this:** Applying is a real business object with status, score, timestamps, and relationships.

**What this does:** `applications` connects one student profile to one internship.

**Do this:** Add `student_profile_id`, `internship_id`, `status`, `match_score`, and a unique index on `(student_profile_id, internship_id)`.

**Check:** The database prevents duplicate applications to the same internship.

#### Step 2 - Create model and status enum

**Goal:** Represent applications and their allowed statuses in code.

**Why we do this:** Status drives both student and company workflows.

**What this does:** `Application` relates to student profile and internship; `ApplicationStatus` prevents random status strings.

**Do this:** Create `Application` model and `ApplicationStatus` enum with `pending`, `reviewed`, `accepted`, and `rejected`.

**Check:** `$application->status` casts to the enum.

#### Step 3 - Create apply request

**Goal:** Validate application submissions.

**Why we do this:** Applying may include optional message/cover letter, and the internship must be valid/open.

**What this does:** `StoreApplicationRequest` validates application input before `store` runs.

**Do this:** Create `app/Http/Requests/Applications/StoreApplicationRequest.php`.

**Check:** Bad internship or invalid fields return `422`.

#### Step 4 - Create application policy

**Goal:** Define who can view or manage applications.

**Why we do this:** Applications contain student data and company decisions, so access rules matter.

**What this does:** Students can view their own applications; companies can view applications for their own internships.

**Do this:** Create and register `ApplicationPolicy`.

**Check:** A student cannot view another student's application.

#### Step 5 - Create `ApplicationService`

**Goal:** Centralize application creation logic.

**Why we do this:** Applying includes duplicate checks, score calculation, status defaults, and saving.

**What this does:** `apply()` handles the workflow and returns the created application.

**Do this:** Create `app/Services/ApplicationService.php`.

**Check:** Duplicate apply attempts are handled cleanly.

#### Step 6 - Create application resource

**Goal:** Shape application JSON for both student and company views.

**Why we do this:** Students and companies need application data, but not always the same fields.

**What this does:** `ApplicationResource` returns id, internship summary, student summary when allowed, status, match score, and dates.

**Do this:** Create `app/Http/Resources/ApplicationResource.php`.

**Check:** The response does not leak unrelated private data.

#### Step 7 - Create controller methods

**Goal:** Add endpoints for applying and listing applications.

**Why we do this:** React needs routes for apply, student list, company list, and detail.

**What this does:** `store`, `studentIndex`, `companyIndex`, and `show` use policies, services, and resources.

**Do this:** Create `ApplicationController` with those methods.

**Check:** Each endpoint returns only records the current user may see.

#### Step 8 - Add role-scoped API routes

**Goal:** Expose application endpoints with correct role protection.

**Why we do this:** Applying is student-only; company application lists are company-only.

**What this does:** Adds student, company, and shared protected routes under `/api/v1`.

**Do this:** In `routes/api.php`, add `POST /internships/{internship}/applications`, `GET /student/applications`, `GET /company/applications`, and `GET /applications/{application}` with proper middleware.

**Check:** Students and companies can access only their own side of the workflow.

### Postman Verification

- [ ] Student applies -> 201 with `match_score`.
- [ ] Student applies to same internship twice -> 422.
- [ ] `GET /api/v1/student/applications` returns only own.
- [ ] `GET /api/v1/company/applications` returns only to company's internships.

### Frontend Walkthrough

#### Step 1 - Create the application API client

**Goal:** Centralize application HTTP calls.

**Why we do this:** Applying, listing student applications, and company review all depend on application endpoints.

**What this does:** `applicationApi.js` exports `apply`, `fetchMine`, `fetchForCompany`, and later `updateStatus`.

**Do this:** Create `resources/js/api/applicationApi.js`.

**Check:** `apply(id, payload)` posts to `/api/v1/internships/{id}/applications`.

#### Step 2 - Add apply UI to internship detail

**Goal:** Let students apply from the detail page.

**Why we do this:** Users normally decide to apply after reading the full internship details.

**What this does:** `ApplyModal` or an inline form submits the application request.

**Do this:** Create `ApplyModal.jsx` and integrate it into `Detail.jsx`.

**Check:** Successful apply shows confirmation and updates the UI.

#### Step 3 - Create `ApplicationCard`

**Goal:** Display one application summary.

**Why we do this:** The student applications page needs a reusable display component.

**What this does:** Shows internship title, company, status, match score, and applied date.

**Do this:** Create `resources/js/components/applications/ApplicationCard.jsx`.

**Check:** It renders correctly from an `ApplicationResource` response.

#### Step 4 - Create student applications page

**Goal:** Let students track their applications.

**Why we do this:** Applying is not enough; students need status visibility.

**What this does:** `Applications.jsx` fetches the current student's applications and renders cards.

**Do this:** Create `resources/js/pages/student/Applications.jsx`.

**Check:** The list shows only the logged-in student's applications.

#### Step 5 - Add student applications route

**Goal:** Make `/student/applications` reachable.

**Why we do this:** React needs a route entry for every page.

**What this does:** The route is protected by login and student role guards.

**Do this:** Add `/student/applications` to `resources/js/router/index.jsx`.

**Check:** Guests and company users cannot access it.

#### Step 6 - Disable duplicate apply

**Goal:** Prevent confusing repeated submissions.

**Why we do this:** The database blocks duplicates, but the UI should still guide the user.

**What this does:** The Apply button is disabled if already applied, or the `422` duplicate error is shown clearly.

**Do this:** Use application state from the API if available; otherwise catch duplicate errors from backend.

**Check:** Applying twice shows "already applied" instead of a generic failure.

### Exit Criteria

- Student applies from detail page -> confirmation + redirect.
- Reapplying shows "already applied".
- Applications list shows status + match score.

---

## Slice 6 - Match Score Logic

**Why now:** Applications exist to display the score against. Also a small standalone feature that is easy to test in isolation.

### Backend Walkthrough

#### Step 1 - Create `MatchScoreService`

**Goal:** Put scoring logic in one testable class.

**Why we do this:** Match scoring is business logic, not controller logic. It should be easy to test without making HTTP requests.

**What this does:** `calculate(StudentProfile $studentProfile, Internship $internship): int` returns a score from 0 to 100.

**Do this:** Create `app/Services/MatchScoreService.php`.

**Check:** You can call the service from Tinker or a unit test.

#### Step 2 - Implement skill overlap

**Goal:** Base the score on matching student skills to internship skills.

**Why we do this:** Skills are the strongest objective signal for internship fit.

**What this does:** Compares student skill IDs to required internship skill IDs and computes `matched / required * 100`.

**Do this:** Load both skill lists, compare IDs, and handle internships with zero required skills safely.

**Check:** If 2 of 4 required skills match, the base score is 50.

#### Step 3 - Add bonus rules

**Goal:** Add small extra signals without overpowering skill match.

**Why we do this:** GPA and graduation year can improve fit, but should not dominate the score.

**What this does:** Adds bonuses like +5 for GPA > 3.5 and +5 for matching graduation year, then clamps to 100.

**Do this:** Add bonus logic inside `MatchScoreService`.

**Check:** A score never goes below 0 or above 100.

#### Step 4 - Create match controller

**Goal:** Expose scoring through API endpoints.

**Why we do this:** React needs to show one internship score and a recommendations list.

**What this does:** `score` returns the current student's score for one internship; `recommendations` returns top open internships by score.

**Do this:** Create `app/Http/Controllers/Api/V1/MatchController.php`.

**Check:** Only student users can request scores.

#### Step 5 - Add match API routes

**Goal:** Make score endpoints reachable.

**Why we do this:** Controller actions need routes, and score data belongs behind student auth.

**What this does:** Adds `GET /internships/{internship}/match-score` and `GET /student/recommendations`.

**Do this:** Add these routes in `routes/api.php` under `auth:sanctum` + `role:student`.

**Check:** Guests receive `401`; companies receive `403`.

#### Step 6 - Add unit tests

**Goal:** Prove the scoring math works.

**Why we do this:** Score logic is easy to break accidentally. Unit tests protect the formula.

**What this does:** Tests known inputs and exact expected scores.

**Do this:** Create `tests/Unit/MatchScoreServiceTest.php`.

**Check:** `php artisan test --filter=MatchScoreServiceTest` passes.

### Postman Verification

- [ ] `GET /api/v1/internships/{id}/match-score` returns number 0-100.
- [ ] `GET /api/v1/student/recommendations` returns top-matched internships.

### Frontend Walkthrough

#### Step 1 - Create match API client

**Goal:** Centralize score and recommendation calls.

**Why we do this:** Score UI appears in several places, so it should use one API module.

**What this does:** `matchApi.js` exposes `fetchScore(internshipId)` and `fetchRecommendations()`.

**Do this:** Create `resources/js/api/matchApi.js`.

**Check:** Both functions use the shared Axios client and require a token.

#### Step 2 - Create `MatchScoreBadge`

**Goal:** Display scores consistently.

**Why we do this:** A number alone is less scannable than a labeled, color-tiered badge.

**What this does:** Shows the score and visual tier, such as high/medium/low.

**Do this:** Create `resources/js/components/match/MatchScoreBadge.jsx`.

**Check:** Scores like 90, 60, and 30 produce different visual tiers.

#### Step 3 - Show score on internship cards

**Goal:** Let students scan fit while browsing.

**Why we do this:** Match score is most useful when comparing many internships.

**What this does:** `InternshipCard` conditionally renders `MatchScoreBadge` for logged-in students.

**Do this:** Update `InternshipCard.jsx`; read auth state and display score if available.

**Check:** Guests and companies do not see student match badges.

#### Step 4 - Create recommendations page

**Goal:** Show the best matches first.

**Why we do this:** Recommendations are a student-focused shortcut through the browse list.

**What this does:** `Recommendations.jsx` fetches scored internships and displays them descending by score.

**Do this:** Create `resources/js/pages/student/Recommendations.jsx`.

**Check:** Highest score appears first.

#### Step 5 - Add recommendations route

**Goal:** Make `/student/recommendations` reachable.

**Why we do this:** React needs a route entry and role guard.

**What this does:** Protects recommendations behind student auth.

**Do this:** Add the route to `resources/js/router/index.jsx`.

**Check:** Guests and companies are blocked.

### Exit Criteria

- Students see match score on every internship where appropriate.
- Recommendations page sorted by score descending.
- Unit tests for match logic pass.

---

## Slice 7 - Company Manages Applicants

**Why now:** Companies need to see who applied.

### Backend Walkthrough

#### Step 1 - Create status update request

**Goal:** Validate company status changes.

**Why we do this:** Companies should only set allowed application statuses.

**What this does:** `UpdateStatusRequest` checks that status is valid, and can later enforce transition rules.

**Do this:** Create `app/Http/Requests/Applications/UpdateStatusRequest.php`.

**Check:** Invalid status values return `422`.

#### Step 2 - Add `updateStatus`

**Goal:** Let companies review applicants by changing application status.

**Why we do this:** Application review is a company workflow and must be saved to the database.

**What this does:** Loads the application, authorizes ownership, updates status, and returns `ApplicationResource`.

**Do this:** Add `updateStatus` to `ApplicationController`.

**Check:** Status changes persist and are visible to the student.

#### Step 3 - Enforce ownership policy

**Goal:** Prevent companies from changing applications for internships they do not own.

**Why we do this:** Role checks alone are not enough. Any company is a company, but only one company owns a given internship.

**What this does:** `ApplicationPolicy` checks the application internship's company profile against the current company user.

**Do this:** Add or update the policy method used by `updateStatus`.

**Check:** Another company receives `403`.

#### Step 4 - Add company status route

**Goal:** Expose the status update endpoint.

**Why we do this:** React needs a route to call when a company changes a dropdown.

**What this does:** Adds `PATCH /api/v1/company/applications/{application}/status`.

**Do this:** Add this route under `auth:sanctum` + `role:company` in `routes/api.php`.

**Check:** The endpoint rejects guests, students, and non-owning companies.

### Postman Verification

- [ ] Company sets status to accepted/rejected/reviewed.
- [ ] Other company cannot change it -> 403.

### Frontend Walkthrough

#### Step 1 - Extend application API client

**Goal:** Add company review calls.

**Why we do this:** The same API module should handle student and company application actions.

**What this does:** Adds `fetchForCompany()` and `updateStatus(applicationId, status)`.

**Do this:** Update `resources/js/api/applicationApi.js`.

**Check:** Status updates call the `PATCH` route.

#### Step 2 - Create applicants page

**Goal:** Show companies who applied.

**Why we do this:** Companies need a review workspace for incoming applications.

**What this does:** `Applicants.jsx` fetches company applications and renders rows or groups by internship.

**Do this:** Create `resources/js/pages/company/Applicants.jsx`.

**Check:** A company sees applications only for its own internships.

#### Step 3 - Create `ApplicantRow`

**Goal:** Display one applicant with review controls.

**Why we do this:** A row component keeps the table/list readable.

**What this does:** Shows student info, internship title, score, status, and a status dropdown.

**Do this:** Create `resources/js/components/applications/ApplicantRow.jsx`.

**Check:** Changing the dropdown calls `updateStatus`.

#### Step 4 - Add CV download link

**Goal:** Let companies view applicant CVs.

**Why we do this:** CV review is a normal part of application management.

**What this does:** Uses a CV URL/path from `ApplicationResource` and hides the link when no CV exists.

**Do this:** Add CV data to `ApplicationResource` if needed, then render the link in `ApplicantRow`.

**Check:** Applicants without CVs do not show a broken link.

#### Step 5 - Add company applicants route

**Goal:** Make `/company/applicants` reachable.

**Why we do this:** React needs a guarded page route.

**What this does:** Protects the applicants page for company users.

**Do this:** Add route in `resources/js/router/index.jsx`.

**Check:** Guests and students cannot access it.

### Exit Criteria

- Company reviews applications and changes status.
- Student sees updated status on their side.

---

## Slice 8 - Admin Dashboard

**Why last:** Aggregates from all the data now in the system.

### Backend Walkthrough

#### Step 1 - Seed an admin user

**Goal:** Have a predictable admin account for testing.

**Why we do this:** You cannot test admin-only routes without an admin user.

**What this does:** `DatabaseSeeder` creates a user with `UserRole::Admin`.

**Do this:** Add a local admin seed with a known email/password for development only.

**Check:** You can log in as admin and receive a token.

#### Step 2 - Create dashboard service

**Goal:** Keep dashboard calculations out of the controller.

**Why we do this:** Admin stats usually combine several models and queries.

**What this does:** `AdminDashboardService` calculates totals, trends, active internships, application counts, and top companies.

**Do this:** Create `app/Services/AdminDashboardService.php`.

**Check:** The service returns one structured array/object of stats.

#### Step 3 - Create dashboard controller

**Goal:** Expose admin stats as JSON.

**Why we do this:** React needs one endpoint for dashboard data.

**What this does:** `AdminDashboardController@index` calls the service and returns the response.

**Do this:** Create `app/Http/Controllers/Api/V1/AdminDashboardController.php`.

**Check:** The controller has little logic beyond calling the service.

#### Step 4 - Create admin user controller

**Goal:** Let admins view and remove users.

**Why we do this:** User management is a common admin requirement.

**What this does:** `index` lists users; `destroy` deletes or disables a user.

**Do this:** Create `app/Http/Controllers/Api/V1/AdminUserController.php`; prevent deleting yourself if you want safer behavior.

**Check:** Admin can list users; non-admin cannot.

#### Step 5 - Create admin resources

**Goal:** Shape admin JSON responses.

**Why we do this:** Admin pages need stats/user rows, but still should not receive passwords or internal-only fields.

**What this does:** `AdminDashboardResource` and `AdminUserResource` define response shape.

**Do this:** Create both resource files.

**Check:** User rows expose id, name, email, role, and created date, not password fields.

#### Step 6 - Add admin API routes

**Goal:** Expose admin endpoints safely.

**Why we do this:** Admin routes must be guarded more strictly than normal protected routes.

**What this does:** Adds `/api/v1/admin/dashboard`, `/api/v1/admin/users`, and user delete route behind `role:admin`.

**Do this:** Add routes in `routes/api.php` under `auth:sanctum` + `role:admin`.

**Check:** Student/company tokens receive `403`.

### Postman Verification

- [ ] `GET /api/v1/admin/dashboard` returns aggregated object.
- [ ] Non-admin -> 403.

### Frontend Walkthrough

#### Step 1 - Create admin API client

**Goal:** Centralize admin HTTP calls.

**Why we do this:** Admin pages will call several protected endpoints.

**What this does:** `adminApi.js` exposes `fetchDashboard`, `fetchUsers`, and `deleteUser`.

**Do this:** Create `resources/js/api/adminApi.js`.

**Check:** All functions use the shared Axios token interceptor.

#### Step 2 - Create stats cards

**Goal:** Display dashboard numbers clearly.

**Why we do this:** Admins need quick scanning before table-level details.

**What this does:** `StatsCards` renders totals like users, internships, applications, and active internships.

**Do this:** Create `resources/js/components/admin/StatsCards.jsx`.

**Check:** Cards render correctly from the dashboard payload.

#### Step 3 - Create users table

**Goal:** Let admins inspect and remove users.

**Why we do this:** User management is a core admin workflow.

**What this does:** `UsersTable` displays user rows and delete actions with confirmation.

**Do this:** Create `resources/js/components/admin/UsersTable.jsx`.

**Check:** Deleting refreshes the list or removes the row from state.

#### Step 4 - Create internships table

**Goal:** Show important internship records to admins.

**Why we do this:** Admins need visibility into platform content.

**What this does:** `InternshipsTable` displays active/recent internships with company and status.

**Do this:** Create `resources/js/components/admin/InternshipsTable.jsx`.

**Check:** Table does not overflow on smaller screens.

#### Step 5 - Create admin dashboard page

**Goal:** Compose admin cards and tables into one page.

**Why we do this:** The page coordinates fetching dashboard data and handling loading/error states.

**What this does:** `Dashboard.jsx` calls `adminApi`, renders stats and tables, and handles empty/error states.

**Do this:** Create `resources/js/pages/admin/Dashboard.jsx`.

**Check:** Admin sees real data after login.

#### Step 6 - Add admin route

**Goal:** Make `/admin/dashboard` reachable only to admins.

**Why we do this:** React should mirror backend role protection.

**What this does:** The route uses `ProtectedRoute` and `RoleRoute role="admin"`.

**Do this:** Add the route in `resources/js/router/index.jsx`.

**Check:** Non-admin users are blocked.

### Exit Criteria

- Admin sees real-time stats.
- Admin can delete or disable misbehaving users.
- Non-admin accounts blocked from `/admin/*` routes.

---

## Slice 9 - Polish, Test, Document

**Final pass before submission.**

### Backend Walkthrough

#### Step 1 - Add feature tests

**Goal:** Prove the API works as a user would use it.

**Why we do this:** Feature tests catch broken routes, middleware, validation, and permissions.

**What this does:** Tests cover auth, internships, applications, match score, and admin flows.

**Do this:** Create `AuthTest`, `InternshipTest`, `ApplicationTest`, `MatchScoreTest`, and `AdminTest` under `tests/Feature/`.

**Check:** Tests include both success and forbidden cases.

#### Step 2 - Run the full test suite

**Goal:** Confirm the whole backend is stable.

**Why we do this:** Passing one endpoint manually is not enough before submission.

**What this does:** `php artisan test` runs feature and unit tests.

**Do this:** Run `php artisan test`.

**Check:** All tests pass before final delivery.

#### Step 3 - Add database indexes

**Goal:** Speed up common queries.

**Why we do this:** Foreign keys and status filters will be used often as data grows.

**What this does:** Adds indexes on hot columns like company, student, internship, status, and dates.

**Do this:** Create a migration that adds missing indexes.

**Check:** Index names are clear and migration rolls back cleanly.

#### Step 4 - Check eager loading

**Goal:** Avoid N+1 database query problems.

**Why we do this:** Listing 20 internships should not run dozens of extra queries for company and skills.

**What this does:** Controllers use `with([...])` before returning list/detail resources.

**Do this:** Review controller `index` and `show` methods.

**Check:** Resources do not trigger surprise relationship queries in loops.

#### Step 5 - Add rate limiting

**Goal:** Reduce abuse on sensitive endpoints.

**Why we do this:** Login/register endpoints are common attack targets.

**What this does:** Applies stricter request limits to auth routes.

**Do this:** Configure a limiter and apply it to login/register routes if required.

**Check:** Repeated login attempts eventually return `429`.

#### Step 6 - Review security

**Goal:** Make sure every route has the right protection.

**Why we do this:** A single missing middleware or policy can expose private data or allow unauthorized writes.

**What this does:** Checks routes, policies, Form Requests, and Resources together.

**Do this:** Review `routes/api.php`, `app/Policies/`, `app/Http/Requests/`, and `app/Http/Resources/`.

**Check:** Every write route has auth, role/policy checks, validation, and safe output.

### Frontend Walkthrough

#### Step 1 - Responsive QA

**Goal:** Make every page usable on mobile, tablet, and desktop.

**Why we do this:** A working feature can still feel broken if layout overflows or controls are hard to tap.

**What this does:** Finds spacing, wrapping, and layout issues.

**Do this:** Manually check key pages at small, medium, and large widths.

**Check:** No important text or buttons overlap or overflow.

#### Step 2 - Loading/error audit

**Goal:** Make every API state visible.

**Why we do this:** Users need to know whether the app is loading, empty, failed, or done.

**What this does:** Every API page gets loading, empty, and error UI.

**Do this:** Review each page that calls an API.

**Check:** Temporarily breaking the API shows a useful error.

#### Step 3 - Form validation audit

**Goal:** Show Laravel validation errors clearly.

**Why we do this:** Backend validation is only helpful if users can see what to fix.

**What this does:** `422` errors appear beside matching fields.

**Do this:** Test every form with bad data.

**Check:** Each invalid field shows its own message.

#### Step 4 - Accessibility pass

**Goal:** Make the UI usable with keyboard and assistive technology basics.

**Why we do this:** Labels, focus states, and semantic buttons are part of professional frontend work.

**What this does:** Adds labels, clear button text, keyboard-friendly controls, and focus styles.

**Do this:** Tab through the app and fix confusing controls.

**Check:** You can navigate forms and actions without a mouse.

#### Step 5 - Production build test

**Goal:** Confirm React builds for deployment.

**Why we do this:** Dev mode can hide build errors that appear only during production bundling.

**What this does:** `npm run build` compiles Vite assets.

**Do this:** Run `npm run build`.

**Check:** Build finishes without errors.

### Documentation Walkthrough

#### Step 1 - Update README

**Goal:** Make the project runnable by someone else.

**Why we do this:** Good code still fails submission if nobody can install or test it.

**What this does:** README explains install, `.env`, migrations, seeders, Laravel server, Vite server, tests, and local admin login.

**Do this:** Update `README.md`.

**Check:** A fresh developer can follow the README from clone to running app.

#### Step 2 - Update `.env.example`

**Goal:** Document required environment variables without secrets.

**Why we do this:** `.env` is local and ignored; `.env.example` tells people what to configure.

**What this does:** Shows required DB, app URL, filesystem, and Sanctum-related settings.

**Do this:** Update `.env.example`.

**Check:** No real passwords, tokens, or private values are committed.

#### Step 3 - Export Postman collection

**Goal:** Save tested API requests.

**Why we do this:** Postman collections prove endpoints were exercised and make demos easier.

**What this does:** `docs/postman.json` contains endpoints, sample payloads, and auth setup without real secrets.

**Do this:** Export from Postman after backend checks pass.

**Check:** Importing the collection into Postman works.

#### Step 4 - Optional API docs

**Goal:** Generate browsable API documentation if time allows.

**Why we do this:** Swagger/OpenAPI can improve presentation and maintainability, but it is not required for the core build.

**What this does:** Documents endpoints, parameters, and responses.

**Do this:** Add Swagger/OpenAPI only after required slices are complete.

**Check:** Docs match real routes.

#### Step 5 - Optional deployment files

**Goal:** Prepare deployment if required.

**Why we do this:** Deployment has separate concerns: PHP runtime, database, built Vite assets, storage link, and environment variables.

**What this does:** Docker or VPS notes make the app easier to run outside your machine.

**Do this:** Add `Dockerfile`, `docker-compose.yml`, or deployment notes only if your submission needs them.

**Check:** Deployment instructions do not replace the main local setup instructions.

---

## Code Contracts And Starter Shapes

Use this section when a walkthrough step says "create X" and you are not sure what X should contain. These are not meant to be perfect final code; they are the minimum shape you can build from.

### Slice 1 - Authentication Contracts

#### `resources/js/api/authApi.js`

**Exports you need:**

```js
import api from './axios';

export function register(payload) {
    return api.post('/register', payload);
}

export function login(payload) {
    return api.post('/login', payload);
}

export function logout() {
    return api.post('/logout');
}

export function me() {
    return api.get('/me');
}
```

**Why it looks like this:** The Axios instance already has `baseURL: '/api/v1'`, so `api.post('/login')` means `POST /api/v1/login`.

#### `resources/js/components/common/ProtectedRoute.jsx`

**Exports you need:** default component or named component. Pick one style and stay consistent.

```jsx
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function ProtectedRoute() {
    const { isAuthenticated, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return <div>Loading...</div>;
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace state={{ from: location }} />;
    }

    return <Outlet />;
}
```

**Variables to notice:** `loading` prevents redirecting before the app finishes checking the saved token.

#### `resources/js/components/common/RoleRoute.jsx`

```jsx
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function RoleRoute({ allowedRoles }) {
    const { user, loading } = useAuth();

    if (loading) {
        return <div>Loading...</div>;
    }

    if (!user || !allowedRoles.includes(user.role)) {
        return <Navigate to="/403" replace />;
    }

    return <Outlet />;
}
```

**Expected prop:** `allowedRoles` is an array, for example `['company']` or `['admin']`.

#### `resources/js/router/index.jsx`

```jsx
import { createBrowserRouter } from 'react-router-dom';
import App from '../App';
import ProtectedRoute from '../components/common/ProtectedRoute';
import RoleRoute from '../components/common/RoleRoute';
import Login from '../pages/auth/Login';
import Register from '../pages/auth/Register';

export const router = createBrowserRouter([
    {
        path: '/',
        element: <App />,
    },
    {
        path: '/login',
        element: <Login />,
    },
    {
        path: '/register',
        element: <Register />,
    },
    {
        element: <ProtectedRoute />,
        children: [
            {
                path: '/dashboard',
                element: <div>Dashboard</div>,
            },
            {
                element: <RoleRoute allowedRoles={['company']} />,
                children: [
                    {
                        path: '/company/dashboard',
                        element: <div>Company dashboard</div>,
                    },
                ],
            },
        ],
    },
]);
```

**What to export:** Export `router`, then import it in `main.jsx`.

#### `resources/js/main.jsx`

```jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { router } from './router';
import '../css/app.css';

createRoot(document.getElementById('root')).render(
    <React.StrictMode>
        <AuthProvider>
            <RouterProvider router={router} />
        </AuthProvider>
    </React.StrictMode>,
);
```

#### Backend auth response shape

Keep register and login responses consistent:

```php
return response()->json([
    'token' => $token,
    'user' => new UserResource($user),
]);
```

For `me`, return:

```php
return new UserResource($request->user());
```

### Slice 2 - Browse Internships Contracts

#### Backend files and methods

`app/Models/Internship.php` should eventually have:

```php
public function company()
{
    return $this->belongsTo(CompanyProfile::class, 'company_profile_id');
}

public function skills()
{
    return $this->belongsToMany(Skill::class);
}
```

`app/Http/Controllers/Api/V1/InternshipController.php` should expose:

```php
public function index(Request $request)
{
    // read filters, eager load company/skills, paginate, return collection
}

public function show(Internship $internship)
{
    // load company/skills, return resource
}
```

`app/Http/Resources/InternshipResource.php` should return a predictable shape:

```php
return [
    'id' => $this->id,
    'title' => $this->title,
    'description' => $this->description,
    'location' => $this->location,
    'type' => $this->type,
    'status' => $this->status,
    'company' => new CompanyProfileResource($this->whenLoaded('company')),
    'skills' => SkillResource::collection($this->whenLoaded('skills')),
];
```

#### `resources/js/api/internshipApi.js`

```js
import api from './axios';

export function fetchAll(filters = {}) {
    return api.get('/internships', { params: filters });
}

export function fetchOne(id) {
    return api.get(`/internships/${id}`);
}
```

#### Browse page state variables

`Browse.jsx` should normally declare:

```jsx
const [internships, setInternships] = useState([]);
const [meta, setMeta] = useState(null);
const [filters, setFilters] = useState({ search: '', page: 1 });
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

**Why these exist:** `internships` renders the list, `meta` powers pagination, `filters` controls the request, `loading/error` control UI states.

#### Component props

Use these simple prop contracts:

```jsx
<InternshipCard internship={internship} />
<InternshipList internships={internships} />
<InternshipFilters filters={filters} onChange={setFilters} />
<Pagination meta={meta} onPageChange={(page) => setFilters({ ...filters, page })} />
```

### Slice 3 - Company Internship Management Contracts

#### Backend service shape

`app/Services/InternshipService.php` should expose methods like:

```php
public function create(User $user, array $data): Internship
{
    // create internship for $user->companyProfile, sync skills, return model
}

public function update(Internship $internship, array $data): Internship
{
    // update fields, sync skills if present, return model
}

public function archive(Internship $internship): Internship
{
    // set status archived, save, return model
}

public function delete(Internship $internship): void
{
    // soft delete
}
```

#### Controller method names

`InternshipController` should now include:

```php
public function companyIndex(Request $request) {}
public function archived(Request $request) {}
public function store(StoreInternshipRequest $request, InternshipService $service) {}
public function update(UpdateInternshipRequest $request, Internship $internship, InternshipService $service) {}
public function archive(Internship $internship, InternshipService $service) {}
public function destroy(Internship $internship, InternshipService $service) {}
```

#### `resources/js/api/internshipApi.js` extra exports

```js
export function create(payload) {
    return api.post('/internships', payload);
}

export function update(id, payload) {
    return api.put(`/internships/${id}`, payload);
}

export function remove(id) {
    return api.delete(`/internships/${id}`);
}

export function archive(id) {
    return api.patch(`/internships/${id}/archive`);
}

export function fetchMine(params = {}) {
    return api.get('/company/internships', { params });
}

export function fetchArchived(params = {}) {
    return api.get('/company/internships/archived', { params });
}
```

#### `InternshipForm.jsx` props and state

```jsx
export default function InternshipForm({ initialValues, onSubmit, submitting, errors = {} }) {
    const [values, setValues] = useState(initialValues ?? {
        title: '',
        description: '',
        location: '',
        type: 'remote',
        skills: [],
    });

    function handleChange(event) {
        setValues({
            ...values,
            [event.target.name]: event.target.value,
        });
    }

    function handleSubmit(event) {
        event.preventDefault();
        onSubmit(values);
    }

    // render inputs using values, handleChange, submitting, errors
}
```

**Expected exports:** Default export `InternshipForm`.

### Slice 4 - Student Profile Contracts

#### Backend files and method names

`StudentProfileController` should expose:

```php
public function show(Request $request) {}
public function update(UpdateProfileRequest $request, StudentProfileService $service) {}
public function uploadCv(UploadCvRequest $request, StudentProfileService $service) {}
```

`StudentSkillController` should expose:

```php
public function index() {}
public function sync(Request $request) {}
```

`StudentProfileService` should expose:

```php
public function updateProfile(StudentProfile $profile, array $data): StudentProfile {}
public function syncSkills(StudentProfile $profile, array $skillIds): StudentProfile {}
public function uploadCv(StudentProfile $profile, UploadedFile $file): StudentProfile {}
```

#### `resources/js/api/studentApi.js`

```js
import api from './axios';

export function fetchProfile() {
    return api.get('/student/profile');
}

export function updateProfile(payload) {
    return api.put('/student/profile', payload);
}

export function uploadCv(file, onUploadProgress) {
    const formData = new FormData();
    formData.append('cv', file);

    return api.post('/student/profile/cv', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress,
    });
}

export function fetchSkills() {
    return api.get('/skills');
}

export function syncSkills(skillIds) {
    return api.put('/student/skills', { skills: skillIds });
}
```

#### Component prop contracts

```jsx
<ProfileForm profile={profile} onSubmit={handleProfileSubmit} errors={errors} />
<SkillSelector skills={skills} selectedIds={selectedSkillIds} onChange={setSelectedSkillIds} />
<FileUpload accept="application/pdf" onUpload={handleCvUpload} />
```

**Page state for `Profile.jsx`:**

```jsx
const [profile, setProfile] = useState(null);
const [skills, setSkills] = useState([]);
const [selectedSkillIds, setSelectedSkillIds] = useState([]);
const [loading, setLoading] = useState(true);
const [errors, setErrors] = useState({});
```

### Slice 5 - Applications Contracts

#### Backend application service

`ApplicationService` should expose:

```php
public function apply(StudentProfile $studentProfile, Internship $internship, array $data = []): Application
{
    // prevent duplicate, calculate score, create with pending status
}
```

`ApplicationController` should expose:

```php
public function store(StoreApplicationRequest $request, Internship $internship, ApplicationService $service) {}
public function studentIndex(Request $request) {}
public function companyIndex(Request $request) {}
public function show(Application $application) {}
```

#### `resources/js/api/applicationApi.js`

```js
import api from './axios';

export function apply(internshipId, payload = {}) {
    return api.post(`/internships/${internshipId}/applications`, payload);
}

export function fetchMine(params = {}) {
    return api.get('/student/applications', { params });
}

export function fetchForCompany(params = {}) {
    return api.get('/company/applications', { params });
}

export function fetchOne(id) {
    return api.get(`/applications/${id}`);
}
```

#### Component prop contracts

```jsx
<ApplyModal internship={internship} open={open} onClose={closeModal} onApplied={refreshApplicationState} />
<ApplicationCard application={application} />
```

**Apply modal state:**

```jsx
const [message, setMessage] = useState('');
const [submitting, setSubmitting] = useState(false);
const [error, setError] = useState(null);
```

### Slice 6 - Match Score Contracts

#### Backend scoring method

`MatchScoreService` should have one main public method:

```php
public function calculate(StudentProfile $studentProfile, Internship $internship): int
{
    // return 0-100
}
```

`MatchController` should expose:

```php
public function score(Request $request, Internship $internship) {}
public function recommendations(Request $request) {}
```

#### `resources/js/api/matchApi.js`

```js
import api from './axios';

export function fetchScore(internshipId) {
    return api.get(`/internships/${internshipId}/match-score`);
}

export function fetchRecommendations(params = {}) {
    return api.get('/student/recommendations', { params });
}
```

#### `MatchScoreBadge.jsx`

```jsx
export default function MatchScoreBadge({ score }) {
    const tier = score >= 80 ? 'high' : score >= 50 ? 'medium' : 'low';

    return (
        <span data-tier={tier}>
            {score}% match
        </span>
    );
}
```

**Expected prop:** `score` is a number from 0 to 100.

### Slice 7 - Company Applicants Contracts

#### Backend update status method

Add to `ApplicationController`:

```php
public function updateStatus(UpdateStatusRequest $request, Application $application)
{
    // authorize company ownership, update status, return resource
}
```

#### Add to `resources/js/api/applicationApi.js`

```js
export function updateStatus(applicationId, status) {
    return api.patch(`/company/applications/${applicationId}/status`, { status });
}
```

#### `ApplicantRow.jsx`

```jsx
export default function ApplicantRow({ application, onStatusChange }) {
    function handleChange(event) {
        onStatusChange(application.id, event.target.value);
    }

    return (
        <tr>
            <td>{application.student?.name}</td>
            <td>{application.internship?.title}</td>
            <td>{application.match_score}</td>
            <td>
                <select value={application.status} onChange={handleChange}>
                    <option value="pending">Pending</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </td>
        </tr>
    );
}
```

**Expected props:** `application` is one API resource row; `onStatusChange` calls the API and updates state.

### Slice 8 - Admin Contracts

#### Backend controller methods

`AdminDashboardController`:

```php
public function index(AdminDashboardService $service) {}
```

`AdminUserController`:

```php
public function index(Request $request) {}
public function destroy(User $user) {}
```

`AdminDashboardService`:

```php
public function stats(): array
{
    return [
        'total_users' => 0,
        'total_students' => 0,
        'total_companies' => 0,
        'active_internships' => 0,
        'total_applications' => 0,
    ];
}
```

#### `resources/js/api/adminApi.js`

```js
import api from './axios';

export function fetchDashboard() {
    return api.get('/admin/dashboard');
}

export function fetchUsers(params = {}) {
    return api.get('/admin/users', { params });
}

export function deleteUser(id) {
    return api.delete(`/admin/users/${id}`);
}
```

#### Admin component prop contracts

```jsx
<StatsCards stats={stats} />
<UsersTable users={users} onDelete={handleDeleteUser} />
<InternshipsTable internships={internships} />
```

**Admin page state:**

```jsx
const [stats, setStats] = useState(null);
const [users, setUsers] = useState([]);
const [internships, setInternships] = useState([]);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

### Slice 9 - Polish Contracts

Use these checklists when the feature code already works.

#### Every API page should have this state shape

```jsx
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

Render rules:

```jsx
if (loading) return <LoadingSpinner />;
if (error) return <ErrorAlert message={error} />;
```

#### Every form should have this error handling shape

```jsx
const [errors, setErrors] = useState({});

try {
    await submit(values);
} catch (error) {
    if (error.response?.status === 422) {
        setErrors(error.response.data.errors ?? {});
    }
}
```

#### Every protected route should be checked in two places

- Frontend: React Router uses `ProtectedRoute` and `RoleRoute`.
- Backend: `routes/api.php` uses `auth:sanctum`, `role:*`, and policies.

Frontend route guards are for user experience. Backend guards are for real security.

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
