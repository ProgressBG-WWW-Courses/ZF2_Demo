FROM php:7.4-apache

# Set document root to ZF2 public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Enable Apache rewrite module
RUN a2enmod rewrite

# Point Apache to the public directory
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

# Redirect Apache error log to a file accessible from the host
RUN sed -ri -e 's|ErrorLog .+|ErrorLog /var/www/html/data/php-errors.log|g' \
    /etc/apache2/apache2.conf

# Enable PHP error logging to a file (accessible from host via volume mount)
RUN { \
        echo "display_errors = Off"; \
        echo "log_errors = On"; \
        echo "error_log = /var/www/html/data/php-errors.log"; \
        echo "error_reporting = E_ALL"; \
    } > /usr/local/etc/php/conf.d/error-logging.ini \
    && mkdir -p /var/www/html/data


# Install dependencies for PHP extensions and composer
RUN apt-get update && apt-get install -y libicu-dev unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql intl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Ensure the log file exists at startup (volume mount overwrites build-time data/)
CMD ["sh", "-c", "mkdir -p /var/www/html/data && touch /var/www/html/data/php-errors.log && chown -R www-data:www-data /var/www/html/data && apache2-foreground"]

EXPOSE 80
