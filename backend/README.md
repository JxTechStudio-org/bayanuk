# Bayanuk Backend (بيانك)

Backend for **Bayanuk**, an AI review service for job offers and employment contracts. Built with Laravel.

## Tech Stack

- PHP 8.3+ (with the `redis` extension)
- Laravel 13+
- PostgreSQL 16+ with pgvector
- Redis (queues, cache, and sessions)
- RustFS (private S3-compatible file storage)
- ClamAV (antivirus file scanner)

## Ports Reference

| Service | Container Port | Host Port | Purpose |
| --- | --- | --- | --- |
| **API / Nginx** | 80 | `8000` (or proxy) | Backend API access |
| **PostgreSQL** | 5432 | `5466` | Database connection |
| **RustFS S3 API** | 9000 | `9000` | File storage API |
| **RustFS Console** | 9001 | `9010` | S3 storage dashboard |
| **Redis** | 6379 | `6379` | Cache & Queues |
| **ClamAV** | 3310 | `3310` | Antivirus scanner |

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
   FORWARD_DB_PORT=5466
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

   MAIL_MAILER=log
   RESEND_API_KEY=
   MAIL_SCHEME=null
   MAIL_HOST=127.0.0.1
   MAIL_PORT=2525
   MAIL_USERNAME=null
   MAIL_PASSWORD=null
   MAIL_FROM_ADDRESS="no-reply@bayanuk.test"
   MAIL_FROM_NAME="${APP_NAME}"

   CLAMAV_HOST=clamav
```
**Email Configuration:**
  - Local Development: Configured with MAIL_MAILER=log in .env.example so developers can run the app locally without needing a real email API key.

  - Production: Real mail credentials and API keys are injected securely via the Jenkins Pipeline variables at deployment time.

   Google sign-in, and AI provider settings (`GOOGLE_*`, `GEMINI_API_KEY`, `VOYAGE_API_KEY`, `BAYANUK_DAILY_AI_BUDGET_USD`) can stay empty until those features are implemented.

3. **Start all Docker services (PostgreSQL, Redis, RustFS, ClamAV, API, Horizon, Scheduler):**
```bash
     docker compose --env-file backend/.env --profile local up -d --build
```
 You can check container statuses with:
```bash
     docker compose --env-file backend/.env --profile local ps
```
To stop all Docker services:
```bash
     docker compose --env-file backend/.env --profile local down
```
4. **Set up RustFS (S3-compatible storage):**
- Open the RustFS console in your browser at `http://localhost:9010` and log in with `rustfsadmin / rustfsadmin`.
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
**Note:** The local environment includes an Nginx container via --profile local which automatically proxies HTTP requests to the PHP-FPM API container.

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