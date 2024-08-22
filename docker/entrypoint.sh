#!/bin/sh
set -e

# Виконання міграцій
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Очищення і прогрівання кешу
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Генерація ключів для JWT
php bin/console lexik:jwt:generate-keypair --overwrite --env=prod

# Запуск PHP-FPM
exec php-fpm