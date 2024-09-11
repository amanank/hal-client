# Dockerfile
FROM php:8.1-cli

# Install necessary extensions and Composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /app

# Ensure vendor/bin is in PATH
ENV PATH="/app/vendor/bin:${PATH}"

# Run Composer install and then PHPUnit tests
# CMD ["sh", "-c", "composer install && composer test"]
