###################################################
# Alias for deppendencies
FROM node:23.11.0 AS node
FROM composer:2.8.8 AS composer
FROM dunglas/frankenphp:1.4.4-php8.4 AS frankenphp

###################################################
################  BACKEND STAGES  #################
###################################################


###################################################
FROM frankenphp AS backend-base

WORKDIR /app/backend

# install php and dependencies
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions zip intl pdo_pgsql opcache apcu


###################################################
FROM backend-base AS backend-dev

ENV APP_RUNTIME="Runtime\FrankenPhpSymfony\Runtime"

# symfony cli
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt install -y symfony-cli
# pcov for coverage
RUN install-php-extensions pcov
COPY backend /app/backend/
COPY meta/image/dev/Caddyfile.dev /etc/caddy/Caddyfile
COPY meta/image/dev/run.dev /app/run
CMD ["sh", "/app/run"]

###################################################
FROM backend-base AS final

ENV APP_RUNTIME="Runtime\FrankenPhpSymfony\Runtime"

RUN apt update && apt install -y supervisor

COPY backend /app/backend

RUN composer install --no-cache --prefer-dist --no-dev --no-scripts --no-progress

COPY --from=frontend-prod /app/frontend/build /app/static
COPY meta/image/prod/Caddyfile.prod /etc/caddy/Caddyfile
COPY meta/image/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY meta/image/prod/run.prod /app/run

EXPOSE 80
CMD ["sh", "/app/run"]
