FROM php:8.3-cli-alpine

# Устанавливаем системные зависимости и расширения PHP
RUN apk add --no-cache \
        bash \
        curl \
        unzip \
        linux-headers\
        libzip-dev \
        postgresql-dev \
        icu-dev \
        libxml2-dev \
        supervisor \
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
    && docker-php-source delete

COPY --from=composer/composer:latest-bin /composer /usr/local/bin/composer

WORKDIR /var/www

COPY symfony/composer.json ./
COPY symfony/composer.lock ./
RUN composer install --no-autoloader --no-scripts --no-progress

# Копируем весь исходный код
COPY ./symfony .

# Запускаем скрипты Composer (очистка кэша, генерация автозагрузки)
RUN composer dump-autoload --optimize

# Права доступа. Для Symfony важен доступ на запись в var/
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]