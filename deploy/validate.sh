#!/bin/sh
# Validates the production compose config without deploying anything.
# Usage: deploy/validate.sh [image-tag]      (run from the Jenkins workspace)
#
# Checks: the merged config is valid, has no host bind mounts, binds published ports to
# 127.0.0.1 only, and pins every image. Prints only results, never the config itself.
set -eu
. deploy/lib.sh

tag="${1:-validate}"

IMAGE_TAG="$tag" bayanuk_compose prod config -q
echo "Merged production config is valid."

IMAGE_TAG="$tag" bayanuk_assert_no_bind_mounts

# The rest reads the config without interpolation and env files, so it holds no secrets.
config_yaml="$(IMAGE_TAG="$tag" bayanuk_compose prod config --no-interpolate --no-env-resolution)"

# One line per entry under a service's "ports:" list, as "<host ip> <entry>". Compose keeps an
# entry with ${...} in short form ("127.0.0.1:${PORT}:80") and expands others to long form
# (mode/host_ip/target/published), where a missing host_ip means 0.0.0.0.
ports="$(printf '%s\n' "$config_yaml" | awk '
  function flush() { if (entry != "") print (ip == "" ? "0.0.0.0" : ip), entry; entry = ""; ip = "" }
  /^    [a-z_]+:/ { flush(); in_ports = ($0 ~ /^    ports:$/); next }
  in_ports && /^      - [^ ]+$/ && $0 !~ /: / {
    flush(); entry = substr($0, 9); ip = (entry ~ /^127\.0\.0\.1:/) ? "127.0.0.1" : "public"; next }
  in_ports && /^      - / { flush(); entry = substr($0, 9) }
  in_ports && /host_ip: / { v = $0; sub(/.*host_ip: */, "", v); gsub(/"/, "", v); ip = v }
  in_ports && /published: / { v = $0; sub(/.*published: */, "", v); entry = entry " published=" v }
  /^  [a-z]/ { flush(); in_ports = 0 }
  END { flush() }')"
port_count="$(printf '%s\n' "$ports" | grep -c . || true)"
not_local="$(printf '%s\n' "$ports" | grep . | grep -v '^127\.0\.0\.1 ' || true)"
[ -z "$not_local" ] || bayanuk_fail "published ports not bound to 127.0.0.1: $not_local"
echo "All $port_count published ports are bound to 127.0.0.1."

# Every image must have a tag (or digest), and none may be :latest.
images="$(printf '%s\n' "$config_yaml" | sed -n 's/^    image: //p')"
unpinned="$(printf '%s\n' "$images" | grep -Ev '(:[^/:]+|@sha256:[0-9a-f]+)$' || true)"
latest="$(printf '%s\n' "$images" | grep -E ':latest$' || true)"
[ -z "$unpinned$latest" ] || bayanuk_fail "unpinned images: $unpinned $latest"
echo "All $(printf '%s\n' "$images" | grep -c .) images are pinned to a version or the commit tag."
