# Task Management API — Quickstart & Usage
A minimal guide to run, test, and use the **Collaborative Task Management API**.

---

## 1) What you get
- REST API for **Projects, Tasks, Comments, Notifications**
- Layered architecture: **Controller → Service → Repository → Model**
- Standard JSON errors, pagination, filtering, rate‑limits on sensitive routes
- CI: tests, static analysis (PHPStan), CS (PHP‑CS‑Fixer), dependency review, CodeQL
- Dockerized local setup

---

## 2) Prerequisites
- **Docker** + **Docker Compose**
- **Git**

---

## 3) Setup (Docker)
```bash
git clone https://github.com/ewmateam/task-api-template.git
cd task-api-template
cp .env.example .env

# Install deps without local PHP
docker run --rm -v %cd%:/app -w /app composer:2 install --ignore-platform-reqs

# Build & run
docker compose build laravel.test
docker compose up -d

# First boot inside container
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
```
Open **http://localhost**.

---

## 4) Endpoints
Import the Postman collection Task API Template.postman_collection.json and environment Task API Template Environment.postman_environment.json into Postman.
Select the imported environment, then run the collection to test the API (against http://localhost).

### Auth
- `POST /api/register` — create user
- `POST /api/login` — login (422 on invalid)

### Users
- `GET /api/users?per_page=&page=` — paginated list
- `DELETE /api/users/{id}` — delete

### Projects
- `GET /api/projects?status=&user_id=&per_page=&page=`
- `POST /api/projects`
- `GET /api/projects/{id}`
- `PUT /api/projects/{id}`
- `DELETE /api/projects/{id}`

### Tasks
- `GET /api/tasks?status=&due_before=&due_after=&search=&per_page=&page=`
- `POST /api/tasks`
- `GET /api/tasks/{id}`
- `PUT /api/tasks/{id}`
- `DELETE /api/tasks/{id}`

### Comments (nested under task)
- `GET /api/tasks/{taskId}/comments?per_page=&page=`
- `POST /api/tasks/{taskId}/comments`
- `GET /api/tasks/{taskId}/comments/{commentId}`
- `PUT /api/tasks/{taskId}/comments/{commentId}`
- `DELETE /api/tasks/{taskId}/comments/{commentId}`

### Notifications
- `GET /api/users/{userId}/notifications?unread_only=&per_page=&page=`
- `GET /api/users/{userId}/notifications/unread-count`
- `PATCH /api/users/{userId}/notifications/{id}/read`
- `PATCH /api/users/{userId}/notifications/mark-all-read`
- `DELETE /api/users/{userId}/notifications/{id}`

---

## 5) Testing
```bash
# All tests
docker compose exec laravel.test php artisan test

# Coverage
docker compose exec -e XDEBUG_MODE=coverage laravel.test php artisan test --coverage
```
Useful subsets:
```
tests/Feature/Auth/*.php
tests/Feature/ProjectsTest.php
tests/Feature/TasksTest.php
tests/Feature/CommentsTest.php
tests/Feature/NotificationsTest.php
```

---

## 6) Architecture & Patterns
- **Services** encapsulate business logic (e.g., TaskService)
- **Repositories** abstract data access (e.g., TaskRepositoryInterface → TaskRepository)
- **Observer/Event**: `TaskUpdated` → `SendTaskNotification` (queued)
- **Consistent error JSON** via custom exception handler
- **Caching**: task lists (Redis) with simple invalidation

---

## 7) CI/CD (GitHub Actions)
- Run tests on PHP 8.2
- PHPStan + PHP‑CS‑Fixer
- Composer audit + Dependency Review
- CodeQL security analysis
- Dockerized dev workflow

---

## 8) Troubleshooting
- **HTML errors** → add `Accept: application/json`
- **DB issues** → check `.env`, run `php artisan migrate`
- **Config not applied** → `php artisan config:clear`
- **Redis port busy** → remove host mapping or change port

---

# Testing & Quality Checks
This project include:
- Unit tests for core services and repositories.
- Integration tests for API endpoints.
- Static analysis (PHPStan/Larastan) to catch potential issues.
to achieved 71.6% test coverage.

![1_-ezjy2FiN-II2pVNYaR32A](https://github.com/user-attachments/assets/aad99bc4-e9b2-4bdf-a4c2-1b79e4c7080d)
