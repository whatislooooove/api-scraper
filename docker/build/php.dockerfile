FROM php:8.3-cli-alpine

# Системные зависимости
RUN apk add --no-cache \
        bash \
        curl \
        unzip \
        linux-headers \
        libzip-dev \
        postgresql-dev \
        icu-dev \
        libxml2-dev \
        supervisor \
        cronie \
        autoconf \
        g++ \
        make \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        bcmath \
        intl \
        zip \
        pcntl \
        sockets \
        xml \
        opcache \
    \
    # 🔥 УСТАНОВКА REDIS EXTENSION
    && pecl install redis \
    && docker-php-ext-enable redis \
    \
    && docker-php-source delete \
    && apk del autoconf g++ make

# Composer
COPY --from=composer/composer:latest-bin /composer /usr/local/bin/composer

WORKDIR /var/www

# Composer dependencies
COPY symfony/composer.json ./
COPY symfony/composer.lock ./
RUN composer install --no-autoloader --no-scripts --no-progress

# App sources
COPY ./symfony .

# Cron
COPY docker/conf/cron/root /etc/crontabs/root
RUN chmod 600 /etc/crontabs/root \
 && chown root:root /etc/crontabs/root

# Autoload
RUN composer dump-autoload --optimize

# Permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
