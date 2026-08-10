###################################################
FROM node:24.9.0 AS node
FROM composer:2.8.8 AS composer
FROM dunglas/frankenphp:1.10.0-php8.4.15 AS frankenphp
FROM golang:1.25.4 AS golang

###################################################
################  FRONTEND STAGES  ################
###################################################

###################################################
FROM node AS frontend-base
WORKDIR /app/frontend
COPY frontend/package.json frontend/package-lock.json frontend/svelte.config.js frontend/vite.config.ts frontend/tsconfig.json /app/frontend/
RUN npm install

###################################################
FROM frontend-base AS frontend-dev
COPY frontend/src /app/frontend/src
COPY frontend/static /app/frontend/static
CMD ["npm", "run", "dev"]

###################################################
FROM frontend-base AS frontend-prod
COPY frontend/src /app/frontend/src
COPY frontend/static /app/frontend/static
RUN npm run build \
    && find . -maxdepth 1 -not -name build -not -name . -exec rm -rf {} \;

###################################################
################   WORKER STAGES   ################
###################################################

###################################################
FROM golang AS worker-dev
WORKDIR /worker
COPY worker/go.mod worker/go.sum /worker/
RUN go mod download
COPY worker/ /worker/
CMD [ "/worker/worker.dev.run" ]

###################################################
FROM golang AS worker
WORKDIR /app/worker
COPY worker/go.mod worker/go.sum /app/worker/
RUN go mod download
COPY worker/ /app/worker/
RUN go build -o ./worker .

###################################################
################  BACKEND STAGES  #################
###################################################

###################################################
FROM frankenphp AS backend-base
ARG APP_VERSION=0.0.0
ENV APP_VERSION=${APP_VERSION}
WORKDIR /app/backend
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions zip intl pdo_pgsql opcache apcu \
    && apt-get update && apt-get install -y --no-install-recommends supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

###################################################
FROM backend-base AS backend-dev
RUN install-php-extensions pcov
COPY backend/composer.json backend/composer.lock /app/backend/
RUN composer install
COPY backend /app/backend/
COPY meta/image/dev/Caddyfile.dev /etc/caddy/Caddyfile
COPY meta/image/dev/run.dev /app/run
COPY meta/image/dev/supervisord.conf.dev /etc/supervisor/conf.d/supervisord.conf
CMD ["sh", "/app/run"]

###################################################
FROM backend-base AS final
COPY backend /app/backend
RUN composer install --no-interaction --no-dev --optimize-autoloader --classmap-authoritative
COPY --from=frontend-prod /app/frontend/build /app/static
COPY --from=worker /app/worker/worker /app/worker
COPY meta/image/prod/Caddyfile.prod /etc/caddy/Caddyfile
COPY meta/image/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY meta/image/prod/run.prod /app/run

RUN useradd -m -s /bin/sh chef \
    && apt-get install -y --no-install-recommends libcap2-bin \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R chef:chef /app \
    && mkdir -p /data/caddy && chown -R chef:chef /data/caddy \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

USER chef

EXPOSE 80
EXPOSE 443
EXPOSE 25
EXPOSE 587
EXPOSE 53

CMD ["sh", "/app/run"]
