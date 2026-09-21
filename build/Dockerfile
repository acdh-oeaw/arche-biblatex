FROM php:8.5-apache
# VARIANT must be "production" or "development"
ARG VARIANT
RUN cp "$PHP_INI_DIR/php.ini-$VARIANT" "$PHP_INI_DIR/php.ini"
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions &&\
    install-php-extensions yaml pdo_pgsql intl @composer
COPY docroot /var/www/html
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY healthcheck.sh /usr/local/bin/healthcheck.sh
RUN cd /var/www/html &&\
    composer update -o --no-dev &&\
    chown -R www-data:www-data /var/www/html &&\
    ln -s /usr/local/bin/php /usr/bin/php
EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
