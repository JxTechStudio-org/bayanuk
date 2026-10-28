#!/bin/sh
# Deploy one image tag to production, with rollback to the tag that was running before.
# Usage: deploy/deploy.sh <image-tag>      (run from the Jenkins workspace, after `docker login ghcr.io`)
#
# Rollback restores the previous images only. Database migrations are never reversed
# automatically: migrations must stay backward compatible with the previous release.
set -eu
. deploy/lib.sh

new_tag="${1:?usage: deploy/deploy.sh <image-tag>}"
app_services="api web horizon scheduler"

# 1. The merged config must be valid and have no host bind mounts. Output is never printed.
IMAGE_TAG="$new_tag" bayanuk_compose prod config -q
IMAGE_TAG="$new_tag" bayanuk_assert_no_bind_mounts

# 2. The tag that is running now is the rollback target. It is read from the running api
#    container (the source of truth), and Jenkins also records it in the build description.
previous_tag="$(bayanuk_running_tag)"
printf '%s\n' "${previous_tag:-none}" > .deploy-previous-tag
echo "Running now: ${previous_tag:-nothing (first deploy)}. Deploying: $new_tag."

# 3. Pull the new images before anything is stopped.
IMAGE_TAG="$new_tag" bayanuk_compose prod pull --quiet $app_services

# 4. Data services. ClamAV loads its signatures for minutes the first time, so it is not
#    waited for; nothing depends on it at start.
IMAGE_TAG="$new_tag" bayanuk_compose prod up -d --wait --wait-timeout 180 postgres redis rustfs
IMAGE_TAG="$new_tag" bayanuk_compose prod up -d clamav

# 5. Migrations, with the new code, while the previous release keeps serving.
#    If this fails, the app containers were not touched, so there is nothing to roll back.
IMAGE_TAG="$new_tag" bayanuk_compose prod run --rm --no-deps api php artisan migrate --force

rollback() {
  if [ -z "$previous_tag" ] || [ "$previous_tag" = "$new_tag" ]; then
    echo "No previous release to roll back to. The stack is left as it is for investigation."
    return 1
  fi
  echo "Rolling back to $previous_tag (database migrations are kept)."
  IMAGE_TAG="$previous_tag" bayanuk_compose prod up -d --wait --wait-timeout 180 $app_services \
    && deploy/smoke.sh "$previous_tag" \
    && echo "Rolled back to $previous_tag." \
    || echo "ROLLBACK FAILED: check the stack by hand."
  return 1
}

# 6. Start the new release (Laravel caches are built at container start; see compose.production.yaml),
#    then smoke-test it through the localhost ports host nginx uses.
if ! IMAGE_TAG="$new_tag" bayanuk_compose prod up -d --wait --wait-timeout 180 --remove-orphans $app_services; then
  echo "The new release did not become healthy."
  rollback || bayanuk_fail "deploy of $new_tag failed"
fi
if ! deploy/smoke.sh "$new_tag"; then
  rollback || bayanuk_fail "deploy of $new_tag failed"
fi

echo "Deployed $new_tag."
