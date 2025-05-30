FROM node:23.11.0 AS node
FROM composer:2.8.8 AS composer
FROM dunglas/frankenphp:1.4.4-php8.4 AS frankenphp

FROM frankenphp AS backend-base
WORKDIR /app/backend
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions zip intl pdo_pgsql opcache apcu

FROM backend-base AS backend-dev
ENV APP_RUNTIME="Runtime\FrankenPhpSymfony\Runtime"
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && apt install -y symfony-cli
RUN install-php-extensions pcov
COPY backend /app/backend/
COPY meta/image/dev/Caddyfile.dev /etc/caddy/Caddyfile
COPY meta/image/dev/run.dev /app/run
CMD ["sh", "/app/run"]