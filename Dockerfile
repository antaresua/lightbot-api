# Вибираємо базовий образ PHP
FROM php:8.3-fpm

# Встановлюємо залежності
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libxml2-dev \
    libzip-dev git unzip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd intl pdo pdo_mysql zip && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug

# Налаштовуємо робочий каталог
WORKDIR /var/www/html

# Копіюємо код проєкту
COPY . .

# Встановлюємо Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Встановлюємо залежності Symfony
RUN composer install

# Виставляємо порт
EXPOSE 9000

# Запускаємо PHP-FPM
CMD ["php-fpm"]
