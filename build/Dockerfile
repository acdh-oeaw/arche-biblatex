FROM php:8-apache
# VARIANT must be "production" or "development"
ARG VARIANT
RUN cp "$PHP_INI_DIR/php.ini-$VARIANT" "$PHP_INI_DIR/php.ini"
# install the php-yaml extenstion
RUN apt-get update &&\
    apt-get install -y libyaml-dev &&\
    pecl install yaml &&\
    docker-php-ext-enable yaml &&\
    apt-get clean
COPY docroot /var/www/html
