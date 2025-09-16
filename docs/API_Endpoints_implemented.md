# API endpoints implemented 

This project includes **Authentication** and **User Management**. The following endpoints are available:

## Authentication Endpoints

### `POST /api/login`
**Body (JSON):**
```json
{
  "email": "example@test.com",
  "password": "secret"
}
```
**Responses:**
- `200 OK` on success — `{ success: true, data: { user, token? } }`
- `422 Unprocessable Entity` for invalid credentials (by design in this project)
- `429 Too Many Requests` if throttled (route uses `throttle:10,1`)

### `POST /api/register`
**Body (JSON):**
```json
{
  "name": "Demo",
  "email": "demo@example.com",
  "password": "secret123"
}
```
**Responses:**
- `201 Created` on success — `{ success: true, data: { user, token? } }`
- `422 Unprocessable Entity` for validation errors
- `429 Too Many Requests` if throttled (route uses `throttle:10,1`)

> **Sanctum tokens:** If `laravel/sanctum` is installed, responses include a `token` field. Otherwise, it will be `null`.

## User Management Endpoints

### `GET /api/users`
Get paginated list of users.

**Query Parameters:**
- `per_page` (optional): Users per page (default: 15, max: 100)
- `page` (optional): Page number (default: 1)

**Example:** `GET /api/users?per_page=10&page=2`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "password": "$2y$12$...", 
        "created_at": "2025-09-16T19:30:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 3,
      "per_page": 15,
      "total_users": 42,
      "from": 1,
      "to": 15
    }
  }
}
```

### `DELETE /api/users/{id}`
Delete a user by ID.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "User deleted successfully.",
    "deleted_user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  }
}
```

**Error Responses:**
- `400 Bad Request` for invalid user ID
- `404 Not Found` if user doesn't exist
- `500 Server Error` for deletion errors

---

## 1) Quick cURL examples

### Authentication
```bash
# Login (success)
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"example@test.com","password":"secret"}'

# Login (invalid -> 422)
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"nope@test.com","password":"wrong"}'

# Register (201)
curl -X POST http://localhost/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Demo","email":"demo@example.com","password":"secret123"}'
```

### User Management
```bash
# Get users list
curl -X GET http://localhost/api/users \
  -H "Accept: application/json"

# Get users with pagination
curl -X GET "http://localhost/api/users?per_page=5&page=2" \
  -H "Accept: application/json"

# Delete user
curl -X DELETE http://localhost/api/users/1 \
  -H "Accept: application/json"
```

---

## 2) Testing
Run tests inside the container:
```powershell
# All tests
docker compose exec laravel.test php artisan test

# Authentication tests only
docker compose exec laravel.test php artisan test tests/Feature/Auth/LoginTest.php
docker compose exec laravel.test php artisan test tests/Feature/Auth/RegisterTest.php

# User management tests only
docker compose exec laravel.test php artisan test tests/Feature/Auth/UsersTest.php

# All Auth-related tests
docker compose exec laravel.test php artisan test tests/Feature/Auth/
```

### Test Coverage
- **LoginTest.php**: Login success, invalid credentials, rate limiting
- **RegisterTest.php**: Registration success, validation errors, duplicate email, rate limiting
- **UsersTest.php**: List users with pagination, delete users, validation, error handling

With coverage (needs Xdebug enabled in the PHP container):
```powershell
docker compose exec -e XDEBUG_MODE=coverage laravel.test php artisan test --coverage
```

**Testing env** (recommended `.env.testing`):
```env
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=testing
DB_USERNAME=sail
DB_PASSWORD=password
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
REDIS_HOST=redis
```

> Tests use Pest for feature tests (PHPUnit-compatible). We also provide unit tests for Services/Repositories as we progress.

---

## 3) Postman (optional)
Quickly exercise the API with Postman:
* Import the provided postman.json (File → Import).
* Set the base URL (e.g., http://localhost; update if you use a different port).
* Send the requests in the collection to verify the endpoints.
* **Important**: Always set `Accept: application/json` header in Postman

---

## 4) Rate limiting
- `/api/login` → `throttle:10,1` (10 requests/minute)
- `/api/register` → `throttle:10,1` (10 requests/minute)
- `/api/users` → No rate limiting (read operation)
- `/api/users/{id}` DELETE → No rate limiting (but should consider adding in production)

If you hit `429 Too Many Requests` during manual testing, either wait a minute or clear cache:
```powershell
docker compose exec laravel.test php artisan cache:clear
```

## 5) Important Headers for API Testing

**Always include these headers when testing:**
```
Content-Type: application/json
Accept: application/json
```

**Why `Accept: application/json` is crucial:**
- Without it, Laravel might return HTML error pages instead of JSON responses
- This is especially important for validation errors and 4xx/5xx responses
- Example of what happens with `Accept: */*`: You get HTML instead of clean JSON errors

---

## 6) Troubleshooting
- **DB connection refused / sessions table missing**: Ensure `DB_HOST=mysql` in `.env`, run `php artisan migrate`, and (if `SESSION_DRIVER=database`) run `php artisan session:table`.
- **Redis port conflict**: Remove or change host port mapping for Redis; Laravel uses `REDIS_HOST=redis` internally.
- **Permissions (storage/)**: Run the permissions fix snippet shown earlier.
- **Config not updating**: `php artisan config:clear` after editing `.env`.
- **Getting HTML instead of JSON**: Make sure to include `Accept: application/json` header in your requests.
- **Validation errors not showing properly**: Check that your `Accept` header is set to `application/json`, not `*/*`.