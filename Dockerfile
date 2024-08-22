FROM php:8.3-fpm-alpine

# Встановлення необхідних залежностей
RUN apk add --no-cache \
    bash \
    nano \
    net-tools \
    git \
    unzip \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    zlib-dev \
    curl \
    curl-dev \
    tzdata \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip bcmath ctype mbstring xml curl intl

# Налаштування часового поясу
ENV TZ=Europe/Kyiv
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Налаштовуємо PHP-FPM для прослуховування на всіх інтерфейсах
RUN sed -i 's/^listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf
RUN sed -i 's/^listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/zz-docker.conf

# Встановлення Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Встановлення Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Налаштування робочої директорії
WORKDIR /var/www/html

# Копіюємо файли проєкту
COPY --chown=www-data:www-data . .
# Копіюємо entrypoint
COPY docker/entrypoint.sh /entrypoint.sh

# Зміна користувача
USER www-data

# Встановлюємо залежності проєкту
RUN composer install --no-dev --optimize-autoloader

# Експонуємо порт
EXPOSE 9000

# Запускаємо PHP-FPM сервер
CMD ["/entrypoint.sh"]
