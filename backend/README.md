# Bayanuk Backend (بيانك)

Backend for **Bayanuk**, an AI review service for job offers and employment contracts. Built with Laravel.

## Tech Stack

- PHP 8.3+
- Laravel 11
- PostgreSQL 16+

## Local Setup Instructions

1. **Clone the repository and navigate to the backend folder:**
   ```bash
   cd bayanuk/backend
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment variables:**
   - Copy `.env.example` to `.env`:
     ```bash
     cp .env.example .env
     ```
   - Open `.env` and fill in your actual PostgreSQL database credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
   - *Note:* Since local development uses `database` for sessions and queues to simplify setup without requiring external services like Redis, ensure your PostgreSQL server is running and the database is created.

4. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```
   *(This creates the required tables for migrations, sessions, jobs/queues, and database caches).*

6. **Run the local server:**
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