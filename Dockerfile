FROM oven/bun:1 AS bun
FROM composer:2.8.8 AS composer
FROM dunglas/frankenphp:1.4.4-php8.4 AS frankenphp
FROM golang:1.23.4 AS golang

ARG APP_VERSION=0.0.0
ENV APP_VERSION=${APP_VERSION}
ARG CHOWN=cadet:cadet

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
WORKDIR /app/backend
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
# add user, install extensions, supervisor, clean up
RUN useradd -m cadet \
    && install-php-extensions zip intl pdo_pgsql opcache apcu \
    && apt update \
    && apt install -y --no-install-recommends supervisor \
    && rm -rf /var/lib/apt/lists/* \
    && chown -R cadet:cadet /app
EXPOSE 8080
CMD ["sh", "/app/run"]

FROM backend-base AS backend-dev
RUN install-php-extensions pcov
USER cadet
COPY --chown=$CHOWN backend/composer.json backend/composer.lock /app/backend/
RUN composer install
COPY --chown=$CHOWN backend /app/backend/
COPY --chown=$CHOWN meta/image/dev/Caddyfile.dev /app/Caddyfile
COPY --chown=$CHOWN meta/image/dev/run.dev /app/run
COPY --chown=$CHOWN meta/image/dev/supervisord.conf.dev /etc/supervisor/conf.d/supervisord.conf

FROM backend-base AS final
USER cadet
COPY backend /app/backend
RUN composer install --no-interaction --no-dev --optimize-autoloader --classmap-authoritative
COPY --chown=$CHOWN --from=frontend-prod /app/frontend/build /app/static
COPY --chown=$CHOWN --from=worker /app/worker/worker /app/worker
COPY --chown=$CHOWN meta/image/prod/Caddyfile.prod /app/Caddyfile
COPY --chown=$CHOWN meta/image/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY --chown=$CHOWN meta/image/prod/run.prod /app/run
