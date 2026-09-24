FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        git unzip zip curl \
        libzip-dev libpng-dev libonig-dev \
        autoconf build-essential zlib1g-dev pkg-config \
        default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install grpc protobuf \
    && docker-php-ext-enable grpc protobuf \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
