# CI image for backend checks: the development stage of ../../Dockerfile plus the code.
# Build context: backend/  (docker build -f deploy/ci/backend.Dockerfile backend)
ARG BASE
FROM ${BASE}
COPY . .
RUN composer install --no-interaction --prefer-dist --no-progress \
    && php artisan key:generate --force
