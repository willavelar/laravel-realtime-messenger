FROM php:8.3-fpm-alpine

# Install system packages required for PHP extensions
RUN apk add --no-cache \
    build-base \
    autoconf \
    zlib-dev \
    libzip-dev \
    oniguruma-dev

# Install core PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    sockets \
    pcntl

# Install PECL extensions
# grpc build can take 5-10 minutes — this is the primary reason for Docker
RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install grpc && docker-php-ext-enable grpc
RUN pecl install protobuf && docker-php-ext-enable protobuf

# Install Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy dependency files first to leverage Docker layer cache
COPY composer.json composer.lock ./

# Install PHP dependencies (production, no dev)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy the rest of the application source
COPY . .

# Run post-install scripts after full source is available
RUN composer run-script post-autoload-dump --no-interaction || true

EXPOSE 9000

CMD ["php-fpm"]
