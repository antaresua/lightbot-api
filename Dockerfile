FROM php:7.4-fpm

WORKDIR /var/www/html

# Встановлюємо необхідні залежності та розширення
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    unzip \
    cron \
    supervisor \
    && docker-php-ext-install pdo pdo_mysql mbstring zip intl \
    && rm -rf /var/lib/apt/lists/*

# Встановлюємо Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копіюємо тільки файли залежностей та встановлюємо їх
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Створюємо користувача і групу www для застосунку
RUN groupadd -g 1000 www && \
    useradd -u 1000 -ms /bin/bash -g www www

# Копіюємо весь проект і встановлюємо права доступу
COPY --chown=www:www . .

# Копіюємо конфігурацію supervisord
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

# Створюємо директорію для логів і надаємо права
RUN mkdir -p /var/log/supervisor && chown www:www /var/log/supervisor

# Змінюємо користувача на www
USER www

# Відкриваємо порт 9000
EXPOSE 9000

# Додаємо команду запуску
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
