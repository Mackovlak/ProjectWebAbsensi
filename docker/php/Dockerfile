FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
