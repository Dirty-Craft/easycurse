FROM php:8.4.6-fpm

# Install system dependencies and PHP extensions
RUN apt-get update \
    && apt-get full-upgrade -y \
    && apt-get install -y \
        git \
        zip \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libicu-dev \
        libzip-dev \
        graphviz \
        curl \
        gnupg \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 22+ from NodeSource
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd pcntl intl zip \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov intl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create www user
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

RUN sed -i 's/user = www-data/user = www/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/group = www-data/group = www/g' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

# Copy composer files and install dependencies early for better caching
COPY src/composer.lock src/composer.json src/package.json src/package-lock.json /var/www/
RUN composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader || true
RUN npm install || true

# Copy application code
COPY --chown=www:www ./src /var/www

CMD ["php-fpm"]
