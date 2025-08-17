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
