# Bayanuk Backend (بيانك)

Backend for **Bayanuk**, an AI review service for job offers and employment contracts. Built with Laravel.

## Tech Stack

- PHP 8.3+ (with the `redis` extension)
- Laravel 13+
- PostgreSQL 16+ with pgvector
- Redis (queues, cache, and sessions)
- RustFS (private S3-compatible file storage)
- ClamAV (antivirus file scanner)

## Local Setup Instructions (Run with Docker)

Everything runs with Docker Compose from the repository root: API (PHP-FPM), queue worker (Horizon), scheduler, PostgreSQL + pgvector, Redis, RustFS, ClamAV. You only need Docker Desktop.

**Note:** Run all commands from the repository root directory where docker-compose.yml is located.

1. **Clone the repository and navigate to the root folder:**
```bash
   git clone https://github.com/JxTechStudio-org/bayanuk bayanuk
   cd bayanuk
```

2. **Configure environment variables:**
```bash
   cp backend/.env.example backend/.env
```
   Open `backend/.env` and ensure your database credentials and RustFS container endpoint are set correctly:
```env
   DB_CONNECTION=pgsql
   DB_HOST=postgres
   DB_PORT=5432
   DB_DATABASE=bayanuk
   DB_USERNAME=postgres
   DB_PASSWORD=postgres

   REDIS_CLIENT=phpredis
   REDIS_HOST=redis
   REDIS_PORT=6379

   FILESYSTEM_DISK=s3
   AWS_ACCESS_KEY_ID=rustfsadmin
   AWS_SECRET_ACCESS_KEY=rustfsadmin
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=bayanuk
   AWS_USE_PATH_STYLE_ENDPOINT=true
   AWS_ENDPOINT=http://rustfs:9000

   CLAMAV_HOST=clamav
```
   Google sign-in, and AI provider settings (`GOOGLE_*`, `GEMINI_API_KEY`, `VOYAGE_API_KEY`, `BAYANUK_DAILY_AI_BUDGET_USD`) can stay empty until those features are implemented.

3. **Start all Docker services (PostgreSQL, Redis, RustFS, ClamAV, API, Horizon, Scheduler):**
```bash
     docker compose --env-file backend/.env up -d --build
```
 You can check container statuses with:
```bash
     docker compose --env-file backend/.env ps
```
4. **Set up RustFS (S3-compatible storage):**
- Open the RustFS console in your browser at `http://localhost:9001` and log in with `rustfsadmin / rustfsadmin`.
- Create a bucket named `bayanuk` (it must match `AWS_BUCKET` in your .env).

5. **Generate application key:**
```bash
   docker compose --env-file backend/.env exec api php artisan key:generate
```

6. **Run migrations:**
```bash
   docker compose --env-file backend/.env exec api php artisan migrate
```
   *(This enables pgvector and creates all application tables, plus the Laravel sessions, cache, and jobs tables.)*

   To load development seed data instead, run:
   ```bash
   docker compose --env-file backend/.env exec api php artisan migrate --seed
   ```
Important: The current Docker Compose configuration runs Laravel
using PHP-FPM but does not include an HTTP web server such as Nginx or
Apache. An additional web-server configuration is required to access the
API over HTTP.

## Code Quality & Testing

The bayanuk_test database is created automatically when PostgreSQL initializes a new data volume. If it does not exist, create it manually:
```bash
  docker compose --env-file backend/.env exec postgres psql -U postgres -d postgres -c "CREATE DATABASE bayanuk_test;"
```

- **Run Tests (Pest):**
```bash
  docker compose --env-file backend/.env exec api vendor/bin/pest
```
- **Run Static Analysis (PHPStan Level 6):**
```bash
  docker compose --env-file backend/.env exec api vendor/bin/phpstan analyse
```
- **Run Code Style Fixer (Pint):**
```bash
  docker compose --env-file backend/.env exec api vendor/bin/pint
```