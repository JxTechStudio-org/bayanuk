pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        timeout(time: 40, unit: 'MINUTES')
    }

    environment {
        IMAGE           = 'bayanuk-api'
        COMPOSE         = "docker compose -p bayanuk-ci-${env.BUILD_NUMBER} --env-file backend/.env"
        FORWARD_DB_PORT = '55432'
    }

    stages {
        stage('Lint and type checks') {
            parallel {
                stage('Backend: Pint, PHPStan') {
                    steps {
                        sh '''
                            cp backend/.env.example backend/.env
                            sed -i 's/^DB_USERNAME=.*/DB_USERNAME=postgres/; s/^DB_PASSWORD=.*/DB_PASSWORD=ci-password/; s/^AWS_ACCESS_KEY_ID=.*/AWS_ACCESS_KEY_ID=ci-access-key/; s/^AWS_SECRET_ACCESS_KEY=.*/AWS_SECRET_ACCESS_KEY=ci-secret-key/' backend/.env
                            $COMPOSE build api
                            $COMPOSE run --rm --no-deps --user "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer api sh -c "composer install --no-interaction --prefer-dist && php artisan key:generate --force && vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G"
                        '''
                    }
                }
                stage('Frontend: ESLint, tsc') {
                    when { expression { fileExists('frontend/package.json') } }
                    steps {
                        sh 'docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$PWD/frontend":/app -w /app node:22 sh -c "npm ci && npm run lint --if-present && npx tsc --noEmit"'
                    }
                }
            }
        }

        stage('Tests and audit') {
            parallel {
                stage('Backend: Pest, composer audit') {
                    steps {
                        sh '''
                            $COMPOSE up -d --wait postgres redis
                            $COMPOSE run --rm --user "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer api sh -c "vendor/bin/pest && composer audit"
                        '''
                    }
                }
                stage('Frontend: Vitest, npm audit') {
                    when { expression { fileExists('frontend/package.json') } }
                    steps {
                        sh 'docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$PWD/frontend":/app -w /app node:22 sh -c "npm ci && npx vitest run --passWithNoTests && npm audit --audit-level=high"'
                    }
                }
            }
        }

        stage('Build image') {
            steps {
                script {
                    env.IMAGE_TAG = sh(script: 'git rev-parse --short=8 HEAD', returnStdout: true).trim()
                }
                sh 'docker build --pull --target production -t $IMAGE:$IMAGE_TAG .'
            }
        }
    }

    post {
        always {
            sh '$COMPOSE down -v --remove-orphans || true'
        }
    }
}