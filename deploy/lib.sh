# Shared helpers for the Bayanuk pipeline. Sourced by the Jenkinsfile steps and deploy/*.sh.
# Runs inside the Jenkins container, from the job workspace.
#
# Never `set -x` in anything that sources this file, and never print `docker compose config`
# output: with interpolation it contains the secrets from the env file.

# Exact helper image: Docker CLI 29.6.2 with Compose v5.3.1, pinned by digest.
BAYANUK_HELPER_IMAGE='docker:29.6.2-cli@sha256:be132a9f282288de4afaf63379dff75711fda0147c6b72a9df44e51841402144'
BAYANUK_JENKINS_CONTAINER="${BAYANUK_JENKINS_CONTAINER:-jenkins}"
BAYANUK_ENV_FILE="${BAYANUK_ENV_FILE:-/root/secrets/bayanuk.env}"
BAYANUK_PROJECT="${BAYANUK_PROJECT:-bayanuk}"

# Unique per build across all branches of the multibranch job (BUILD_TAG is
# jenkins-<job>-<branch>-<number>). Used for the CI project name and CI image tags, so main
# and development never share a CI stack, even at the same build number or commit.
BAYANUK_CI_ID="$(printf '%s' "${BUILD_TAG:-local}" | tr 'A-Z' 'a-z' | tr -c 'a-z0-9_-' '-' | cut -c1-60)"

bayanuk_fail() {
  echo "ERROR: $*" >&2
  exit 1
}

# The only way the pipeline runs docker compose.
#
#   bayanuk_compose prod <compose args...>   production stack (project $BAYANUK_PROJECT)
#   bayanuk_compose ci   <compose args...>   throwaway CI stack (project bayanuk-ci-$BAYANUK_CI_ID)
#
# Compose runs in a short-lived helper container, not in Jenkins itself:
# - --volumes-from gives the helper the Jenkins volumes at the same paths, so the workspace
#   (/var/jenkins_home/workspace/...) and the compose files resolve exactly as in Jenkins.
# - For prod, the host env file is mounted read-only at the same path as on the host, so
#   --env-file and env_file resolve identically. --mount (not -v) fails if the file is
#   missing, instead of creating an empty directory on the host.
# - The helper runs as root, so the env file can stay root:root 600 on the host.
bayanuk_compose() {
  target="$1"
  shift

  case "$PWD" in
    /var/jenkins_home/*) ;;
    *) bayanuk_fail "bayanuk_compose must run from the Jenkins workspace (PWD=$PWD)" ;;
  esac

  case "$target" in
    prod)
      [ -n "${IMAGE_TAG:-}" ] || bayanuk_fail "IMAGE_TAG is not set"
      docker run --rm \
        --volumes-from "$BAYANUK_JENKINS_CONTAINER" \
        --mount "type=bind,src=$BAYANUK_ENV_FILE,dst=$BAYANUK_ENV_FILE,readonly" \
        -w "$PWD" \
        -e IMAGE_TAG -e DOCKER_CONFIG -e BAYANUK_ENV_FILE \
        "$BAYANUK_HELPER_IMAGE" \
        docker compose -p "$BAYANUK_PROJECT" \
          -f docker-compose.yml -f compose.production.yaml \
          --env-file "$BAYANUK_ENV_FILE" "$@"
      ;;
    ci)
      [ -n "${BUILD_TAG:-}" ] || bayanuk_fail "BUILD_TAG is not set"
      docker run --rm \
        --volumes-from "$BAYANUK_JENKINS_CONTAINER" \
        -w "$PWD" \
        -e IMAGE_TAG="$BAYANUK_CI_ID" \
        "$BAYANUK_HELPER_IMAGE" \
        docker compose -p "bayanuk-ci-$BAYANUK_CI_ID" \
          -f docker-compose.yml -f deploy/ci/compose.ci.yaml \
          --env-file backend/.env "$@"
      ;;
    *)
      bayanuk_fail "unknown compose target: $target"
      ;;
  esac
}

# Fails if the merged production config has any host bind mount.
# Uses --no-interpolate and --no-env-resolution, so the output it reads holds no secrets,
# and it prints only the count.
bayanuk_assert_no_bind_mounts() {
  binds="$(bayanuk_compose prod config --no-interpolate --no-env-resolution --format json \
    | grep -Eo '"type": *"bind"' | wc -l | tr -d ' ')"
  [ "$binds" = "0" ] || bayanuk_fail "production config has $binds host bind mount(s)"
  echo "No host bind mounts in the production config."
}

# Image tag of the running production api container, empty if none is running.
bayanuk_running_tag() {
  docker ps \
    --filter "label=com.docker.compose.project=$BAYANUK_PROJECT" \
    --filter "label=com.docker.compose.service=api" \
    --format '{{.Image}}' | head -n 1 | sed -n 's/^.*:\([0-9a-f]\{7,40\}\)$/\1/p'
}

# Removes the throwaway CI stack and images of this build only, by label and tag.
# It works even when backend/.env is gone, and it can never match the production project.
bayanuk_ci_cleanup() {
  [ -n "${BUILD_TAG:-}" ] || return 0
  docker image rm -f "bayanuk-api-dev:$BAYANUK_CI_ID" "bayanuk-api-ci:$BAYANUK_CI_ID" \
    "bayanuk-web-build:$BAYANUK_CI_ID" >/dev/null 2>&1 || true
  label="label=com.docker.compose.project=bayanuk-ci-$BAYANUK_CI_ID"
  ids="$(docker ps -aq --filter "$label")"
  [ -z "$ids" ] || docker rm -f $ids >/dev/null
  ids="$(docker volume ls -q --filter "$label")"
  [ -z "$ids" ] || docker volume rm $ids >/dev/null
  ids="$(docker network ls -q --filter "$label")"
  [ -z "$ids" ] || docker network rm $ids >/dev/null
  return 0
}
