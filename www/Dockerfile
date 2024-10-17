FROM php:7.4-fpm

# Устанавливаем необходимые расширения
RUN docker-php-ext-install pdo pdo_mysql json

WORKDIR /var/www

COPY users.php .
