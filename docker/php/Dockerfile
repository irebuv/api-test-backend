# Use official PHP-FPM 8.2 base image
FROM php:8.2-fpm

# PHP limits (upload/post/memory)
RUN { \
  echo "upload_max_filesize=50M"; \
  echo "post_max_size=50M"; \
  echo "memory_limit=512M"; \
} > /usr/local/etc/php/conf.d/limits.ini

# System deps + PHP extensions (with GD WebP)
RUN apt-get update && apt-get install -y \
    git zip unzip curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libmagickwand-dev \      
 && pecl install imagick \     
 && docker-php-ext-enable imagick \
 && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) gd \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Workdir
WORKDIR /var/www
