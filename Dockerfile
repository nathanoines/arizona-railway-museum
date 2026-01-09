FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and MySQL
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    supervisor \
    default-mysql-server \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    intl \
    mbstring \
    opcache

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Create /arm alias to serve application from /arm/ path
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf \
    && sed -i '/<VirtualHost \*:80>/a\    Alias /arm /var/www/html\n    <Directory /var/www/html>\n        AllowOverride All\n        Require all granted\n    </Directory>' /etc/apache2/sites-available/000-default.conf

# Create MySQL data directory
RUN mkdir -p /var/lib/mysql /var/run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql /var/run/mysqld \
    && chmod 777 /var/run/mysqld

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy MySQL initialization script
COPY docker/init-db.sh /docker-entrypoint-initdb.d/init-db.sh
RUN chmod +x /docker-entrypoint-initdb.d/init-db.sh

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose ports
EXPOSE 80 3306

# Start supervisor to manage both Apache and MySQL
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
