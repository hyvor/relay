FROM oven/bun:1 AS bun
FROM composer:2.8.8 AS composer
FROM dunglas/frankenphp:1.4.4-php8.4 AS frankenphp
FROM golang:1.23.4 AS golang

ARG APP_VERSION=0.0.0
ENV APP_VERSION=${APP_VERSION}

FROM bun AS frontend-base
WORKDIR /app/frontend
COPY frontend/package.json frontend/bun.lock frontend/svelte.config.js frontend/vite.config.ts frontend/tsconfig.json /app/frontend/
COPY frontend/src /app/frontend/src
COPY frontend/static /app/frontend/static

FROM frontend-base AS frontend-dev
RUN bun install
CMD ["bun", "run", "dev"]

FROM frontend-base AS frontend-prod
RUN  bun install \
    && bun run build \
    && find . -maxdepth 1 -not -name build -not -name . -exec rm -rf {} \;

FROM golang AS worker-dev
WORKDIR /worker
COPY worker/ /worker/
RUN go mod download
CMD [ "/worker/worker.dev.run" ]

FROM golang AS worker
WORKDIR /app/worker
COPY worker/ /app/worker/
RUN go build -o ./worker .

FROM frankenphp AS backend-base
ENV APP_RUNTIME="Runtime\FrankenPhpSymfony\Runtime"
WORKDIR /app/backend
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions zip intl pdo_pgsql opcache apcu amqp \
    && echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini     # Enable APCu for CLI for Metrics tests
RUN apt update  && apt install -y supervisor

FROM backend-base AS backend-dev
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && apt install -y symfony-cli
RUN install-php-extensions pcov
COPY backend/composer.json backend/composer.lock /app/backend/
RUN composer install
COPY backend /app/backend/
COPY meta/image/dev/Caddyfile.dev /etc/caddy/Caddyfile
COPY meta/image/dev/run.dev /app/run
COPY meta/image/dev/supervisord.conf.dev /etc/supervisor/conf.d/supervisord.conf
CMD ["sh", "/app/run"]

FROM backend-base AS final
COPY backend /app/backend
RUN composer install --no-interaction --no-dev --optimize-autoloader --classmap-authoritative
COPY --from=frontend-prod /app/frontend/build /app/static
COPY --from=worker /app/worker/worker /app/worker
COPY meta/image/prod/Caddyfile.prod /etc/caddy/Caddyfile
COPY meta/image/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY meta/image/prod/run.prod /app/run

EXPOSE 80
CMD ["sh", "/app/run"]
