# Senior PHP Backend Developer – Coding Assessment

Thank you for taking the time to complete this assessment.  
The goal is to demonstrate how you approach architecture, testing, and backend engineering best practices.

---

## 📖 Project: Collaborative Task Management API

### Objective
Build a REST API for managing projects, tasks, comments, and notifications.  
We value **clean architecture, thoughtful design, and code quality** over speed or feature quantity.

---

## ✅ Requirements

### Core Features
- **Authentication**: User registration & login (JWT or Laravel Sanctum).
- **Projects**: CRUD operations. Each project belongs to a user.
- **Tasks**:
  - CRUD operations.
  - Fields: `title, description, status (todo/in-progress/done), due_date`.
  - Filtering: by status, due date, full-text search.
  - Pagination for listing.
- **Comments**: CRUD operations. Each comment belongs to a task.
- **Notifications**:
  - Triggered when a task is assigned or updated.
  - Delivered asynchronously (e.g., queue).
  - Endpoint for fetching unseen notifications.

### Non-Functional
- Use a layered architecture (controllers, services, repositories, domain models).
- Apply at least two meaningful design patterns (e.g., Repository, Strategy, Observer).
- Database migrations must be included.
- Cache task listings (e.g., Redis).
- Add rate limiting for sensitive endpoints.
- Standardized error handling and responses.

### Testing
- Unit tests for core services and repositories.
- Integration tests for API endpoints.
- Minimum **70% test coverage**.

### DevOps
- `Dockerfile` + `docker-compose.yml` for local setup.
- CI pipeline runs automatically (tests, static analysis, linting, security).
- Compatible with **PHP 8.2+**.

### Documentation
- Update this `README.md` to include:
  - Setup instructions.
  - Example API requests (curl/Postman).
  - Explanation of your architectural decisions and trade-offs.
  - Which design patterns you applied, and why.

---

## 🎯 Acceptance Criteria

Your submission will be evaluated on:

- **Architecture & Patterns**: Separation of concerns, justified design patterns.
- **Code Quality & Standards**: PSR-12 compliance, maintainability.
- **Feature Completeness**: Requirements implemented.
- **Testing**: Coverage, meaningful cases, edge-case handling.
- **Documentation**: Clear and professional.
- **DevOps**: CI/CD awareness, Docker setup.

---

## 📝 Commit Guidelines

We value not only the final code but also how you structure your work.  
Please use **meaningful, structured commit messages** throughout your development.  

- Follow [Conventional Commits](https://www.conventionalcommits.org/) style when possible:  
  - `feat:` – for new features  
  - `fix:` – for bug fixes  
  - `chore:` – for setup, configuration, or maintenance  
  - `test:` – for adding or improving tests  
  - `docs:` – for documentation changes  

- Examples:  
  - `chore: initial commit (Laravel project setup)`  
  - `feat: add task CRUD endpoints`  
  - `fix: correct due date validation logic`  

Your commit history will be reviewed as part of the assessment to understand how you approach iteration, problem-solving, and communication through code.


## 📦 Submission Instructions
1. Implement your solution inside this repo.
2. Push to a private GitHub repository.
3. Invite the following reviewers with **Read**  role: `gh-ewmateam`.
4. Please complete within 7 days of receiving the assignment.
5. If you need more time, let us know.

## ℹ️ Notes
1. The project is designed to take 3–5 hours. We do not expect a production-ready system.
2. Quality matters more than quantity — partial solutions are acceptable if well-documented.
3. Document anything you would do differently with more time.

Good luck, and thank you again for your effort!


---
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
