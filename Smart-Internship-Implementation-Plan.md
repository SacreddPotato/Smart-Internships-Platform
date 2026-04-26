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
- If the new user's role is `company`, create a minimal company profile immediately.
- Create a token using `$user->createToken('auth-token')->plainTextToken`.
- Return JSON with `token` and `user`.

Company profile creation rule:

```php
if ($user->role === UserRole::Company) {
    $user->companyProfile()->create([
        'company_name' => $user->name,
    ]);
}
```

If your enum cast returns an enum object, compare with:

```php
if ($user->role === UserRole::Company) {
    // create profile
}
```

If you have not cast `role` yet and it is still a string, compare with:

```php
if ($user->role === 'company') {
    // create profile
}
```

Use the enum version once Step 3 is complete.

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
- Registering with `role = company` also creates one row in `company_profiles`.
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
                setUser(response.data.user);
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

**Do this:** In `resources/js/main.jsx`, render the React Router instead of rendering `<App />` directly.

Your `main.jsx` should have this shape:

```jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext.jsx';
import router from './router.jsx';
import '../css/app.css';

createRoot(document.getElementById('root')).render(
    <React.StrictMode>
        <AuthProvider>
            <RouterProvider router={router} />
        </AuthProvider>
    </React.StrictMode>,
);
```

This means:

- `AuthProvider` owns auth state.
- `RouterProvider` owns page routing.
- `router.jsx` decides which layout/page renders.
- `App.jsx` is no longer the root page unless you intentionally use it inside the router.

**Check:** A page can call your auth hook/context and read `user` without errors.

#### Step 4 - Create login and register pages

**Goal:** Let real users create accounts and sign in from the UI.

**Why we do this:** The backend works through Postman first, but the product needs browser forms that call those backend endpoints.

**What this does:** `Login.jsx` and `Register.jsx` collect form data, call the auth context functions, show validation errors, and redirect after success.

**Do this:** Create `resources/js/pages/auth/Login.jsx` and `resources/js/pages/auth/Register.jsx`.

**What "fields matching backend requests" means:**

Look at your Laravel Form Request rules:

- `LoginRequest` expects `email` and `password`.
- `RegisterRequest` expects `name`, `email`, `password`, `password_confirmation`, and `role`.

Your React form state should use the same names because those names become the JSON keys sent to Laravel.

`Login.jsx` state:

```jsx
const [form, setForm] = useState({
    email: '',
    password: '',
});

const [errors, setErrors] = useState({});
const [submitting, setSubmitting] = useState(false);
```

`Login.jsx` submit payload:

```js
await login(form);
```

`Register.jsx` state:

```jsx
const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'student',
});

const [errors, setErrors] = useState({});
const [submitting, setSubmitting] = useState(false);
```

`Register.jsx` submit payload:

```js
await register(form);
```

Use one shared change handler in each page:

```jsx
function handleChange(event) {
    setForm({
        ...form,
        [event.target.name]: event.target.value,
    });
}
```

The important part is the `name` attribute:

```jsx
<input name="email" value={form.email} onChange={handleChange} />
```

That `name="email"` is what lets the generic `handleChange` update `form.email`.

**Login page render checklist:**

- Page title: "Welcome back"
- Subtitle: short line like "Sign in to continue."
- Email input: `name="email"`, `type="email"`
- Password input: `name="password"`, `type="password"`
- Submit button disabled while `submitting`
- Link to `/register`
- Render `errors.email` and `errors.password` under the matching fields

**Register page render checklist:**

- Page title: "Create your account"
- Subtitle: short line like "Choose your role and enter your details."
- Name input: `name="name"`
- Email input: `name="email"`, `type="email"`
- Password input: `name="password"`, `type="password"`
- Password confirmation input: `name="password_confirmation"`, `type="password"`
- Role select or role cards using values `student`, `company`, `admin`
- Submit button disabled while `submitting`
- Link to `/login`
- Render each Laravel validation error under the matching field

**Submit function shape for both pages:**

```jsx
async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});

    try {
        await login(form); // or register(form)
        navigate('/dashboard');
    } catch (error) {
        if (error.response?.status === 422) {
            setErrors(error.response.data.errors);
        }
    } finally {
        setSubmitting(false);
    }
}
```

For register-then-login-manually, change the register success redirect to:

```js
navigate('/login');
```

**Expected layout and CSS:**

Both pages render inside `GuestLayout`, so the page component itself should start with:

```jsx
<div className="auth-card">
    <h1 className="auth-title">...</h1>
    <p className="auth-subtitle">...</p>
    <form className="form-stack">...</form>
</div>
```

Use these classes:

- Form wrapper: `form-stack`
- Each field: `form-group`
- Labels: `form-label`
- Inputs/selects: `form-input`, `form-select`
- Field errors: `form-error`
- Submit row: `form-actions`
- Submit button: `btn btn-primary`
- Login/register switch text: `auth-switch`, `auth-switch-link`
- Register role choices: `role-options`, `role-option`, `role-option role-option-active`

**Important layout rule:** `Login.jsx` and `Register.jsx` should not import `GuestLayout`. The router renders them as children of `GuestLayout`, and `GuestLayout` displays them through `<Outlet />`.

**Check:** A bad form submission shows Laravel `422` errors; a good submission stores a token and redirects.

#### Step 5 - Create route guards

**Goal:** Stop guests and wrong-role users from seeing protected pages.

**Why we do this:** The backend still enforces security, but the frontend should also guide users away from pages they cannot use.

**What this does:** `IndexRedirect` decides where `/` goes, `GuestOnlyRoute` blocks logged-in users from auth pages, `ProtectedRoute` blocks guests from app pages, and `RoleRoute` checks the user's role.

**Do this:** Create route guard components. You can put them in separate files under `resources/js/components/common/`, or define them inside `router.jsx` while learning.

`IndexRedirect`:

```jsx
import { Navigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function IndexRedirect() {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return <div>Loading...</div>;
    }

    return isAuthenticated
        ? <Navigate to="/dashboard" replace />
        : <Navigate to="/login" replace />;
}
```

`GuestOnlyRoute`:

```jsx
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function GuestOnlyRoute() {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return <div>Loading...</div>;
    }

    return isAuthenticated
        ? <Navigate to="/dashboard" replace />
        : <Outlet />;
}
```

`ProtectedRoute`:

```jsx
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function ProtectedRoute() {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return <div>Loading...</div>;
    }

    return isAuthenticated
        ? <Outlet />
        : <Navigate to="/login" replace />;
}
```

`RoleRoute`:

```jsx
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function RoleRoute({ allowedRoles }) {
    const { user, loading } = useAuth();

    if (loading) {
        return <div>Loading...</div>;
    }

    return user && allowedRoles.includes(user.role)
        ? <Outlet />
        : <Navigate to="/403" replace />;
}
```

Your `AuthContext` must expose `isAuthenticated`:

```js
const isAuthenticated = Boolean(user && token);
```

and include it in the provider value.

**Check:** Visiting `/dashboard` while logged out redirects to `/login`; visiting a company-only page as a student shows a 403 page or redirects.

#### Step 6 - Add React routes

**Goal:** Connect URLs like `/login` and `/dashboard` to React pages.

**Why we do this:** Laravel's `routes/web.php` only loads the React app shell. React Router decides which page component appears after the app loads.

**What this does:** `resources/js/router/index.jsx` becomes the frontend route map.

**Do this:** Use your existing `resources/js/router.jsx` file, or rename it to `resources/js/router/index.jsx` if you prefer a router folder. Add `/login`, `/register`, `/dashboard`, and one test role route like `/company/dashboard`; update `main.jsx` to render the router.

**Expected layout and CSS:**

- Routes `/login` and `/register` should be children of `GuestLayout`.
- Main app routes should be children of `DefaultLayout`.
- `DefaultLayout` should use `app-shell`, `app-sidebar`, `app-main`, `app-topbar`, and `content-shell`.
- Sidebar links should use `sidebar-link`; the active link should also include `sidebar-link-active`.

**Where layouts are imported:**

Import layouts only in the router file:

```jsx
import GuestLayout from './components/layouts/GuestLayout';
import DefaultLayout from './components/layouts/DefaultLayout';
import Login from './pages/auth/Login';
import Register from './pages/auth/Register';
```

Then nest pages under layouts:

```jsx
const router = createBrowserRouter([
    {
        path: '/',
        element: <IndexRedirect />,
    },
    {
        element: <GuestOnlyRoute />,
        children: [
            {
                element: <GuestLayout />,
                children: [
                    { path: '/login', element: <Login /> },
                    { path: '/register', element: <Register /> },
                ],
            },
        ],
    },
    {
        element: <ProtectedRoute />,
        children: [
            {
                element: <DefaultLayout />,
                children: [
                    { path: '/dashboard', element: <Dashboard /> },
                    { path: '/internships', element: <Browse /> },
                    { path: '/internships/:id', element: <Detail /> },
                    { path: '/403', element: <Forbidden /> },
                ],
            },
        ],
    },
]);
```

This means `GuestLayout` owns the auth screen frame, and `Login`/`Register` only own the form card.

The same rule applies to main pages: `Browse.jsx`, `Profile.jsx`, `Applicants.jsx`, and similar child pages should not import `DefaultLayout`. The router wraps them with `DefaultLayout`, and `DefaultLayout` renders them through `<Outlet />`.

**Layout file skeletons:**

`GuestLayout.jsx`:

```jsx
import { Outlet } from 'react-router-dom';

export default function GuestLayout() {
    return (
        <main className="auth-shell">
            <section className="auth-brand-panel">
                <div>
                    <div className="auth-brand-mark">Smart Internship</div>
                    <h1 className="auth-brand-title">Find the right internship path.</h1>
                    <p className="auth-brand-copy">
                        Students, companies, and admins work from one guided platform.
                    </p>
                </div>
                <div className="auth-brand-note">
                    Build your account first, then continue into the platform.
                </div>
            </section>

            <section className="auth-form-panel">
                <Outlet />
            </section>
        </main>
    );
}
```

`DefaultLayout.jsx`:

```jsx
import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

function navClass({ isActive }) {
    return isActive ? 'sidebar-link sidebar-link-active' : 'sidebar-link';
}

export default function DefaultLayout() {
    const { user, logout } = useAuth();

    return (
        <main className="app-shell">
            <aside className="app-sidebar">
                <div className="app-sidebar-header">
                    <div className="app-logo">Smart Internship</div>
                </div>

                <nav className="sidebar-nav">
                    <NavLink to="/dashboard" className={navClass}>Dashboard</NavLink>
                    <NavLink to="/internships" className={navClass}>Internships</NavLink>
                    <NavLink to="/student/profile" className={navClass}>Profile</NavLink>
                    <NavLink to="/student/applications" className={navClass}>Applications</NavLink>
                </nav>
            </aside>

            <section className="app-main">
                <header className="app-topbar">
                    <div className="topbar-title">
                        <h1>Smart Internship Platform</h1>
                        <p>{user?.role ?? 'Guest'}</p>
                    </div>
                    <div className="topbar-actions">
                        <span className="user-chip">{user?.name}</span>
                        <button className="btn btn-ghost" onClick={logout}>Logout</button>
                    </div>
                </header>

                <div className="content-shell">
                    <Outlet />
                </div>
            </section>
        </main>
    );
}
```

**Check:** Browser navigation works, and refreshing a React route still loads because `routes/web.php` has the fallback.

#### Step 7 - Create starter dashboard and common state pages

**Goal:** Avoid placeholder `<div>Dashboard</div>` routes and give protected navigation somewhere real to land.

**Why we do this:** After login, users need a page that proves routing, layout, auth state, and CSS are working before later feature pages exist.

**What this does:** Adds a simple dashboard plus shared 403/empty-state pages that future guards can use.

**Do this:** Create:

- `resources/js/pages/Dashboard.jsx`
- `resources/js/pages/errors/Forbidden.jsx`
- optional `resources/js/components/common/EmptyState.jsx`

**Dashboard state/data:**

Read auth state only:

```jsx
const { user } = useAuth();
```

No API call is required yet.

**Dashboard render checklist:**

- Page title showing "Dashboard"
- Subtitle mentioning the user's role
- Welcome panel
- Quick action cards:
  - Student: Browse Internships, Profile, Applications, Recommendations
  - Company: Browse Internships, My Internships, Applicants
  - Admin: Admin Dashboard, Users, Internships

**Expected layout and CSS:**

```jsx
<>
    <div className="page-header">
        <div>
            <h1 className="page-title">Dashboard</h1>
            <p className="page-subtitle">...</p>
        </div>
    </div>

    <section className="hero-panel">...</section>

    <div className="card-grid">...</div>
</>
```

`Forbidden.jsx` should use:

- `empty-state`
- `empty-state-icon`
- `empty-state-title`
- `empty-state-copy`
- `btn btn-primary` to link back to dashboard

**Router update:** Replace dashboard placeholder elements with `<Dashboard />` and add `/403` with `<Forbidden />`.

**Check:** After login, `/dashboard` shows the styled page; wrong-role users can be sent to `/403`.

### Exit Criteria

- Register a student from UI -> see token in `localStorage`.
- Log out -> token cleared and redirected to login.
- Visit protected route without login -> redirected to login.
- Log in as company -> accessing student-only route shows 403 or redirects.

---

## Slice 2 - Browse Internships (Public, Read-Only)

**Why second:** Establishes the listing pattern used everywhere. No auth required, so you can focus on the Model -> Resource -> List flow.

### Slice Contract

Build this slice to this exact shape.

**Database tables and required columns:**

`company_profiles`

- `id`
- `user_id`
- `company_name`
- `industry`
- `website`
- `description`
- timestamps

This table is introduced in Slice 2 for browsing, but the workflow rule starts earlier: when a company user registers in Slice 1, create a minimal profile row with `company_name = user.name`. Slice 2 can then add the remaining company profile fields if they do not already exist.

`skills`

- `id`
- `name`
- timestamps

`internships`

- `id`
- `company_profile_id`
- `title`
- `description`
- `requirements`
- `location`
- `type`
- `status`
- `starts_at`
- `ends_at`
- timestamps

`internship_skill`

- `id`
- `internship_id`
- `skill_id`
- unique pair on `internship_id` + `skill_id`

**Backend routes to add in `routes/api.php`:**

```php
Route::get('/internships', [InternshipController::class, 'index']);
Route::get('/internships/{internship}', [InternshipController::class, 'show']);
```

These routes live inside the existing `/api/v1` group.

**Query params accepted by `GET /api/v1/internships`:**

- `search`: filters title, description, company name, or skills.
- `type`: filters `remote`, `onsite`, or `hybrid`.
- `skill`: filters by one skill id.
- `page`: Laravel pagination page.

**List response shape:**

```json
{
  "data": [
    {
      "id": 1,
      "title": "Frontend Intern",
      "description": "...",
      "requirements": "...",
      "location": "Cairo",
      "type": "remote",
      "status": "open",
      "company": {
        "id": 1,
        "company_name": "Acme"
      },
      "skills": [
        { "id": 1, "name": "React" }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 40
  }
}
```

**Frontend files created in this slice:**

- `resources/js/api/internshipApi.js`
- `resources/js/components/internships/InternshipCard.jsx`
- `resources/js/components/internships/InternshipList.jsx`
- `resources/js/components/internships/InternshipFilters.jsx`
- `resources/js/pages/internships/Browse.jsx`
- `resources/js/pages/internships/Detail.jsx`
- `resources/js/components/common/LoadingSpinner.jsx`
- `resources/js/components/common/ErrorAlert.jsx`
- `resources/js/components/common/Pagination.jsx`

**React routes added in `router.jsx`:**

- `/internships` -> `Browse`
- `/internships/:id` -> `Detail`

Both routes are children of `DefaultLayout`. They can be public routes if you want browsing without login, but they still use the main app shell.

### Backend Walkthrough

#### Step 1 - Create internship-related tables

**Goal:** Store companies, internships, skills, and the many-to-many link between internships and skills.

**Why we do this:** Browse pages need real database rows. Internships also need related company and skill data, so the database structure comes first.

**What this does:** Migrations create `company_profiles`, `internships`, `skills`, and `internship_skill`. If you already created `company_profiles` during Slice 1, update that existing table here instead of creating a duplicate migration.

**Do this:** Create migrations for each missing table. Add foreign keys, timestamps, and indexes on commonly filtered fields like company, status, and skill name. If `company_profiles` already exists, create an `add_company_details_to_company_profiles_table` migration for `industry`, `website`, and `description`.

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

**Expected props:**

```jsx
export default function InternshipCard({ internship, showMatchScore = false }) {
    // render one card
}
```

Expected internship fields from the API:

- `internship.id`
- `internship.title`
- `internship.company.name`
- `internship.location`
- `internship.type`
- `internship.description`
- `internship.skills`
- optional later: `internship.match_score`

**Expected layout and CSS:**

```jsx
<article className="internship-card">
    <div>
        <h2 className="card-title">...</h2>
        <div className="card-meta">...</div>
        <p className="card-copy">...</p>
    </div>
    <div className="skill-list">...</div>
</article>
```

Use `skill-pill` for each skill and `btn btn-secondary` or a normal link for "View details".

**Check:** The component works with one internship object from the API response.

#### Step 3 - Create list and filter components

**Goal:** Separate list rendering from filter controls.

**Why we do this:** Keeping filtering UI separate makes the Browse page easier to read and test.

**What this does:** `InternshipList` renders cards; `InternshipFilters` manages search/skill inputs.

**Do this:** Create `InternshipList.jsx` and `InternshipFilters.jsx`.

**`InternshipList` expected props:**

```jsx
export default function InternshipList({ internships }) {
    if (internships.length === 0) {
        return <EmptyState message="No internships found." />;
    }

    return (
        <div className="card-grid">
            {internships.map((internship) => (
                <InternshipCard key={internship.id} internship={internship} />
            ))}
        </div>
    );
}
```

**`InternshipFilters` expected props:**

```jsx
export default function InternshipFilters({ filters, onChange, onSubmit }) {
    // filters.search, filters.type, filters.page
}
```

Expected filter fields:

- `search`: text input
- `type`: select with `remote`, `onsite`, `hybrid`, or empty
- `page`: usually controlled by pagination, not directly typed

**Expected layout and CSS:**

- `InternshipList` should wrap cards in `card-grid`.
- `InternshipFilters` should wrap controls in `filter-bar`.
- Search input uses `form-input`.
- Type/status/skill dropdowns use `form-select`.
- Filter button uses `btn btn-secondary`.

**Check:** Typing a search term updates the browse query state.

#### Step 4 - Create the browse page

**Goal:** Build the public `/internships` page.

**Why we do this:** This is the first real React page powered by Laravel data.

**What this does:** `Browse.jsx` fetches internships, renders filters, shows loading/error states, and displays the list.

**Do this:** Create `resources/js/pages/internships/Browse.jsx`; add `/internships` to `resources/js/router/index.jsx`.

**State to declare:**

```jsx
const [internships, setInternships] = useState([]);
const [meta, setMeta] = useState(null);
const [filters, setFilters] = useState({
    search: '',
    type: '',
    page: 1,
});
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

**Fetch function shape:**

```jsx
async function loadInternships() {
    setLoading(true);
    setError(null);

    try {
        const response = await internshipApi.fetchAll(filters);
        setInternships(response.data.data);
        setMeta(response.data.meta);
    } catch {
        setError('Could not load internships.');
    } finally {
        setLoading(false);
    }
}
```

Call `loadInternships()` in `useEffect` when `filters` changes.

**Expected layout and CSS:**

```jsx
<>
    <div className="page-header">
        <div>
            <h1 className="page-title">Browse Internships</h1>
            <p className="page-subtitle">...</p>
        </div>
    </div>

    <InternshipFilters />
    <InternshipList />
    <Pagination />
</>
```

Use `empty-state` if no internships exist and `alert alert-error` if the API fails.

**Check:** Visiting `/internships` shows seeded internships.

#### Step 5 - Create the detail page

**Goal:** Show one internship in full.

**Why we do this:** Users need a detail page before they can later apply.

**What this does:** `Detail.jsx` reads `id` from the URL and calls `fetchOne(id)`.

**Do this:** Create `resources/js/pages/internships/Detail.jsx`; add `/internships/:id` to the React router.

**State to declare:**

```jsx
const { id } = useParams();
const [internship, setInternship] = useState(null);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

**Fetch function shape:**

```jsx
async function loadInternship() {
    setLoading(true);
    setError(null);

    try {
        const response = await internshipApi.fetchOne(id);
        setInternship(response.data.data);
    } catch {
        setError('Could not load internship details.');
    } finally {
        setLoading(false);
    }
}
```

**Render checklist:**

- Title
- Company name
- Location
- Type
- Status
- Description
- Requirements
- Skills
- Apply button area that can be wired to `ApplyModal` in Slice 5

**Expected layout and CSS:**

```jsx
<div className="detail-layout">
    <main className="detail-main">
        <section className="hero-panel">...</section>
        <section className="surface">...</section>
        <section className="surface">...</section>
    </main>

    <aside className="detail-sidebar">
        <section className="surface-muted">...</section>
    </aside>
</div>
```

Use `skill-list`/`skill-pill` for skills, `match-badge` for student match score later, and `btn btn-primary` for Apply.

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

### Slice Contract

Build this slice to this exact shape.

**Backend request payload for creating an internship:**

```json
{
  "title": "Backend Intern",
  "description": "Work with Laravel APIs.",
  "requirements": "Basic PHP and SQL.",
  "location": "Cairo",
  "type": "hybrid",
  "starts_at": "2026-07-01",
  "ends_at": "2026-09-30",
  "skills": [1, 4, 7]
}
```

**Validation rules:**

- `title`: required string max 255
- `description`: required string
- `requirements`: nullable string
- `location`: required string max 255
- `type`: required enum `remote`, `onsite`, `hybrid`
- `starts_at`: nullable date
- `ends_at`: nullable date after or equal `starts_at`
- `skills`: array
- `skills.*`: exists in `skills,id`

**Backend routes to add in `routes/api.php`:**

```php
Route::middleware(['auth:sanctum', 'role:company'])->group(function () {
    Route::get('/company/internships', [InternshipController::class, 'companyIndex']);
    Route::get('/company/internships/archived', [InternshipController::class, 'archived']);
    Route::post('/internships', [InternshipController::class, 'store']);
    Route::put('/internships/{internship}', [InternshipController::class, 'update']);
    Route::patch('/internships/{internship}/archive', [InternshipController::class, 'archive']);
    Route::delete('/internships/{internship}', [InternshipController::class, 'destroy']);
});
```

**Policy rules:**

- Company users can create.
- Only the company that owns the internship can update/archive/delete it.
- Student/admin users cannot use company write routes.

**Company profile workflow:**

- Do not add a separate onboarding page in this slice.
- Company registration in Slice 1 already creates a minimal `company_profiles` row.
- `InternshipService::create()` should read `$user->companyProfile`.
- If `$user->companyProfile` is missing, return a clear `422` or `409` error instead of crashing.
- A later settings/profile page can let companies edit `industry`, `website`, and `description`.

**Frontend files created in this slice:**

- `resources/js/components/internships/InternshipForm.jsx`
- `resources/js/pages/company/InternshipCreate.jsx`
- `resources/js/pages/company/InternshipEdit.jsx`
- `resources/js/pages/company/Internships.jsx`
- `resources/js/pages/company/ArchivedInternships.jsx`

**React routes added in `router.jsx`:**

- `/company/internships`
- `/company/internships/create`
- `/company/internships/:id/edit`
- `/company/internships/archived`

All four routes must be inside `ProtectedRoute`, `RoleRoute allowedRoles={['company']}`, and `DefaultLayout`.

### Backend Walkthrough

#### Step 1 - Wire company profiles to users

**Goal:** Connect company accounts to their company profile.

**Why we do this:** A user is the login identity; a company profile is the business information that owns internships.

**What this does:** `User` can access `$user->companyProfile`, and `CompanyProfile` can access its user and internships.

**Do this:** Add `companyProfile()` to `User`; rely on Slice 1 registration to create the minimal row for real company users. For seed data, make sure company seeders also create a matching company profile.

**Check:** A company user has exactly one company profile before trying to create internships. If the profile is missing, fix the registration/seeding flow rather than adding a new onboarding page.

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

**Expected props:**

```jsx
export default function InternshipForm({
    initialValues,
    onSubmit,
    submitting = false,
    errors = {},
}) {
    // form implementation
}
```

**State to declare inside the form:**

```jsx
const [form, setForm] = useState(initialValues ?? {
    title: '',
    description: '',
    location: '',
    type: 'remote',
    requirements: '',
    skills: [],
});
```

**Fields to render:**

- `title`: text input
- `description`: textarea
- `location`: text input
- `type`: select with `remote`, `onsite`, `hybrid`
- `requirements`: textarea
- `skills`: multiselect or checkboxes using skill IDs

**Submit payload:** send the `form` object to `onSubmit(form)`.

**Expected layout and CSS:**

```jsx
<form className="form-stack">
    <div className="form-grid">
        <div className="form-group">...</div>
        <div className="form-group form-wide">...</div>
    </div>
    <div className="form-actions">...</div>
</form>
```

Use `form-input`, `form-select`, `form-textarea`, `form-error`, `btn btn-primary`, and `btn btn-ghost`.

**Check:** The same component works with empty values and loaded internship values.

#### Step 3 - Create the company create page

**Goal:** Give companies a page to post internships.

**Why we do this:** This is the first frontend write workflow for internships.

**What this does:** `InternshipCreate.jsx` renders the form, calls `internshipApi.create`, and redirects after success.

**Do this:** Create `resources/js/pages/company/InternshipCreate.jsx`.

**State/functions to declare:**

```jsx
const [errors, setErrors] = useState({});
const [submitting, setSubmitting] = useState(false);

async function handleSubmit(values) {
    setSubmitting(true);
    setErrors({});

    try {
        await internshipApi.create(values);
        navigate('/company/internships');
    } catch (error) {
        if (error.response?.status === 422) {
            setErrors(error.response.data.errors);
        }
    } finally {
        setSubmitting(false);
    }
}
```

**Expected layout and CSS:**

Use the same two-column management layout as the wireframe:

```jsx
<div className="detail-layout">
    <section className="surface">
        <InternshipForm />
    </section>
    <aside className="surface-muted">...</aside>
</div>
```

The sidebar can show help text, validation reminders, or a lightweight preview. Use `section-title`, `section-copy`, and `skill-pill`.

**Check:** Creating from UI makes the internship appear in public Browse.

#### Step 4 - Create the company edit page

**Goal:** Let companies update their internships.

**Why we do this:** Real data changes after posting: title, dates, description, and skills may need edits.

**What this does:** `InternshipEdit.jsx` loads by id, fills the form, and submits updates.

**Do this:** Create `resources/js/pages/company/InternshipEdit.jsx`; route it as `/company/internships/:id/edit`.

**State/functions to declare:**

```jsx
const { id } = useParams();
const [internship, setInternship] = useState(null);
const [errors, setErrors] = useState({});
const [loading, setLoading] = useState(true);
const [submitting, setSubmitting] = useState(false);
```

First load the internship with `internshipApi.fetchOne(id)`, then pass it as `initialValues` to `InternshipForm`. On submit, call:

```js
await internshipApi.update(id, values);
```

**Expected layout and CSS:**

Use the same classes as Create:

- Page header: `page-header`, `page-title`, `page-subtitle`
- Form area: `detail-layout`, `surface`
- Help/preview sidebar: `surface-muted`
- Save button: `btn btn-primary`
- Cancel/back button: `btn btn-ghost`

**Check:** Editing updates the database and the public detail page.

#### Step 5 - Create the company internship list

**Goal:** Show a company its own internships.

**Why we do this:** Companies need a management view separate from public Browse.

**What this does:** `Internships.jsx` fetches company-owned internships and shows create/edit/archive/delete actions.

**Do this:** Create `resources/js/pages/company/Internships.jsx`.

**State/functions to declare:**

```jsx
const [internships, setInternships] = useState([]);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

Actions this page needs:

- `loadInternships()` calls `internshipApi.fetchMine()`
- `handleArchive(id)` calls `internshipApi.archive(id)` then reloads
- `handleDelete(id)` calls `internshipApi.remove(id)` then reloads

**Columns or card fields to show:**

- Title
- Status
- Type
- Created date
- Edit action
- Archive action
- Delete action

**Expected layout and CSS:**

```jsx
<div className="page-header">
    <div>
        <h1 className="page-title">My Internships</h1>
        <p className="page-subtitle">...</p>
    </div>
    <Link className="btn btn-primary">Create Internship</Link>
</div>
```

For the list, choose either:

- Table style: `table-shell`, `table-scroll`, `data-table`, `row-actions`
- Card style: `card-grid`, `internship-card`

Use `badge badge-open`, `badge badge-archived`, and action buttons `btn btn-secondary`, `btn btn-ghost`, `btn btn-danger`.

**Check:** A company sees only its own internships.

#### Step 6 - Create the archived page

**Goal:** Show archived internships separately.

**Why we do this:** Archived internships should not clutter active management, but companies may still need to review them.

**What this does:** `ArchivedInternships.jsx` calls `fetchArchived()` and renders archived results.

**Do this:** Create `resources/js/pages/company/ArchivedInternships.jsx`.

**State/functions to declare:**

```jsx
const [internships, setInternships] = useState([]);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

Call `internshipApi.fetchArchived()` on page load.

**Fields to show:**

- Title
- Type
- Archived/status date from `archived_at` if you add that column, otherwise show `updated_at`
- Link to detail or edit if you support it

**Expected layout and CSS:**

- Header: `page-header`, `page-title`, `page-subtitle`
- List/table: `table-shell`, `table-scroll`, `data-table`
- Status: `badge badge-archived`
- Empty archive: `empty-state`, `empty-state-title`, `empty-state-copy`

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

### Slice Contract

Build this slice to this exact shape.

**Database columns for `student_profiles`:**

- `id`
- `user_id`
- `university`
- `major`
- `gpa`
- `graduation_year`
- `bio`
- `cv_path`
- timestamps

**Database columns for `student_skill`:**

- `id`
- `student_profile_id`
- `skill_id`
- unique pair on `student_profile_id` + `skill_id`

**Backend request payload for profile update:**

```json
{
  "university": "Cairo University",
  "major": "Computer Science",
  "gpa": 3.4,
  "graduation_year": 2027,
  "bio": "Interested in backend and APIs."
}
```

**Backend request payload for skill sync:**

```json
{
  "skills": [1, 2, 5]
}
```

**Backend routes to add in `routes/api.php`:**

```php
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    Route::get('/student/profile', [StudentProfileController::class, 'show']);
    Route::put('/student/profile', [StudentProfileController::class, 'update']);
    Route::post('/student/profile/cv', [StudentProfileController::class, 'uploadCv']);
    Route::put('/student/skills', [StudentSkillController::class, 'sync']);
});

Route::get('/skills', [StudentSkillController::class, 'index']);
```

`GET /skills` can be public because it only returns skill names.

**Profile response shape:**

```json
{
  "data": {
    "id": 1,
    "university": "Cairo University",
    "major": "Computer Science",
    "gpa": 3.4,
    "graduation_year": 2027,
    "bio": "Interested in backend and APIs.",
    "cv_url": "http://localhost:8000/storage/cvs/file.pdf",
    "skills": [
      { "id": 1, "name": "Laravel" }
    ]
  }
}
```

**Frontend files created in this slice:**

- `resources/js/api/studentApi.js`
- `resources/js/components/student/ProfileForm.jsx`
- `resources/js/components/common/SkillSelector.jsx`
- `resources/js/components/common/FileUpload.jsx`
- `resources/js/pages/student/Profile.jsx`

**React route added in `router.jsx`:**

- `/student/profile`

This route must be inside `ProtectedRoute`, `RoleRoute allowedRoles={['student']}`, and `DefaultLayout`.

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

**Why we do this:** File uploads have several steps: validate, store, save the path, and replace the old CV file when a student uploads a newer one.

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

**Expected props:**

```jsx
export default function ProfileForm({ profile, onSubmit, errors = {}, submitting = false }) {
    // form implementation
}
```

**State to declare:**

```jsx
const [form, setForm] = useState({
    university: profile?.university ?? '',
    major: profile?.major ?? '',
    gpa: profile?.gpa ?? '',
    graduation_year: profile?.graduation_year ?? '',
    bio: profile?.bio ?? '',
});
```

**Fields to render:**

- `university`: text input
- `major`: text input
- `gpa`: number input
- `graduation_year`: number input
- `bio`: textarea

**Submit payload:** call `onSubmit(form)`.

**Expected layout and CSS:**

Use `form-stack` and `form-grid`. Each field should use:

```jsx
<div className="form-group">
    <label className="form-label">...</label>
    <input className="form-input" />
    <p className="form-error">...</p>
</div>
```

Use `form-textarea` for bio and `form-wide` for fields that should span the full width.

**Check:** Saved values remain after refresh.

#### Step 3 - Create `SkillSelector`

**Goal:** Let students choose multiple skills.

**Why we do this:** Skills are a many-to-many relationship, so the UI needs a multi-select style control.

**What this does:** The selector displays available skills and submits selected skill IDs.

**Do this:** Create `resources/js/components/common/SkillSelector.jsx`.

**Expected props:**

```jsx
export default function SkillSelector({ skills, selectedIds, onChange }) {
    // selector implementation
}
```

**Expected data:**

- `skills`: array of all available skills from `GET /api/v1/skills`
- `selectedIds`: array of selected skill IDs, like `[1, 3, 5]`

**Interaction logic:**

When a skill is clicked:

- If its id is already in `selectedIds`, remove it.
- If its id is not selected, add it.
- Call `onChange(nextSelectedIds)`.

**Expected layout and CSS:**

- Wrapper: `surface` if standalone, or plain `form-group` if inside a form.
- Selected skills: `skill-list`, `skill-pill`
- Search/select input: `form-input` or `form-select`
- Help text: `form-help`

**Check:** Adding/removing skills updates the pivot table after save.

#### Step 4 - Create `FileUpload`

**Goal:** Let students upload a CV PDF.

**Why we do this:** File upload handling is different from normal JSON forms.

**What this does:** `FileUpload` accepts a file, sends `FormData`, and optionally shows upload progress.

**Do this:** Create `resources/js/components/common/FileUpload.jsx`.

**Expected props:**

```jsx
export default function FileUpload({ onUpload, accept = 'application/pdf' }) {
    // upload implementation
}
```

**State to declare:**

```jsx
const [file, setFile] = useState(null);
const [progress, setProgress] = useState(0);
const [error, setError] = useState(null);
const [uploading, setUploading] = useState(false);
```

**Interaction logic:**

- File input stores the selected file in `file`.
- Submit/upload button calls `onUpload(file, setProgress)`.
- Show progress while uploading.

**Expected layout and CSS:**

```jsx
<div className="file-dropzone">...</div>
<div className="progress-track">
    <div className="progress-bar" style={{ width: `${progress}%` }} />
</div>
```

Use `alert alert-error` for failed uploads and `alert alert-success` after success.

**Check:** Non-PDF files show the backend validation error.

#### Step 5 - Create the profile page

**Goal:** Compose profile form, skill selector, and CV upload into one page.

**Why we do this:** The page coordinates fetching data and refreshing after saves.

**What this does:** `Profile.jsx` loads profile/skills, renders child components, and handles loading/error states.

**Do this:** Create `resources/js/pages/student/Profile.jsx`.

**State/functions to declare:**

```jsx
const [profile, setProfile] = useState(null);
const [skills, setSkills] = useState([]);
const [selectedSkillIds, setSelectedSkillIds] = useState([]);
const [loading, setLoading] = useState(true);
const [errors, setErrors] = useState({});
```

Load both profile and skills on page load:

```js
const profileResponse = await studentApi.fetchProfile();
const skillsResponse = await studentApi.fetchSkills();
```

Submit handlers:

- `handleProfileSubmit(values)` calls `studentApi.updateProfile(values)`
- `handleSkillSave()` calls `studentApi.syncSkills(selectedSkillIds)`
- `handleCvUpload(file, onUploadProgress)` calls `studentApi.uploadCv(file, onUploadProgress)`

**Expected layout and CSS:**

```jsx
<div className="detail-layout">
    <section className="surface">
        <ProfileForm />
    </section>
    <aside className="detail-sidebar">
        <section className="surface">...</section>
        <section className="surface-muted">...</section>
    </aside>
</div>
```

Use the sidebar for skill selection and CV upload. Use `page-header`, `page-title`, and `page-subtitle` above the layout.

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

### Slice Contract

Build this slice to this exact shape.

**Database columns for `applications`:**

- `id`
- `student_profile_id`
- `internship_id`
- `status`
- `match_score`
- `message`
- timestamps
- unique pair on `student_profile_id` + `internship_id`

**Status values:**

- `pending`
- `reviewed`
- `accepted`
- `rejected`

**Backend request payload for applying:**

```json
{
  "message": "I am interested in this internship because..."
}
```

`message` is optional. The student id comes from the authenticated user, not from the request body.

**Backend routes to add in `routes/api.php`:**

```php
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    Route::post('/internships/{internship}/applications', [ApplicationController::class, 'store']);
    Route::get('/student/applications', [ApplicationController::class, 'studentIndex']);
});

Route::middleware(['auth:sanctum', 'role:company'])->group(function () {
    Route::get('/company/applications', [ApplicationController::class, 'companyIndex']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);
});
```

**Application response shape:**

```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "match_score": 72,
    "message": "I am interested...",
    "created_at": "2026-04-26T12:00:00.000000Z",
    "internship": {
      "id": 1,
      "title": "Frontend Intern",
      "company": {
        "id": 1,
        "company_name": "Acme"
      }
    },
    "student": {
      "id": 1,
      "name": "Ahmed",
      "cv_url": "http://localhost:8000/storage/cvs/file.pdf"
    }
  }
}
```

For student application lists, including `student` is optional because the student is already known. For company application lists, include `student`.

**Frontend files created in this slice:**

- `resources/js/api/applicationApi.js`
- `resources/js/components/applications/ApplyModal.jsx`
- `resources/js/components/applications/ApplicationCard.jsx`
- `resources/js/pages/student/Applications.jsx`

**React route added in `router.jsx`:**

- `/student/applications`

This route must be inside `ProtectedRoute`, `RoleRoute allowedRoles={['student']}`, and `DefaultLayout`.

**Existing page updated in this slice:**

- `resources/js/pages/internships/Detail.jsx`

Add an Apply button that opens `ApplyModal` for logged-in students.

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

**Expected props:**

```jsx
export default function ApplyModal({ internship, open, onClose, onApplied }) {
    // modal implementation
}
```

**State to declare:**

```jsx
const [message, setMessage] = useState('');
const [submitting, setSubmitting] = useState(false);
const [error, setError] = useState(null);
```

**Submit payload:**

```js
await applicationApi.apply(internship.id, { message });
```

After success:

- call `onApplied()`
- call `onClose()`

**Expected layout and CSS:**

```jsx
<div className="modal-backdrop">
    <section className="modal-panel">
        <form className="form-stack">...</form>
    </section>
</div>
```

Use `form-textarea` for the optional message, `btn btn-primary` for Apply, and `btn btn-ghost` for Cancel.

**Check:** Successful apply shows confirmation and updates the UI.

#### Step 3 - Create `ApplicationCard`

**Goal:** Display one application summary.

**Why we do this:** The student applications page needs a reusable display component.

**What this does:** Shows internship title, company, status, match score, and applied date.

**Do this:** Create `resources/js/components/applications/ApplicationCard.jsx`.

**Expected props:**

```jsx
export default function ApplicationCard({ application }) {
    // render one application
}
```

Expected fields:

- `application.id`
- `application.status`
- `application.match_score`
- `application.created_at`
- `application.internship.title`
- `application.internship.company.name`

**Expected layout and CSS:**

```jsx
<article className="surface">
    <h2 className="card-title">...</h2>
    <div className="card-meta">...</div>
    <span className="badge badge-pending">...</span>
    <span className="match-badge match-high">...</span>
</article>
```

Use the correct badge class based on status: `badge-pending`, `badge-reviewed`, `badge-accepted`, or `badge-rejected`.

**Check:** It renders correctly from an `ApplicationResource` response.

#### Step 4 - Create student applications page

**Goal:** Let students track their applications.

**Why we do this:** Applying is not enough; students need status visibility.

**What this does:** `Applications.jsx` fetches the current student's applications and renders cards.

**Do this:** Create `resources/js/pages/student/Applications.jsx`.

**State/functions to declare:**

```jsx
const [applications, setApplications] = useState([]);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

On page load, call:

```js
const response = await applicationApi.fetchMine();
setApplications(response.data.data);
```

**Fields to render:**

- Internship title
- Company name
- Application status
- Match score
- Applied date
- Link to internship detail

**Expected layout and CSS:**

Use a table for dense tracking:

```jsx
<div className="table-shell">
    <div className="table-scroll">
        <table className="data-table">...</table>
    </div>
</div>
```

If you prefer cards on mobile, use `card-grid` and `ApplicationCard`. Empty list uses `empty-state`.

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

**Do this:** In the short term, catch the backend duplicate-application `422` response and show "You already applied to this internship." Later, you can add an `already_applied` boolean to the internship detail API response and use it to disable the button before submit.

**Check:** Applying twice shows "already applied" instead of a generic failure.

### Exit Criteria

- Student applies from detail page -> confirmation + redirect.
- Reapplying shows "already applied".
- Applications list shows status + match score.

---

## Slice 6 - Match Score Logic

**Why now:** Applications exist to display the score against. Also a small standalone feature that is easy to test in isolation.

### Slice Contract

Build this slice to this exact shape.

**Score formula:**

1. Required skill IDs come from `Internship->skills`.
2. Student skill IDs come from `StudentProfile->skills`.
3. Base score is `matched_required_skills / total_required_skills * 100`.
4. Add `+5` if GPA is greater than `3.5`.
5. Add `+5` if graduation year matches a future internship preference field. If you did not add that field, skip this bonus.
6. Clamp final score between `0` and `100`.
7. Return an integer.

**Backend routes to add in `routes/api.php`:**

```php
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    Route::get('/internships/{internship}/match-score', [MatchController::class, 'score']);
    Route::get('/student/recommendations', [MatchController::class, 'recommendations']);
});
```

**Single score response shape:**

```json
{
  "score": 72
}
```

**Recommendations response shape:**

```json
{
  "data": [
    {
      "id": 1,
      "title": "Frontend Intern",
      "description": "...",
      "location": "Cairo",
      "type": "remote",
      "status": "open",
      "match_score": 92,
      "company": {
        "id": 1,
        "company_name": "Acme"
      },
      "skills": [
        { "id": 1, "name": "React" }
      ]
    }
  ]
}
```

**Frontend files created in this slice:**

- `resources/js/api/matchApi.js`
- `resources/js/components/match/MatchScoreBadge.jsx`
- `resources/js/pages/student/Recommendations.jsx`

**Existing frontend file updated in this slice:**

- `resources/js/components/internships/InternshipCard.jsx`

Show `MatchScoreBadge` when `internship.match_score` exists.

**React route added in `router.jsx`:**

- `/student/recommendations`

This route must be inside `ProtectedRoute`, `RoleRoute allowedRoles={['student']}`, and `DefaultLayout`.

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

**Expected props and logic:**

```jsx
export default function MatchScoreBadge({ score }) {
    const scoreNumber = Number(score ?? 0);
    const tierClass = scoreNumber >= 80
        ? 'match-high'
        : scoreNumber >= 50
            ? 'match-medium'
            : 'match-low';

    return (
        <span className={`match-badge ${tierClass}`}>
            {scoreNumber}% match
        </span>
    );
}
```

**Expected layout and CSS:**

```jsx
<span className={`match-badge ${tierClass}`}>
    {score}% match
</span>
```

Use:

- `match-badge match-high` for scores 80+
- `match-badge match-medium` for scores 50-79
- `match-badge match-low` for scores below 50

**Check:** Scores like 90, 60, and 30 produce different visual tiers.

#### Step 3 - Show score on internship cards

**Goal:** Let students scan fit while browsing.

**Why we do this:** Match score is most useful when comparing many internships.

**What this does:** `InternshipCard` conditionally renders `MatchScoreBadge` for logged-in students.

**Do this:** Update `InternshipCard.jsx`; read auth state and display score when the internship object includes `match_score`.

**Implementation logic:**

```jsx
const { user } = useAuth();
const shouldShowScore = user?.role === 'student' && internship.match_score !== undefined;
```

Then render:

```jsx
{shouldShowScore && <MatchScoreBadge score={internship.match_score} />}
```

Do not fetch match score inside every card unless the backend does not include it. Prefer the recommendations endpoint returning internships with `match_score`, because fetching per card can create many API calls.

**Expected layout and CSS:**

Place `MatchScoreBadge` near the card meta or footer:

```jsx
<div className="card-meta">
    <span>...</span>
    <MatchScoreBadge score={score} />
</div>
```

Keep skills in `skill-list` and `skill-pill` so the card stays consistent with Browse.

**Check:** Guests and companies do not see student match badges.

#### Step 4 - Create recommendations page

**Goal:** Show the best matches first.

**Why we do this:** Recommendations are a student-focused shortcut through the browse list.

**What this does:** `Recommendations.jsx` fetches scored internships and displays them descending by score.

**Do this:** Create `resources/js/pages/student/Recommendations.jsx`.

**State/functions to declare:**

```jsx
const [recommendations, setRecommendations] = useState([]);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

On page load:

```js
const response = await matchApi.fetchRecommendations();
setRecommendations(response.data.data);
```

Expected recommendation row/card fields:

- Internship fields from `InternshipResource`
- `match_score`

**Expected layout and CSS:**

```jsx
<section className="hero-panel">...</section>
<div className="card-grid">
    <InternshipCard />
</div>
```

Use `page-header` above the hero, `match-badge` on each card, and `empty-state` if no recommendations exist.

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

### Slice Contract

Build this slice to this exact shape.

**Backend request payload for status update:**

```json
{
  "status": "reviewed"
}
```

Accepted status values:

- `pending`
- `reviewed`
- `accepted`
- `rejected`

**Backend route to add in `routes/api.php`:**

```php
Route::middleware(['auth:sanctum', 'role:company'])->group(function () {
    Route::patch('/company/applications/{application}/status', [ApplicationController::class, 'updateStatus']);
});
```

**Policy rule:**

The authenticated company can update the application only if:

```text
application.internship.company_profile_id === auth user company profile id
```

**Frontend files created in this slice:**

- `resources/js/pages/company/Applicants.jsx`
- `resources/js/components/applications/ApplicantRow.jsx`

**Existing frontend file updated in this slice:**

- `resources/js/api/applicationApi.js`

Add `fetchForCompany()` and `updateStatus(applicationId, status)`.

**React route added in `router.jsx`:**

- `/company/applicants`

This route must be inside `ProtectedRoute`, `RoleRoute allowedRoles={['company']}`, and `DefaultLayout`.

**Applicants table columns:**

- Student name
- Internship title
- Match score
- Status dropdown
- CV download

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

**State/functions to declare:**

```jsx
const [applications, setApplications] = useState([]);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
const [updatingId, setUpdatingId] = useState(null);
```

Load applicants:

```js
const response = await applicationApi.fetchForCompany();
setApplications(response.data.data);
```

Update status:

```jsx
async function handleStatusChange(applicationId, status) {
    setUpdatingId(applicationId);

    try {
        const response = await applicationApi.updateStatus(applicationId, status);
        setApplications((items) => items.map((item) => (
            item.id === applicationId ? response.data.data : item
        )));
    } finally {
        setUpdatingId(null);
    }
}
```

Expected columns:

- Student name
- Internship title
- Match score
- Current status
- Status dropdown
- CV download link

**Expected layout and CSS:**

```jsx
<div className="page-header">...</div>
<div className="filter-bar">...</div>
<div className="table-shell">
    <div className="table-scroll">
        <table className="data-table">...</table>
    </div>
</div>
```

Filters use `form-input` and `form-select`. Empty applicant list uses `empty-state`.

**Check:** A company sees applications only for its own internships.

#### Step 3 - Create `ApplicantRow`

**Goal:** Display one applicant with review controls.

**Why we do this:** A row component keeps the table/list readable.

**What this does:** Shows student info, internship title, score, status, and a status dropdown.

**Do this:** Create `resources/js/components/applications/ApplicantRow.jsx`.

**Expected props:**

```jsx
export default function ApplicantRow({ application, updating, onStatusChange }) {
    // render one table row
}
```

Expected application fields:

- `application.id`
- `application.student.name`
- `application.student.cv_url`
- `application.internship.title`
- `application.match_score`
- `application.status`

Status dropdown:

```jsx
<select
    className="form-select"
    value={application.status}
    disabled={updating}
    onChange={(event) => onStatusChange(application.id, event.target.value)}
>
    <option value="pending">Pending</option>
    <option value="reviewed">Reviewed</option>
    <option value="accepted">Accepted</option>
    <option value="rejected">Rejected</option>
</select>
```

**Expected layout and CSS:**

Inside each `<tr>`, use:

- Applicant/student info cell: `avatar`, `card-title` or plain text.
- Status dropdown: `form-select`.
- CV action: `btn btn-secondary`.
- Save/status action, if you decide not to auto-save dropdown changes: `btn btn-primary`.

For status display, use the same `badge badge-pending|badge-reviewed|badge-accepted|badge-rejected` classes.

**Check:** Changing the dropdown calls `updateStatus`.

#### Step 4 - Add CV download link

**Goal:** Let companies view applicant CVs.

**Why we do this:** CV review is a normal part of application management.

**What this does:** Uses a CV URL/path from `ApplicationResource` and hides the link when no CV exists.

**Do this:** Add `student.cv_url` to `ApplicationResource`, then render the link in `ApplicantRow`.

**Expected layout and CSS:**

Use:

```jsx
<a className="btn btn-secondary" href={cvUrl}>Download CV</a>
```

If there is no CV, render muted text with `form-help` or disable a `btn btn-ghost`.

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

### Slice Contract

Build this slice to this exact shape.

**Admin seed user:**

- `name`: `Admin`
- `email`: `admin@example.com`
- `password`: choose a local dev password and document it in README
- `role`: `admin`

**Backend routes to add in `routes/api.php`:**

```php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
});
```

**Dashboard response shape:**

```json
{
  "stats": {
    "total_users": 20,
    "total_students": 12,
    "total_companies": 7,
    "active_internships": 15,
    "total_applications": 40
  },
  "internships": [
    {
      "id": 1,
      "title": "Frontend Intern",
      "status": "open",
      "company": {
        "company_name": "Acme"
      }
    }
  ]
}
```

**Users response shape:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Ahmed",
      "email": "ahmed@example.com",
      "role": "student",
      "created_at": "2026-04-26T12:00:00.000000Z"
    }
  ]
}
```

**Frontend files created in this slice:**

- `resources/js/api/adminApi.js`
- `resources/js/components/admin/StatsCards.jsx`
- `resources/js/components/admin/UsersTable.jsx`
- `resources/js/components/admin/InternshipsTable.jsx`
- `resources/js/pages/admin/Dashboard.jsx`

**React route added in `router.jsx`:**

- `/admin/dashboard`

This route must be inside `ProtectedRoute`, `RoleRoute allowedRoles={['admin']}`, and `DefaultLayout`.

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

**Expected props:**

```jsx
export default function StatsCards({ stats }) {
    // render dashboard metrics
}
```

Expected `stats` fields:

- `stats.total_users`
- `stats.total_students`
- `stats.total_companies`
- `stats.active_internships`
- `stats.total_applications`

Render these as an array so the markup is not repeated:

```jsx
const cards = [
    { label: 'Users', value: stats.total_users },
    { label: 'Students', value: stats.total_students },
    { label: 'Companies', value: stats.total_companies },
    { label: 'Active Internships', value: stats.active_internships },
];
```

**Expected layout and CSS:**

```jsx
<div className="grid-stats">
    <article className="stat-card">
        <p className="stat-label">...</p>
        <p className="stat-value">...</p>
        <p className="stat-note">...</p>
    </article>
</div>
```

**Check:** Cards render correctly from the dashboard payload.

#### Step 3 - Create users table

**Goal:** Let admins inspect and remove users.

**Why we do this:** User management is a core admin workflow.

**What this does:** `UsersTable` displays user rows and delete actions with confirmation.

**Do this:** Create `resources/js/components/admin/UsersTable.jsx`.

**Expected props:**

```jsx
export default function UsersTable({ users, onDelete, deletingId }) {
    // render user table
}
```

Expected user fields:

- `user.id`
- `user.name`
- `user.email`
- `user.role`
- `user.created_at`

Delete button:

```jsx
<button
    className="btn btn-danger"
    disabled={deletingId === user.id}
    onClick={() => onDelete(user.id)}
>
    Delete
</button>
```

**Expected layout and CSS:**

Use `table-shell`, `table-scroll`, and `data-table`. For actions:

- Delete: `btn btn-danger`
- View/secondary action: `btn btn-secondary`
- Action cell wrapper: `row-actions`

**Check:** Deleting refreshes the list or removes the row from state.

#### Step 4 - Create internships table

**Goal:** Show important internship records to admins.

**Why we do this:** Admins need visibility into platform content.

**What this does:** `InternshipsTable` displays active/recent internships with company and status.

**Do this:** Create `resources/js/components/admin/InternshipsTable.jsx`.

**Expected props:**

```jsx
export default function InternshipsTable({ internships }) {
    // render internship table
}
```

Expected internship fields:

- `internship.id`
- `internship.title`
- `internship.company.name`
- `internship.status`
- `internship.created_at`

**Expected layout and CSS:**

Use the same table classes:

- Wrapper: `table-shell`
- Scroll wrapper: `table-scroll`
- Table: `data-table`
- Status: `badge badge-open` or `badge badge-archived`

**Check:** Table does not overflow on smaller screens.

#### Step 5 - Create admin dashboard page

**Goal:** Compose admin cards and tables into one page.

**Why we do this:** The page coordinates fetching dashboard data and handling loading/error states.

**What this does:** `Dashboard.jsx` calls `adminApi`, renders stats and tables, and handles empty/error states.

**Do this:** Create `resources/js/pages/admin/Dashboard.jsx`.

**State/functions to declare:**

```jsx
const [stats, setStats] = useState(null);
const [users, setUsers] = useState([]);
const [internships, setInternships] = useState([]);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
const [deletingId, setDeletingId] = useState(null);
```

On page load:

```js
const dashboardResponse = await adminApi.fetchDashboard();
const usersResponse = await adminApi.fetchUsers();

setStats(dashboardResponse.data.stats);
setInternships(dashboardResponse.data.internships);
setUsers(usersResponse.data.data);
```

Delete user:

```jsx
async function handleDeleteUser(userId) {
    if (!confirm('Delete this user?')) {
        return;
    }

    setDeletingId(userId);

    try {
        await adminApi.deleteUser(userId);
        setUsers((items) => items.filter((user) => user.id !== userId));
    } finally {
        setDeletingId(null);
    }
}
```

**Expected layout and CSS:**

```jsx
<div className="page-header">...</div>
<StatsCards stats={stats} />
<div className="grid gap-5 xl:grid-cols-2">
    <UsersTable users={users} />
    <InternshipsTable internships={internships} />
</div>
```

Use `alert alert-error` for failed API calls and `empty-state` for missing table data.

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

### Slice Contract

This slice does not add new product features. It makes the existing slices reliable, readable, and submissible.

**Backend must finish with:**

- Passing `php artisan test`
- Feature tests for auth, internships, applications, match score, and admin
- No unprotected write routes
- No raw model responses for user-facing API data
- No N+1 relationship loading in list endpoints

**Frontend must finish with:**

- `npm run build` passes
- Every API page has loading, error, empty, and success states where relevant
- Every form displays Laravel `422` field errors
- Every protected page is guarded in React Router
- Every protected API route is guarded in Laravel
- Layout works on mobile, tablet, and desktop

**Documentation must finish with:**

- README setup instructions
- `.env.example` complete
- Postman collection exported
- Local admin credentials documented for development
- Known limitations listed honestly

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

**Expected layout and CSS:**

Pay special attention to pages using:

- `app-shell` sidebar collapse behavior.
- `detail-layout` two-column behavior.
- `filter-bar` wrapping on mobile.
- `table-scroll` around every `data-table`.

**Check:** No important text or buttons overlap or overflow.

#### Step 2 - Loading/error audit

**Goal:** Make every API state visible.

**Why we do this:** Users need to know whether the app is loading, empty, failed, or done.

**What this does:** Every API page gets loading, empty, and error UI.

**Do this:** Review each page that calls an API.

**Expected layout and CSS:**

- Loading state can use a simple centered `surface` or your `LoadingSpinner`.
- Empty data uses `empty-state`, `empty-state-title`, `empty-state-copy`.
- API failure uses `alert alert-error`.
- Success messages use `alert alert-success`.

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

## Reusable CSS Class Reference

The project CSS lives in `resources/css/app.css`. You do not need to write Tailwind classes directly in every component. Each frontend task above now lists the expected layout and classes for that page. Use this section only as a quick reference when you forget what a class is for.

### Layout Classes

#### `GuestLayout`

Use for `/login` and `/register`.

Expected structure:

```jsx
<main className="auth-shell">
    <section className="auth-brand-panel">
        <div>
            <div className="auth-brand-mark">Smart Internship</div>
            <h1 className="auth-brand-title">...</h1>
            <p className="auth-brand-copy">...</p>
        </div>
        <div className="auth-brand-note">...</div>
    </section>

    <section className="auth-form-panel">
        <Outlet />
    </section>
</main>
```

Use these inside auth pages:

- `auth-card` on the form container.
- `auth-title` on the page title.
- `auth-subtitle` under the title.
- `auth-switch` and `auth-switch-link` for "already have an account?" links.

#### `DefaultLayout`

Use for the main app shell.

Expected structure:

```jsx
<main className="app-shell">
    <aside className="app-sidebar">
        <div className="app-sidebar-header">
            <div className="app-logo">Smart Internship</div>
        </div>
        <nav className="sidebar-nav">...</nav>
    </aside>

    <section className="app-main">
        <header className="app-topbar">...</header>
        <div className="content-shell">
            <Outlet />
        </div>
    </section>
</main>
```

Use these for navigation:

- `sidebar-section-label` for nav section headings.
- `sidebar-link` for normal links.
- `sidebar-link sidebar-link-active` for the current page.
- `user-chip` and `avatar` in the topbar.

### Common Component Classes

#### Page headers

```jsx
<div className="page-header">
    <div>
        <h1 className="page-title">...</h1>
        <p className="page-subtitle">...</p>
    </div>
    <button className="btn btn-primary">...</button>
</div>
```

#### Panels and cards

- `hero-panel` for a large intro/summary area.
- `surface` for normal sections.
- `surface-muted` for secondary panels.
- `card-grid` for repeated card layouts.
- `internship-card` for internship records.
- `stat-card`, `stat-label`, `stat-value`, `stat-note` for dashboard metrics.

#### Forms

Use this shape for most forms:

```jsx
<form className="form-stack">
    <div className="form-grid">
        <div className="form-group">
            <label className="form-label">...</label>
            <input className="form-input" />
            <p className="form-error">...</p>
        </div>

        <div className="form-group form-wide">
            <label className="form-label">...</label>
            <textarea className="form-textarea" />
        </div>
    </div>

    <div className="form-actions">
        <button className="btn btn-primary">Save</button>
        <button className="btn btn-ghost">Cancel</button>
    </div>
</form>
```

Available form classes:

- `form-stack`
- `form-grid`
- `form-wide`
- `form-group`
- `form-label`
- `form-input`
- `form-select`
- `form-textarea`
- `form-help`
- `form-error`
- `form-actions`

#### Buttons

Use `btn` plus one variant:

- `btn btn-primary` for main actions.
- `btn btn-secondary` for helpful secondary actions.
- `btn btn-ghost` for quiet/cancel actions.
- `btn btn-danger` for destructive actions.

#### Badges and statuses

- `badge badge-open`
- `badge badge-pending`
- `badge badge-reviewed`
- `badge badge-accepted`
- `badge badge-rejected`
- `badge badge-archived`
- `match-badge match-high`
- `match-badge match-medium`
- `match-badge match-low`
- `skill-list` and `skill-pill` for skills.

#### Tables

```jsx
<div className="table-shell">
    <div className="table-scroll">
        <table className="data-table">...</table>
    </div>
</div>
```

Use `row-actions` inside action columns.

#### Loading, empty, and alerts

- `empty-state`, `empty-state-icon`, `empty-state-title`, `empty-state-copy`
- `alert alert-error`
- `alert alert-success`
- `alert alert-info`

#### Pagination and uploads

- `pagination`
- `pagination-button`
- `pagination-button pagination-button-active`
- `file-dropzone`
- `progress-track`
- `progress-bar`

#### Modals

Use for apply modal or confirmation dialogs:

```jsx
<div className="modal-backdrop">
    <div className="modal-panel">...</div>
</div>
```

## Code Contracts And Starter Shapes

Use this section when a walkthrough step says "create X" and you need the expected exports, props, methods, and state shape. These are not meant to be perfect final code; they are the minimum shape you can build from.

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
        setErrors(error.response.data.errors);
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
