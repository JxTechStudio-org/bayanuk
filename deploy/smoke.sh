#!/bin/sh
# Smoke tests for a running production stack, through the same localhost ports host nginx uses.
# Usage: deploy/smoke.sh <image-tag>      (run from the Jenkins workspace)
set -eu
. deploy/lib.sh

tag="${1:?usage: deploy/smoke.sh <image-tag>}"
web_port="${BAYANUK_WEB_PORT:-8090}"
fpm_port="${BAYANUK_FPM_PORT:-9110}"
api_image="ghcr.io/jxtechstudio-org/bayanuk-api:$tag"

# A FastCGI request to PHP-FPM on the host port, the way host nginx sends it.
# The api image has cgi-fcgi; it runs only as a client here, with no env file.
fpm_get() {
  docker run --rm --network host --entrypoint sh \
    -e REQUEST_URI="$1" "$api_image" -c \
    "SCRIPT_FILENAME=/var/www/html/public/index.php SCRIPT_NAME=/index.php \
     REQUEST_METHOD=GET SERVER_NAME=bayanuk.jxtechstudio.com HTTPS=on \
     cgi-fcgi -bind -connect 127.0.0.1:$fpm_port"
}

check() {
  name="$1"
  shift
  for _ in 1 2 3 4 5; do
    if "$@" >/dev/null 2>&1; then
      echo "OK   $name"
      return 0
    fi
    sleep 3
  done
  echo "FAIL $name"
  return 1
}

web_index() {
  docker run --rm --network host "$BAYANUK_HELPER_IMAGE" \
    wget -q -O - "http://127.0.0.1:$web_port/" | grep -q 'id="root"'
}

# PHP-FPM sends a Status header only for non-200 answers.
api_up() {
  out="$(fpm_get /up)"
  ! printf '%s\n' "$out" | grep -qi '^Status: [^2]'
}

api_health() {
  fpm_get /api/v1/health | grep -q '"status":"ok"'
}

horizon_running() {
  IMAGE_TAG="$tag" bayanuk_compose prod exec -T horizon php artisan horizon:status | grep -qi 'running'
}

failed=0
check "frontend  http://127.0.0.1:$web_port/"          web_index       || failed=1
check "api       /up via 127.0.0.1:$fpm_port"          api_up          || failed=1
check "api       /api/v1/health via 127.0.0.1:$fpm_port" api_health    || failed=1
check "horizon   status"                               horizon_running || failed=1

[ "$failed" = 0 ] || bayanuk_fail "smoke tests failed for $tag"
echo "Smoke tests passed for $tag."
