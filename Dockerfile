FROM node:24.9.0 AS node
FROM composer:2.8.8 AS composer
FROM dunglas/frankenphp:1.10.0-php8.4.15 AS frankenphp
FROM golang:1.25.4 AS golang

FROM node AS frontend-base
WORKDIR /app/frontend
COPY frontend/package.json frontend/package-lock.json frontend/svelte.config.js frontend/vite.config.ts frontend/tsconfig.json /app/frontend/
COPY frontend/src /app/frontend/src
COPY frontend/static /app/frontend/static

FROM frontend-base AS frontend-dev
RUN npm install
CMD ["npm", "run", "dev"]

FROM frontend-base AS frontend-prod
RUN  npm install \
    && npm run build \
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
ARG APP_VERSION=0.0.0
ENV APP_VERSION=${APP_VERSION}
ENV APP_RUNTIME="Runtime\FrankenPhpSymfony\Runtime"
WORKDIR /app/backend
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions zip intl pdo_pgsql opcache apcu
RUN apt update  && apt install -y supervisor

FROM backend-base AS backend-dev
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && apt install -y symfony-cli
RUN install-php-extensions pcov
RUN curl --proto '=https' --tlsv1.2 -sSf https://carthage.software/mago.sh | bash
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
