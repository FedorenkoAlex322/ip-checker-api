# ============================================
# Stage 1: Composer dependencies
# ============================================
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ============================================
# Stage 2: Application image
# ============================================
FROM php:8.3-fpm AS app

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    unzip \
    libicu-dev \
    && docker-php-ext-install \
        pdo_mysql \
        pcntl \
        bcmath \
        sockets \
        zip \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Copy custom PHP config
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Set working directory
WORKDIR /var/www/html

# Copy application from composer stage
COPY --from=composer-deps /app /var/www/html

# Set permissions for storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Switch to non-root user
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
