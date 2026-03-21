FROM dunglas/frankenphp:php8.4.19-bookworm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    && docker-php-ext-install mysqli pdo_mysql mbstring zip gd curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy application files
COPY . /app

# Set working directory
WORKDIR /app

# Install composer dependencies
RUN composer install --optimize-autoloader --no-scripts --no-interaction

# Expose port
EXPOSE 8080

# Start FrankenPHP
CMD ["/start-container.sh"]
