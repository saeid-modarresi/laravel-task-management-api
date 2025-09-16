# API endpoints implemented 

This project starts with **Authentication**. The following endpoints are available early:

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

---

## 1) Quick cURL examples
```bash
# Login (success)
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"example@test.com","password":"secret"}'

# Login (invalid -> 422)
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"nope@test.com","password":"wrong"}'

# Register (201)
curl -X POST http://localhost/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Demo","email":"demo@example.com","password":"secret123"}'
```

---

## 2) Testing
Run tests inside the container:
```powershell
# all tests
docker compose exec laravel.test php artisan test

# only login tests
docker compose exec laravel.test php artisan test --filter=LoginTest

# only register tests
docker compose exec laravel.test php artisan test --filter=RegisterTest
```

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

---

## 4) Rate limiting
- `/api/login` → `throttle:10,1` (10 requests/minute)
- `/api/register` → `throttle:10,1`

If you hit `429 Too Many Requests` during manual testing, either wait a minute or clear cache:
```powershell
docker compose exec laravel.test php artisan cache:clear
```

---

## 5) Troubleshooting
- **DB connection refused / sessions table missing**: Ensure `DB_HOST=mysql` in `.env`, run `php artisan migrate`, and (if `SESSION_DRIVER=database`) run `php artisan session:table`.
- **Redis port conflict**: Remove or change host port mapping for Redis; Laravel uses `REDIS_HOST=redis` internally.
- **Permissions (storage/)**: Run the permissions fix snippet shown earlier.
- **Config not updating**: `php artisan config:clear` after editing `.env`.