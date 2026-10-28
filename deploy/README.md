# Bayanuk production deployment

Production runs on the jxtechstudio.com server as Compose project `bayanuk`. Jenkins builds
the images, pushes them to GHCR, and deploys them after a manual approval. Host nginx is
managed by hand, outside this repository.

| Piece | Where |
| --- | --- |
| Compose | `docker-compose.yml` (base, local dev) + `compose.production.yaml` (production override) |
| Images | `ghcr.io/jxtechstudio-org/bayanuk-api:<sha12>`, `ghcr.io/jxtechstudio-org/bayanuk-web:<sha12>` |
| Secrets | `/root/secrets/bayanuk.env` on the host (root:root 600); template `deploy/bayanuk.env.example` |
| Pipeline | `Jenkinsfile` + `deploy/lib.sh` (the `bayanuk_compose` wrapper), `deploy/deploy.sh`, `deploy/smoke.sh`, `deploy/validate.sh` |
| Host nginx | `deploy/nginx/bayanuk.jxtechstudio.com.conf` (text to apply by hand) |
| Backups | `deploy/backup/` (prepared, not installed) |
| App to-dos | `deploy/APP-HANDOFF.md` |

## Ports (all on 127.0.0.1)

| Port | Service | Used by |
| --- | --- | --- |
| 8090 | web (container 8089) | host nginx `location /` |
| 9110 | api PHP-FPM (container 9000) | host nginx `/api/`, `/sanctum/`, `/up` (FastCGI) |
| 9012 | RustFS console (container 9001) | SSH tunnel only: `ssh -L 9012:127.0.0.1:9012 root@<server>` |

Postgres, Redis, the RustFS S3 API, ClamAV and Horizon have no host ports.

## ClamAV (off for now)

ClamAV isn't started in production until the upload scanning code exists. No app code calls it
yet, and it needs about 1.2 GB of RAM on a server with roughly 3.7 GB free. It sits behind the Compose
profile `clamav` in `compose.production.yaml`. Local development still starts it as before.

To turn it on:

1. Check the memory: `free -m`. "available" should be at least 2 GB above what the stack uses.
2. Add `COMPOSE_PROFILES=clamav` to `/root/secrets/bayanuk.env` (the template has it commented out).
3. Deploy again. `deploy/deploy.sh` starts ClamAV when the profile is on. The first start downloads
   the signatures and can take up to 5 minutes before the healthcheck passes.
4. Check: `IMAGE_TAG=<tag> $C ps clamav` shows `healthy` (`$C` as in "Manual commands" below).

To turn it off again, remove the line, then run `$C stop clamav && $C rm -f clamav`. The
signature volume `bayanuk_clamav-data` stays, so a later start is quicker.

## Requirements

- Docker Compose **2.24.4 or newer**, for `!reset` / `!override` in the override file. The pipeline
  uses its own pinned helper (`docker:29.6.2-cli`, Compose v5.3.1). The host has v5.2.0.
- `env_file` with `path`/`required` needs Compose 2.24 or newer (same requirement).

## How Jenkins runs Compose

Jenkins runs in a container with the host Docker socket. `bayanuk_compose` (deploy/lib.sh) runs
`docker compose` in a short-lived helper container with:

- `--volumes-from jenkins`: the workspace is at the same path (`/var/jenkins_home/workspace/...`);
- the env file mounted read-only at the same path as on the host (`--mount`, so a missing file
  is an error instead of an empty directory being created).

Nothing in production bind-mounts a host path. `deploy/validate.sh` and `deploy/deploy.sh`
fail if the merged config has any `type: bind` volume.

## Pipeline (main only, after all checks pass)

1. Checkout, `IMAGE_TAG` = first 12 characters of the commit SHA.
2. Checks: Pint, PHPStan, Pest (throwaway Postgres + Redis), composer audit; ESLint, tsc, Vite build,
   Vitest, npm audit (shipped dependencies).
3. Build `bayanuk-api` (Dockerfile `production` target) and `bayanuk-web` (frontend/Dockerfile).
4. Push both to GHCR (login with `--password-stdin`, temporary `DOCKER_CONFIG` in the workspace).
5. Manual approval (`DEPLOY_APPROVERS` in the Jenkinsfile).
6. `deploy/deploy.sh`: validate config, record the running tag, pull, start data services,
   `php artisan migrate --force`, start the release, smoke-test.
7. Laravel caches (`php artisan optimize`) are built when each app container starts, from the
   runtime env file, as www-data.
8. Smoke tests through the localhost ports: frontend `/`, FastCGI `/up` and `/api/v1/health`, Horizon status.
9. On failure: back to the images that were running before. **Migrations are never reversed**,
   so every migration must keep working with the previous release.

The rollback target is read from the running `api` container (`docker inspect`), not from a file:
it's always what's actually live, even after a manual change. Jenkins also writes it into
the build description (`<new> (previous: <old>, <result>)`), so the history shows what each
deploy replaced.

## Manual commands (on the host, from a checkout of the deployed commit)

```sh
export IMAGE_TAG=<sha12>
C="docker compose -p bayanuk -f docker-compose.yml -f compose.production.yaml --env-file /root/secrets/bayanuk.env"

$C config -q                      # validate (never run `config` without -q: it prints the secrets)
$C ps                             # status
$C logs --tail=100 api horizon    # logs
$C up -d --wait api web horizon scheduler   # (re)start the app with IMAGE_TAG
```

Rollback by hand: set `IMAGE_TAG` to the previous tag (see the Jenkins build description) and run the
`up` line above.

## Staging later

Same files, another project name, env file and ports:

```sh
IMAGE_TAG=<sha12> docker compose -p bayanuk-staging -f docker-compose.yml -f compose.production.yaml \
  --env-file /root/secrets/bayanuk-staging.env up -d
```

with `BAYANUK_WEB_PORT`, `BAYANUK_FPM_PORT`, `BAYANUK_RUSTFS_CONSOLE_PORT` set to free ports, and
`BAYANUK_ENV_FILE=/root/secrets/bayanuk-staging.env` in that file. Volumes are per project, so data
is separate. The server can't hold two full stacks permanently (2 CPUs, ~4 GB free).
