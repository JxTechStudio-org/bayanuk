// Bayanuk CI/CD (Multibranch Pipeline job).
//
// Every branch: lint, type checks, tests, audits, frontend build.
// main only:    build the api and web images (tagged with the commit), push them to GHCR,
//               wait for manual approval, deploy to bayanuk.jxtechstudio.com, smoke-test,
//               and roll back to the previous images if the new release fails.
//
// Rules: every shell step starts with #!/bin/sh so Jenkins does not add `set -x`; every
// docker compose call goes through bayanuk_compose (deploy/lib.sh); secrets never appear in
// arguments or logs. App secrets live only in /root/secrets/bayanuk.env on the host.

pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        skipDefaultCheckout(true)
        timeout(time: 90, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '30'))
    }

    environment {
        API_IMAGE        = 'ghcr.io/jxtechstudio-org/bayanuk-api'
        WEB_IMAGE        = 'ghcr.io/jxtechstudio-org/bayanuk-web'
        // Jenkins user IDs allowed to approve a production deploy (comma-separated).
        DEPLOY_APPROVERS = 'adminjana'
        // Registry login for this build only, deleted in post { always }.
        DOCKER_CONFIG    = "${WORKSPACE}/.docker-ghcr"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
                script {
                    env.GIT_SHA   = sh(returnStdout: true, script: '#!/bin/sh\ngit rev-parse HEAD').trim()
                    env.IMAGE_TAG = env.GIT_SHA.take(12)
                    currentBuild.displayName = "#${env.BUILD_NUMBER} ${env.IMAGE_TAG}"
                }
                echo "Branch ${env.BRANCH_NAME}, commit ${env.GIT_SHA}"
            }
        }

        stage('Checks') {
            parallel {
                stage('Backend: Pint, PHPStan, Pest, composer audit') {
                    steps {
                        sh '''#!/bin/sh
                            set -eu
                            . deploy/lib.sh
                            # Dummy settings for the throwaway CI stack only.
                            cp backend/.env.example backend/.env
                            sed -i 's/^DB_USERNAME=.*/DB_USERNAME=postgres/; s/^DB_PASSWORD=.*/DB_PASSWORD=ci-password/; s/^AWS_ACCESS_KEY_ID=.*/AWS_ACCESS_KEY_ID=ci-access-key/; s/^AWS_SECRET_ACCESS_KEY=.*/AWS_SECRET_ACCESS_KEY=ci-secret-key/' backend/.env

                            docker build --target development -t "bayanuk-api-dev:$IMAGE_TAG" .
                            docker build --build-arg BASE="bayanuk-api-dev:$IMAGE_TAG" \
                                -f deploy/ci/backend.Dockerfile -t "bayanuk-api-ci:$IMAGE_TAG" backend

                            bayanuk_compose ci up -d --wait postgres redis
                            bayanuk_compose ci run --rm --no-deps api sh -c \
                                "vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/pest && composer audit"
                        '''
                    }
                }
                stage('Frontend: ESLint, tsc, build, Vitest, npm audit (prod deps)') {
                    steps {
                        sh '''#!/bin/sh
                            set -eu
                            # The build stage runs npm ci and npm run build (tsc -b, vite build).
                            # The audit gate covers the dependencies that ship in the site (--omit=dev);
                            # dev tooling never reaches the web image.
                            docker build --target build -t "bayanuk-web-build:$IMAGE_TAG" frontend
                            docker run --rm "bayanuk-web-build:$IMAGE_TAG" \
                                sh -c "npm run lint && npx vitest run && npm audit --audit-level=high --omit=dev"
                        '''
                    }
                }
            }
        }

        stage('Build images') {
            when { branch 'main' }
            steps {
                sh '''#!/bin/sh
                    set -eu
                    docker build --pull --target production \
                        --label org.opencontainers.image.revision="$GIT_SHA" \
                        --label org.opencontainers.image.source=https://github.com/JxTechStudio-org/bayanuk \
                        -t "$API_IMAGE:$IMAGE_TAG" .
                    docker build --pull \
                        --label org.opencontainers.image.revision="$GIT_SHA" \
                        --label org.opencontainers.image.source=https://github.com/JxTechStudio-org/bayanuk \
                        -t "$WEB_IMAGE:$IMAGE_TAG" frontend
                '''
            }
        }

        stage('Push to GHCR') {
            when { branch 'main' }
            steps {
                withCredentials([usernamePassword(credentialsId: 'ghcr-push',
                                                  usernameVariable: 'GHCR_USER',
                                                  passwordVariable: 'GHCR_TOKEN')]) {
                    sh '''#!/bin/sh
                        set -eu
                        mkdir -p "$DOCKER_CONFIG"
                        chmod 700 "$DOCKER_CONFIG"
                        printf '%s' "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin >/dev/null
                        echo "Logged in to ghcr.io."
                    '''
                }
                sh '''#!/bin/sh
                    set -eu
                    docker push --quiet "$API_IMAGE:$IMAGE_TAG"
                    docker push --quiet "$WEB_IMAGE:$IMAGE_TAG"
                '''
            }
        }

        stage('Approve production deploy') {
            when { branch 'main' }
            options { timeout(time: 30, unit: 'MINUTES') }
            steps {
                input message: "Deploy ${env.IMAGE_TAG} to bayanuk.jxtechstudio.com?",
                      ok: 'Deploy',
                      submitter: env.DEPLOY_APPROVERS
            }
        }

        stage('Deploy, migrate, smoke test') {
            when { branch 'main' }
            steps {
                // Validates the config (no bind mounts), pulls, migrates, starts the release,
                // runs the smoke tests, and rolls back the images if anything fails.
                sh '''#!/bin/sh
                    set -eu
                    sh deploy/deploy.sh "$IMAGE_TAG"
                '''
            }
            post {
                always {
                    script {
                        def previous = fileExists('.deploy-previous-tag') ? readFile('.deploy-previous-tag').trim() : 'unknown'
                        currentBuild.description = "${env.IMAGE_TAG} (previous: ${previous}, ${currentBuild.currentResult})"
                    }
                }
            }
        }
    }

    post {
        always {
            sh '''#!/bin/sh
                . deploy/lib.sh 2>/dev/null || exit 0
                bayanuk_ci_cleanup
                if [ -n "${IMAGE_TAG:-}" ]; then
                    docker image rm -f "bayanuk-api-dev:$IMAGE_TAG" "bayanuk-api-ci:$IMAGE_TAG" \
                        "bayanuk-web-build:$IMAGE_TAG" >/dev/null 2>&1 || true
                fi
                docker logout ghcr.io >/dev/null 2>&1 || true
                rm -rf "$DOCKER_CONFIG" backend/.env .deploy-previous-tag
            '''
        }
    }
}
