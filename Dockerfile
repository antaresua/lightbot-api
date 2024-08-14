# Dockerfile для Symfony API

FROM php:8.3-fpm

# Встановлення необхідних залежностей
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    zlib1g-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql zip bcmath ctype iconv mbstring xml curl

# Встановлення Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Налаштування робочої директорії
WORKDIR /var/www/backend

# Копіюємо інші файли додатку
COPY . .

# Копіюємо composer файли і встановлюємо залежності з оптимізацією для продакшену
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Налаштування середовища на продакшен
RUN php bin/console cache:clear --env=prod \
    && php bin/console cache:warmup --env=prod

# Виставляємо права на кеш та лог директрії
RUN chown -R www-data:www-data var/cache var/log

# Експонуємо порт
EXPOSE 9000

# Запускаємо PHP-FPM сервер
CMD ["php-fpm"]