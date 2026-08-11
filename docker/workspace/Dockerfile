FROM composer:2.10.2 AS composer

FROM node:24.19.0-bookworm AS node

FROM php:8.5.5-fpm-bookworm

ARG PNPM_VERSION=11.21.0

ENV COREPACK_HOME=/usr/local/share/corepack

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git \
        libpq-dev \
        libsqlite3-dev \
        unzip \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libpq-dev libsqlite3-dev \
    && apt-get install -y --no-install-recommends libpq5 \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s ../lib/node_modules/corepack/dist/corepack.js /usr/local/bin/corepack \
    && ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && corepack enable \
    && corepack prepare pnpm@${PNPM_VERSION} --activate \
    && groupadd --gid 1000 vscode \
    && useradd --uid 1000 --gid vscode --create-home --shell /bin/bash vscode \
    && sed -ri 's/^user = www-data/user = vscode/; s/^group = www-data/group = vscode/' /usr/local/etc/php-fpm.d/www.conf \
    && mkdir -p /workspaces/gestao-publica-lab /home/vscode/.local/share/pnpm/store \
    && chown -R vscode:vscode /workspaces /home/vscode

WORKDIR /workspaces/gestao-publica-lab
