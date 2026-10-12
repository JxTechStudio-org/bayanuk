# Bayanuk Backend (بيانك)

Backend for **Bayanuk**, an AI review service for job offers and employment contracts. Built with Laravel.

## Tech Stack

- PHP 8.3+
- Laravel 13+
- PostgreSQL 16+ with pgvector
- Redis (for queues, cache, and sessions)
- RustFS (private S3-compatible file storage)

## Local Setup Instructions

1. **Clone the repository and navigate to the backend folder:**
   ```bash
   git clone <https://github.com/JxTechStudio-org/bayanuk> bayanuk
   cd bayanuk/backend
   ```

2. **Ensure Required PHP Extensions Are Enabled:**
   Verify in your `php.ini` that extensions such as `fileinfo`, `mbstring`, `openssl`, `pdo_pgsql`, and `bcmath` are enabled.
   ```bash
   php -m
   ```

3. **Install dependencies (including Flysystem S3 adapter for RustFS):**
   ```bash
   composer install
   ```

4. **Configure environment variables:**
   - Copy `.env.example` to `.env`:
     ```bash
     cp .env.example .env
     ```
   - Open `.env` and fill in your actual PostgreSQL database credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
   - *Note:* Ensure your PostgreSQL server and Redis service are running locally before executing migrations and starting queue workers.
   - Configure your S3-compatible RustFS settings in .env:
   ```bash
     FILESYSTEM_DISK=s3
     AWS_ACCESS_KEY_ID=your-rustfs-key
     AWS_SECRET_ACCESS_KEY=your-rustfs-secret
     AWS_DEFAULT_REGION=us-east-1
     AWS_BUCKET=bayanuk
     AWS_USE_PATH_STYLE_ENDPOINT=true
     AWS_ENDPOINT=http://127.0.0.1:9000
   ```

5. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

6. **Run migrations:**
   ```bash
   php artisan migrate
   ```
   *(This creates the required tables for migrations, sessions, jobs/queues, and database caches).*

7. **Run the local server:**
   ```bash
   php artisan serve
   ```
   The API will be available at `http://localhost:8000/api/v1`.

## Code Quality & Testing

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