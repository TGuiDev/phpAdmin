# syntax=docker/dockerfile:1
FROM php:8.3-fpm-bookworm

ENV ACCEPT_EULA=Y \
    DEBIAN_FRONTEND=noninteractive

# Nginx + supervisor (processo unico rodando web server e php-fpm) e libs
# de build para as extensoes PDO padrao.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        apt-transport-https \
        gnupg2 \
        curl \
        libpq-dev \
        libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Driver ODBC da Microsoft (necessario para sqlsrv/pdo_sqlsrv). Usa o pacote
# oficial "packages-microsoft-prod.deb" (registra o repo + chave GPG do jeito
# atual recomendado pela Microsoft) em vez de "apt-key", que esta
# descontinuado/quebrado nas imagens Debian mais recentes.
RUN curl -sSL -O https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm packages-microsoft-prod.deb \
    && apt-get update \
    && apt-get install -y --no-install-recommends msodbcsql18 unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# https://github.com/mlocati/docker-php-extension-installer -- instala e
# habilita extensoes PHP (compila via pecl quando necessario) de forma
# confiavel, incluindo pdo_sqlsrv.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/install-php-extensions
RUN chmod +x /usr/bin/install-php-extensions \
    && install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite pdo_sqlsrv opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --no-progress \
    && composer clear-cache

COPY . .
RUN mkdir -p storage/sqlite \
    && chown -R www-data:www-data /var/www/html/storage

COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
