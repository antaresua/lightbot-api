# Dockerfile для Symfony API
FROM php:8.3-fpm

# Встановлення необхідних залежностей
RUN apt-get update && apt-get install -y \
    nano \
    net-tools \
    git \
    unzip \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    zlib1g-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    && docker-php-ext-install pdo pdo_mysql zip bcmath ctype iconv mbstring xml curl intl

# Встановлення Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Налаштування робочої директорії
WORKDIR /var/www/html

# Копіюємо інші файли додатку
COPY . .

# Копіюємо composer файли і встановлюємо залежності з оптимізацією для продакшену
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Запуск міграцій
#RUN php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Налаштування середовища
RUN php bin/console cache:clear --env=prod \
    && php bin/console cache:warmup --env=prod \
    && php bin/console lexik:jwt:generate-keypair

# Виставляємо права на кеш та лог директрії
RUN chown -R www-data:www-data ./

# Налаштовуємо PHP-FPM для прослуховування на всіх інтерфейсах
RUN sed -i 's/^listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf
RUN sed -i 's/^listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/zz-docker.conf

USER www-data

# Експонуємо порт
EXPOSE 9000

# Запускаємо PHP-FPM сервер
CMD ["php-fpm"]