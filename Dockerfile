FROM php:8.2-apache

# System deps for gd + zip (mPDF needs gd; zip is commonly needed too)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mysqli pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# uploads/*/.htaccess (access control on contracts, signatures, documents)
# relies on .htaccess overrides being honored — the base image ships with
# AllowOverride None for the docroot, which silently ignores them.
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Mirror the local dev php.ini limits (mPDF is memory-hungry; contract/
# property-photo uploads need higher limits than the PHP defaults).
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
COPY . /var/www/html/

# The app writes to uploads/ at runtime (property photos, signed contracts,
# signatures, documents). On Render's free tier this directory is NOT
# persisted across deploys/restarts — see docker/README.md.
RUN chown -R www-data:www-data /var/www/html/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
