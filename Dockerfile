# Вибираємо базовий образ PHP
FROM php:8.3-fpm

# Встановлюємо залежності
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libxml2-dev \
    libzip-dev git unzip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd intl pdo pdo_mysql zip && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug

# Встановлення Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Встановлення Symfony CLI (необов'язково)
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Налаштування робочої директорії
WORKDIR /var/www/backend

# Копіюємо composer файли і встановлюємо залежності
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# Копіюємо інші файли додатку
COPY . .

# Виконуємо залишкову установку залежностей
RUN composer dump-autoload --optimize

# Виставляємо права на кеш та лог директрії
RUN chown -R www-data:www-data var/cache var/log

# Експонуємо порт
EXPOSE 9000

# Запускаємо PHP-FPM сервер
CMD ["php-fpm"]