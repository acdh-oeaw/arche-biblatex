FROM php:8.2-apache
# VARIANT must be "production" or "development"
ARG VARIANT
RUN cp "$PHP_INI_DIR/php.ini-$VARIANT" "$PHP_INI_DIR/php.ini"
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions &&\
    install-php-extensions yaml @composer
COPY docroot /var/www/html
RUN cd /var/www/html &&\
    composer update -o --no-dev &&\
    chown -R www-data:www-data /var/www/html
EXPOSE 80
