FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql mbstring

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update Apache configuration to allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Outside the web root — not directly accessible via URL
RUN mkdir -p /var/www/storage/uploads \
    && chown -R www-data:www-data /var/www/storage

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]