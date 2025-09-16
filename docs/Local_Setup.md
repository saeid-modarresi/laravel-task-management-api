# Local Setup & Developer Guide

This document explains how to run, test, and verify the **Task Management API** locally using Docker. It complements the main `README.md` (which contains the assessment brief).

---

## Prerequisites
- **Docker Desktop** (Windows/macOS/Linux)
- **Git**
- You do **not** need local PHP/Composer/Node; everything runs in containers.

> **Windows note:** Use **PowerShell**. If a command uses `${PWD}` and it doesn’t work in your shell, replace it with `%cd%` (CMD) or `$PWD.Path` (PowerShell Core).

---

## 1) Clone & Environment
```bash
git clone https://github.com/ewmateam/task-api-template.git
cd task-api-template

# Create env file
cp .env.example .env
```

Open `.env` and make sure these are set (important parts only):
```env
# Used by Larvel Sail build (it used in docker-compose.yml)
WWWUSER=1000
WWWGROUP=1000

# Database (service names from docker-compose)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=task_api
DB_USERNAME=sail
DB_PASSWORD=password

# Redis (service name)
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
```

---

## 2) Install dependencies (without local PHP/Composer)
To install the vendor dependencies without having PHP/Composer locally, run Composer inside a temporary Docker container. The container mounts your project directory, runs composer install, writes the vendor folder on your host, and then is automatically removed (--rm). So run this command:

**CMD:**
```bat
docker run --rm -v %cd%:/app -w /app composer:2 install --ignore-platform-reqs
```

This writes `vendor/` on your host and then removes the container.

---

## 3) Build & Run containers
```powershell
# Build PHP (Sail) image (first build can take a while)
docker compose build laravel.test

# Start all services (php, mysql, redis)
docker compose up -d
```

> **If Redis port 6379 is in use on your host:**
> - Remove or comment out the `ports:` mapping for the `redis` service, **or** map to a different host port, e.g. `"6380:6379"`. Laravel talks to Redis via the internal network (`REDIS_HOST=redis`), so host mapping is optional.

---

## 4) First-time app bootstrap
Run these commands **inside** the PHP container:
```powershell
# App key
docker compose exec laravel.test php artisan key:generate

# (Optional) create tables for session/cache/queue if you use database drivers
docker compose exec laravel.test php artisan session:table
docker compose exec laravel.test php artisan cache:table
docker compose exec laravel.test php artisan queue:table

# Migrations
docker compose exec laravel.test php artisan migrate

# Clear/refresh config cache (handy after .env edits)
docker compose exec laravel.test php artisan config:clear
```


---

## 5) Run the app
Open **http://localhost** in your browser. You should see Laravel’s welcome page.