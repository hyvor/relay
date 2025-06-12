FROM oven/bun:1 AS bun
FROM composer:2.8.8 AS composer
FROM dunglas/frankenphp:1.4.4-php8.4 AS frankenphp

FROM bun AS frontend-base
WORKDIR /app/frontend
COPY frontend/package.json frontend/bun.lock frontend/svelte.config.js frontend/vite.config.ts frontend/tsconfig.json /app/frontend/
COPY frontend/src /app/frontend/src
COPY frontend/static /app/frontend/static

FROM frontend-base AS frontend-dev
RUN bun install
CMD ["bun", "run", "dev"]

FROM frankenphp AS backend-base
WORKDIR /app/backend
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions zip intl pdo_pgsql opcache apcu amqp

FROM backend-base AS backend-dev
ENV APP_RUNTIME="Runtime\FrankenPhpSymfony\Runtime"
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && apt install -y symfony-cli
RUN install-php-extensions pcov
COPY backend /app/backend/
COPY meta/image/dev/Caddyfile.dev /etc/caddy/Caddyfile
COPY meta/image/dev/run.dev /app/run
CMD ["sh", "/app/run"]

