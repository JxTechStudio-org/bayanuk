#!/bin/sh
# Daily PostgreSQL backup for Bayanuk production. Runs on the HOST (not in Jenkins), as root,
# from cron. Keeps 7 days. Prepared only: install it by hand (see deploy/backup/README.md).
#
# Writes a custom-format dump (pg_dump -Fc) to $BACKUP_DIR, outside Docker volumes, so it
# survives `docker compose down -v` and volume loss. The credentials are read inside the
# container from its own environment, so they never appear in arguments, logs or cron mail.
set -eu
umask 077

PROJECT="${BAYANUK_PROJECT:-bayanuk}"
BACKUP_DIR="${BAYANUK_BACKUP_DIR:-/var/backups/bayanuk/postgres}"
KEEP_DAYS="${BAYANUK_BACKUP_KEEP_DAYS:-7}"

log() { echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) $*"; }

container="$(docker ps -q \
  --filter "label=com.docker.compose.project=$PROJECT" \
  --filter "label=com.docker.compose.service=postgres" | head -n 1)"
[ -n "$container" ] || { log "ERROR: no running postgres container for project $PROJECT"; exit 1; }

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

stamp="$(date -u +%Y%m%d-%H%M%S)"
final="$BACKUP_DIR/bayanuk-$stamp.dump"
partial="$final.partial"

# shellcheck disable=SC2016  # $POSTGRES_* are expanded inside the container
if ! docker exec "$container" sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc --no-owner' > "$partial"; then
  rm -f "$partial"
  log "ERROR: pg_dump failed"
  exit 1
fi

# The dump must be readable by pg_restore (catches truncated files).
if ! docker exec -i "$container" pg_restore --list > /dev/null < "$partial"; then
  rm -f "$partial"
  log "ERROR: dump is not a valid archive"
  exit 1
fi

mv "$partial" "$final"
log "OK: $final ($(du -h "$final" | cut -f1))"

# Retention: delete dumps older than KEEP_DAYS days. Only files this script creates.
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'bayanuk-*.dump' -mtime +"$((KEEP_DAYS - 1))" -print -delete \
  | while read -r old; do log "deleted $old"; done
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'bayanuk-*.dump.partial' -mmin +120 -delete
