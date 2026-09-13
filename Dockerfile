# Dockerfile — place this in the ROOT of your project (same level as the "backend" folder)

FROM php:8.2-apache

# Install PHP extensions needed for MySQL + SSL connections to Aiven
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache's rewrite module (needed for the front-controller routing)
RUN a2enmod rewrite

# Copy your backend code into the container
COPY backend /var/www/html/backend

# Point Apache's document root at backend/public instead of the default /var/www/html
ENV APACHE_DOCUMENT_ROOT=/var/www/html/backend/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides in the document root
RUN echo '<Directory /var/www/html/backend/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Make sure storage/uploads is writable (used by file upload features)
RUN mkdir -p /var/www/html/backend/storage/uploads \
    && chown -R www-data:www-data /var/www/html/backend/storage

# Render provides a dynamic $PORT — this entrypoint rewrites Apache's config to use it
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
