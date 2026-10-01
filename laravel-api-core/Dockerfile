FROM php:8.4-cli

# 1. Masukkan script installer extension pintar
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# 2. Install dependensi OS dasar (hanya yang diperlukan)
RUN apt-get update && apt-get install -y \
        git unzip zip curl default-mysql-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Install ekstensi PHP (Script ini otomatis menangani dependensi lib-nya dan sangat cepat)
RUN install-php-extensions pdo pdo_mysql zip grpc protobuf

# 4. Copy Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

CMD ["sh", "-c", "until php artisan migrate --force; do echo 'Menunggu database siap...'; sleep 2; done && php artisan serve --host=0.0.0.0 --port=8000"]