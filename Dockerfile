FROM php:8.3-fpm

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

# Створюємо користувача і групу www для застосунку
RUN groupadd -g 1000 www && \
    useradd -u 1000 -ms /bin/bash -g www www

# Створюємо директорію для логів
RUN mkdir -p /var/log/supervisor

# Копіюємо конфігурацію supervisord
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

# Копіюємо весь проект і встановлюємо права доступу
COPY --chown=www:www . .

# Змінюємо власність на директорію для логів
RUN chown www:www /var/log/supervisor

# Встановлюємо всі залежності для проєкту
USER www
RUN composer install

# Відкриваємо порт 9000
EXPOSE 9000

# Додаємо команду запуску
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
