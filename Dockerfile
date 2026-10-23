# syntax=docker/dockerfile:1

# Stage: Frontend build
FROM node:22-alpine AS frontend-build

WORKDIR /app/frontend

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# Stage: Base PHP Environment
# PHP 8.3.36 fixes CVE-2026-109618 and CVE-2026-109619 (critical RCE). Never go below it.
FROM php:8.3.36-fpm-bookworm AS base

ENV COMPOSER_ALLOW_SUPERUSER=1

# Text extraction tools: poppler (pdftotext, pdftoppm) and Tesseract with Arabic + English
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        poppler-utils tesseract-ocr tesseract-ocr-ara tesseract-ocr-eng \
        git unzip curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis intl zip bcmath pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Upload limit is 10 MB; 12M leaves room for the multipart overhead
RUN printf '%s\n' \
        'upload_max_filesize = 12M' \
        'post_max_size = 12M' \
        'memory_limit = 512M' \
        'max_execution_time = 120' \
        > /usr/local/etc/php/conf.d/zz-bayanuk.ini

WORKDIR /var/www/html

# Local development: the code is bind-mounted by docker-compose.yml
FROM base AS development