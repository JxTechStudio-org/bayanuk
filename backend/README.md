# Bayanuk Backend (بيانك)

Backend for **Bayanuk**, an AI review service for job offers and employment contracts. Built with Laravel.

## Tech Stack

- PHP 8.3+ (with the `redis` extension)
- Laravel 13+
- PostgreSQL 16+ with pgvector
- Redis (queues, cache, and sessions)
- RustFS (private S3-compatible file storage)

## Local Setup Instructions

1. **Clone the repository and navigate to the backend folder:**
```bash
   git clone https://github.com/JxTechStudio-org/bayanuk bayanuk
   cd bayanuk/backend
```

2. **Start PostgreSQL (with pgvector) and Redis using Docker:**
```bash
   docker run -d --name bayanuk-db -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=bayanuk -p 5432:5432 pgvector/pgvector:pg17
   docker run -d --name bayanuk-redis -p 6379:6379 redis:7
```
   - The `vector` extension is enabled automatically by the first migration, so no manual SQL is needed.
   - If port 5432 is already used by a local PostgreSQL, map another port (for example `-p 5433:5432`) and set `DB_PORT` in `.env`.
   - These credentials are for local development only.

3. **Install dependencies:**
```bash
   composer install
```
   This must include the Flysystem S3 adapter (`league/flysystem-aws-s3-v3`) used by the RustFS disk.

   **Redis client:** `.env.example` uses `REDIS_CLIENT=phpredis`, which needs the PHP `redis` extension. Check it with:
```bash
   php -r "var_dump(extension_loaded('redis'));"
```
   If it is not available, run `composer require predis/predis` and set `REDIS_CLIENT=predis` in your `.env`.

4. **Configure environment variables:**
```bash
   cp .env.example .env
```
   Open `.env` and set your database credentials:
```env
   DB_DATABASE=bayanuk
   DB_USERNAME=postgres
   DB_PASSWORD=postgres
```
   Then set the RustFS (S3) settings. The keys below are RustFS's default local credentials:
```env
   FILESYSTEM_DISK=s3
   AWS_ACCESS_KEY_ID=rustfsadmin
   AWS_SECRET_ACCESS_KEY=rustfsadmin
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=bayanuk
   AWS_USE_PATH_STYLE_ENDPOINT=true
   AWS_ENDPOINT=http://127.0.0.1:9000
```
   Google sign-in, ClamAV, and AI provider settings (`GOOGLE_*`, `CLAMAV_*`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`) can stay empty until those features are implemented.

5. **Start and set up RustFS (S3-compatible storage):**
   - Run RustFS with Docker (port 9000 is the S3 API, port 9001 is the web console):
```bash
     docker run -d --name rustfs -p 9000:9000 -p 9001:9001 rustfs/rustfs:latest
```
   - Open the console at `http://localhost:9001` and log in with `rustfsadmin` / `rustfsadmin`.
   - Create a bucket named `bayanuk` (it must match `AWS_BUCKET` in `.env`).
   - To use your own credentials, start the container with `-e RUSTFS_ACCESS_KEY=... -e RUSTFS_SECRET_KEY=...` and use the same values in `.env`. Never reuse the defaults on staging or production.
   - To keep files after the container is removed, add a volume (for example `-v rustfs-data:/data`). If the container fails to start, check `docker logs rustfs`.
   - After the application key is generated (step 6), verify the connection:
```bash
     php artisan tinker --execute="Storage::disk('s3')->put('test.txt', 'ok'); echo Storage::disk('s3')->get('test.txt'); Storage::disk('s3')->delete('test.txt');"
```
     It should print `ok`.

6. **Generate application key:**
```bash
   php artisan key:generate
```

7. **Run migrations:**
```bash
   php artisan migrate
```
   *(This enables pgvector and creates all application tables, plus the Laravel sessions, cache, and jobs tables.)*

   To load development data, run `php artisan migrate --seed` instead. To rebuild a local database from scratch: `php artisan migrate:fresh --seed`.

8. **Run the local server:**
```bash
   php artisan serve
```
   The API will be available at `http://localhost:8000/api/v1`.

## Code Quality & Testing

Tests run against a separate PostgreSQL database, because `RefreshDatabase` wipes the database it runs on. Create it once:

```bash
docker exec -it bayanuk-db psql -U postgres -c "CREATE DATABASE bayanuk_test;"
```

- **Run Tests (Pest):**
```bash
  vendor/bin/pest
```
- **Run Static Analysis (PHPStan Level 6):**
```bash
  vendor/bin/phpstan analyse
```
- **Run Code Style Fixer (Pint):**
```bash
  vendor/bin/pint
```