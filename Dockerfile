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

# Driver ODBC da Microsoft (necessario para sqlsrv/pdo_sqlsrv).
# O repo "11" e usado de proposito sobre a base bookworm (12): evita um
# conflito de pacotes conhecido do repositorio da Microsoft nessa combinacao.
# Ref: https://github.com/microsoft/linux-package-repositories/issues/39
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
    && curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && printf 'Package: unixodbc\nPin: origin "packages.microsoft.com"\nPin-Priority: 100\n' >> /etc/apt/preferences.d/microsoft \
    && printf 'Package: unixodbc-dev\nPin: origin "packages.microsoft.com"\nPin-Priority: 100\n' >> /etc/apt/preferences.d/microsoft \
    && printf 'Package: libodbc1:amd64\nPin: origin "packages.microsoft.com"\nPin-Priority: 100\n' >> /etc/apt/preferences.d/microsoft \
    && printf 'Package: odbcinst\nPin: origin "packages.microsoft.com"\nPin-Priority: 100\n' >> /etc/apt/preferences.d/microsoft \
    && printf 'Package: odbcinst1debian2:amd64\nPin: origin "packages.microsoft.com"\nPin-Priority: 100\n' >> /etc/apt/preferences.d/microsoft \
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
