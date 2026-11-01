# Bayanuk PostgreSQL backups

Daily `pg_dump` of the production database to `/var/backups/bayanuk/postgres` on the host, kept 7 days.
The script and cron line are prepared here. They are **not installed**.

## Install (on the host, as root)

```sh
install -m 700 -o root -g root deploy/backup/bayanuk-pg-backup.sh /usr/local/sbin/bayanuk-pg-backup.sh
install -m 644 -o root -g root deploy/backup/bayanuk-pg-backup.cron /etc/cron.d/bayanuk-pg-backup
# First run by hand, then check the log and the file:
/usr/local/sbin/bayanuk-pg-backup.sh
ls -la /var/backups/bayanuk/postgres
```

The cron job runs at 02:30 UTC. That's after the image prune at 00:07 and before the
builder prune on Mondays at 03:53. Output goes to `/var/log/bayanuk-pg-backup.log`.

## Test a restore once (into a scratch database, production is not touched)

```sh
c="$(docker ps -q --filter label=com.docker.compose.project=bayanuk --filter label=com.docker.compose.service=postgres)"
f="$(ls -1t /var/backups/bayanuk/postgres/bayanuk-*.dump | head -n 1)"

docker exec "$c" sh -c 'createdb -U "$POSTGRES_USER" bayanuk_restore_test'
docker exec -i "$c" sh -c 'pg_restore -U "$POSTGRES_USER" -d bayanuk_restore_test --no-owner --exit-on-error' < "$f"

# Compare with production: the two numbers must match (repeat for any table you care about).
docker exec "$c" sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"       -Atc "select count(*) from migrations"'
docker exec "$c" sh -c 'psql -U "$POSTGRES_USER" -d bayanuk_restore_test -Atc "select count(*) from migrations"'

docker exec "$c" sh -c 'dropdb -U "$POSTGRES_USER" bayanuk_restore_test'
```

`pgvector` is installed in the image, so the `vector` extension restores with the dump.

## Notes

- The backups are on the same disk as the database. For off-site copies, sync
  `/var/backups/bayanuk/postgres` to another machine or bucket. That isn't set up yet.
- Uploaded documents in RustFS aren't covered by this. Under the retention policy
  (24 h for guests, 30 days for users) they're short-lived by design. Decide whether they need a backup.
