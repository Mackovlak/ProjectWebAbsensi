FROM php:8.3-apache

# libzip-dev is a build-time dependency of the zip extension (needed by
# xlsx_writer.php's use of ZipArchive to generate real .xlsx exports) - it
# also pulls in the runtime libzip shared library the compiled extension
# links against, so it's kept rather than purged after install.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install mysqli zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Composer itself (not the app's dependencies - the repo is bind-mounted at
# runtime, so anything installed into the image's copy of /var/www/html
# would just be shadowed by the host directory). entrypoint.sh runs the
# actual `composer install` against the bind-mounted assets/ on container
# start, using this binary.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
