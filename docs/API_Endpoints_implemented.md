# API endpoints implemented 

This project includes **Authentication**, **User Management**, **Project Management**, **Task Management**, and **Comment Management**. The following endpoints are available:

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

## Project Management Endpoints

### `GET /api/projects`
Get paginated list of projects with optional filtering.

**Query Parameters:**
- `per_page` (optional): Projects per page (default: 15, max: 100)
- `page` (optional): Page number (default: 1)
- `user_id` (optional): Filter by user ID
- `status` (optional): Filter by status (pending, in_progress, completed, cancelled)

**Example:** `GET /api/projects?per_page=10&page=2&status=in_progress&user_id=1`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "projects": [
      {
        "id": 1,
        "title": "Project Title",
        "description": "Project description",
        "status": "in_progress",
        "start_date": "2025-01-01",
        "end_date": "2025-12-31",
        "user_id": 1,
        "created_at": "2025-09-16T19:30:00.000000Z",
        "updated_at": "2025-09-16T19:30:00.000000Z",
        "user": {
          "id": 1,
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 3,
      "per_page": 15,
      "total_projects": 42,
      "from": 1,
      "to": 15
    }
  }
}
```

### `POST /api/projects`
Create a new project.

**Body (JSON):**
```json
{
  "title": "New Project",
  "description": "Project description",
  "status": "pending",
  "start_date": "2025-01-01",
  "end_date": "2025-12-31",
  "user_id": 1
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "project": {
      "id": 1,
      "title": "New Project",
      "description": "Project description",
      "status": "pending",
      "start_date": "2025-01-01",
      "end_date": "2025-12-31",
      "user_id": 1,
      "created_at": "2025-09-16T19:30:00.000000Z",
      "updated_at": "2025-09-16T19:30:00.000000Z",
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      }
    },
    "message": "Project created successfully."
  }
}
```

### `GET /api/projects/{id}`
Get a specific project by ID.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "project": {
      "id": 1,
      "title": "Project Title",
      "description": "Project description",
      "status": "in_progress",
      "start_date": "2025-01-01",
      "end_date": "2025-12-31",
      "user_id": 1,
      "created_at": "2025-09-16T19:30:00.000000Z",
      "updated_at": "2025-09-16T19:30:00.000000Z",
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      }
    }
  }
}
```

### `PUT /api/projects/{id}`
Update an existing project.

**Body (JSON):**
```json
{
  "title": "Updated Project Title",
  "description": "Updated description",
  "status": "completed",
  "end_date": "2025-11-30"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "project": {
      "id": 1,
      "title": "Updated Project Title",
      "description": "Updated description",
      "status": "completed",
      "start_date": "2025-01-01",
      "end_date": "2025-11-30",
      "user_id": 1,
      "created_at": "2025-09-16T19:30:00.000000Z",
      "updated_at": "2025-09-16T20:00:00.000000Z",
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      }
    },
    "message": "Project updated successfully."
  }
}
```

### `DELETE /api/projects/{id}`
Delete a project by ID.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Project deleted successfully.",
    "deleted_project": {
      "id": 1,
      "title": "Project Title",
      "user_id": 1
    }
  }
}
```

**Error Responses for Projects:**
- `400 Bad Request` for invalid project ID
- `404 Not Found` if project doesn't exist
- `422 Unprocessable Entity` for validation errors
- `500 Server Error` for operation errors

---

## Task Management

### `GET /api/tasks`
List all tasks with optional filtering, search, and pagination.

**Query Parameters:**
- `status` (optional): Filter by status (`todo`, `in-progress`, `done`)
- `due_before` (optional): Filter tasks due before a date (YYYY-MM-DD)
- `due_after` (optional): Filter tasks due after a date (YYYY-MM-DD)
- `search` (optional): Full-text search in title and description
- `page` (optional): Page number for pagination (default: 1)
- `per_page` (optional): Items per page (default: 15, max: 100)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "title": "Complete documentation",
        "description": "Update API documentation with new endpoints",
        "status": "in-progress",
        "due_date": "2025-01-20",
        "created_at": "2025-01-15T10:00:00.000000Z",
        "updated_at": "2025-01-15T14:30:00.000000Z"
      },
      {
        "id": 2,
        "title": "Fix authentication bug",
        "description": "Resolve login timeout issue",
        "status": "todo",
        "due_date": null,
        "created_at": "2025-01-15T09:00:00.000000Z",
        "updated_at": "2025-01-15T09:00:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost/api/tasks?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost/api/tasks?page=1",
    "links": [...],
    "next_page_url": null,
    "path": "http://localhost/api/tasks",
    "per_page": 15,
    "prev_page_url": null,
    "to": 2,
    "total": 2
  }
}
```

### `POST /api/tasks`
Create a new task.

**Request Body:**
```json
{
  "title": "New task",
  "description": "Task description",
  "status": "todo",
  "due_date": "2025-01-25"
}
```

**Validation Rules:**
- `title`: required, string, max:255
- `description`: optional, string
- `status`: optional, enum (`todo`, `in-progress`, `done`), default: `todo`
- `due_date`: optional, date format (YYYY-MM-DD)

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "title": "New task",
    "description": "Task description",
    "status": "todo",
    "due_date": "2025-01-25",
    "created_at": "2025-01-15T15:00:00.000000Z",
    "updated_at": "2025-01-15T15:00:00.000000Z"
  }
}
```

### `GET /api/tasks/{id}`
Get a specific task by ID.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Complete documentation",
    "description": "Update API documentation with new endpoints",
    "status": "in-progress",
    "due_date": "2025-01-20",
    "created_at": "2025-01-15T10:00:00.000000Z",
    "updated_at": "2025-01-15T14:30:00.000000Z"
  }
}
```

### `PUT /api/tasks/{id}`
Update a task by ID.

**Request Body:**
```json
{
  "title": "Updated task title",
  "description": "Updated description",
  "status": "done",
  "due_date": "2025-01-22"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Updated task title",
    "description": "Updated description",
    "status": "done",
    "due_date": "2025-01-22",
    "created_at": "2025-01-15T10:00:00.000000Z",
    "updated_at": "2025-01-15T16:00:00.000000Z"
  }
}
```

### `DELETE /api/tasks/{id}`
Delete a task by ID.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Task deleted successfully.",
    "deleted_task": {
      "id": 1,
      "title": "Updated task title",
      "status": "done"
    }
  }
}
```

**Error Responses for Tasks:**
- `400 Bad Request` for invalid task ID
- `404 Not Found` if task doesn't exist
- `422 Unprocessable Entity` for validation errors
- `500 Server Error` for operation errors

---

## Comment Management

### `GET /api/tasks/{taskId}/comments`
List all comments for a specific task with pagination.

**Query Parameters:**
- `page` (optional): Page number for pagination (default: 1)
- `per_page` (optional): Items per page (default: 15, max: 100)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "task_id": 1,
        "content": "This is a comment on the task",
        "created_at": "2025-01-15T10:00:00.000000Z",
        "updated_at": "2025-01-15T10:00:00.000000Z"
      },
      {
        "id": 2,
        "task_id": 1,
        "content": "Another comment",
        "created_at": "2025-01-15T09:00:00.000000Z",
        "updated_at": "2025-01-15T09:00:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost/api/tasks/1/comments?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost/api/tasks/1/comments?page=1",
    "links": [...],
    "next_page_url": null,
    "path": "http://localhost/api/tasks/1/comments",
    "per_page": 15,
    "prev_page_url": null,
    "to": 2,
    "total": 2
  }
}
```

### `POST /api/tasks/{taskId}/comments`
Create a new comment for a specific task.

**Request Body:**
```json
{
  "content": "This is a new comment"
}
```

**Validation Rules:**
- `content`: required, string

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "task_id": 1,
    "content": "This is a new comment",
    "created_at": "2025-01-15T15:00:00.000000Z",
    "updated_at": "2025-01-15T15:00:00.000000Z"
  }
}
```

### `GET /api/tasks/{taskId}/comments/{commentId}`
Get a specific comment by ID for a specific task.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "task_id": 1,
    "content": "This is a comment on the task",
    "created_at": "2025-01-15T10:00:00.000000Z",
    "updated_at": "2025-01-15T10:00:00.000000Z"
  }
}
```

### `PUT /api/tasks/{taskId}/comments/{commentId}`
Update a comment by ID for a specific task.

**Request Body:**
```json
{
  "content": "Updated comment content"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "task_id": 1,
    "content": "Updated comment content",
    "created_at": "2025-01-15T10:00:00.000000Z",
    "updated_at": "2025-01-15T16:00:00.000000Z"
  }
}
```

### `DELETE /api/tasks/{taskId}/comments/{commentId}`
Delete a comment by ID for a specific task.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Comment deleted successfully.",
    "deleted_comment": {
      "id": 1,
      "content": "This is a comment on the task...",
      "task_id": 1
    }
  }
}
```

**Error Responses for Comments:**
- `400 Bad Request` for invalid task or comment ID
- `404 Not Found` if task or comment doesn't exist
- `422 Unprocessable Entity` for validation errors
- `500 Server Error` for operation errors

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

### Project Management
```bash
# Get projects list
curl -X GET http://localhost/api/projects \
  -H "Accept: application/json"

# Get projects with filtering
curl -X GET "http://localhost/api/projects?per_page=10&status=in_progress&user_id=1" \
  -H "Accept: application/json"

# Create project
curl -X POST http://localhost/api/projects \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"title":"New Project","description":"Description","user_id":1,"status":"pending"}'

# Get specific project
curl -X GET http://localhost/api/projects/1 \
  -H "Accept: application/json"

# Update project
curl -X PUT http://localhost/api/projects/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"title":"Updated Title","status":"completed"}'

# Delete project
curl -X DELETE http://localhost/api/projects/1 \
  -H "Accept: application/json"
```

### Tasks
```bash
# Get all tasks
curl -X GET http://localhost/api/tasks \
  -H "Accept: application/json"

# Get tasks with filtering
curl -X GET "http://localhost/api/tasks?status=in-progress&due_before=2025-01-20&search=documentation" \
  -H "Accept: application/json"

# Get tasks with pagination
curl -X GET "http://localhost/api/tasks?page=2&per_page=10" \
  -H "Accept: application/json"

# Create task
curl -X POST http://localhost/api/tasks \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"title":"New task","description":"Task description","status":"todo","due_date":"2025-01-25"}'

# Get specific task
curl -X GET http://localhost/api/tasks/1 \
  -H "Accept: application/json"

# Update task
curl -X PUT http://localhost/api/tasks/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"title":"Updated task","status":"done","due_date":"2025-01-22"}'

# Delete task
curl -X DELETE http://localhost/api/tasks/1 \
  -H "Accept: application/json"
```

### Comments
```bash
# Get all comments for a task
curl -X GET http://localhost/api/tasks/1/comments \
  -H "Accept: application/json"

# Get comments with pagination
curl -X GET "http://localhost/api/tasks/1/comments?page=2&per_page=10" \
  -H "Accept: application/json"

# Create comment for a task
curl -X POST http://localhost/api/tasks/1/comments \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"content":"This is a new comment"}'

# Get specific comment
curl -X GET http://localhost/api/tasks/1/comments/1 \
  -H "Accept: application/json"

# Update comment
curl -X PUT http://localhost/api/tasks/1/comments/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"content":"Updated comment content"}'

# Delete comment
curl -X DELETE http://localhost/api/tasks/1/comments/1 \
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

# Project management tests only
docker compose exec laravel.test php artisan test tests/Feature/ProjectsTest.php

# Task management tests only
docker compose exec laravel.test php artisan test tests/Feature/TasksTest.php

# Comment management tests only
docker compose exec laravel.test php artisan test tests/Feature/CommentsTest.php

# All Auth-related tests
docker compose exec laravel.test php artisan test tests/Feature/Auth/
```

### Test Coverage
- **LoginTest.php**: Login success, invalid credentials, rate limiting
- **RegisterTest.php**: Registration success, validation errors, duplicate email, rate limiting
- **UsersTest.php**: List users with pagination, delete users, validation, error handling
- **ProjectsTest.php**: Complete CRUD operations, filtering, validation, error handling, relationships
- **TasksTest.php**: Complete CRUD operations, filtering by status and due date, full-text search, pagination, validation, error handling
- **CommentsTest.php**: Complete CRUD operations for comments nested under tasks, task relationship validation, pagination, error handling, cascade deletion

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