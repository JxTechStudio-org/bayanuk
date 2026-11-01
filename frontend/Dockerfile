# syntax=docker/dockerfile:1

# The frontend image: Node builds the site, then nginx serves the built files.
# Build it from this folder: docker build -t bayanuk-web frontend

# Stage 1: build the site (Vite writes it to dist/)
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: serve the built files. This nginx image runs as a normal user, not root, on port 8089.
FROM nginxinc/nginx-unprivileged:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 8089
