#!/bin/sh
set -e

# Виконати composer install
composer install --no-dev --optimize-autoloader

# Виконати міграції
php bin/console doctrine:migrations:migrate --no-interaction

# Запустити PHP-FPM
php-fpm
