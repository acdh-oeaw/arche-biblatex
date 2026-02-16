#!/bin/bash
echo "$PGPASS" > /var/www/.pgpass &&\
    chmod 600 /var/www/.pgpass &&\
    chown www-data:www-data /var/www/.pgpass

# workaround for seboettg/citeproc-php fixing CSL styles repo at an ancient version
cd /tmp &&\
    composer require citation-style-language/styles &&\
    composer require citation-style-language/locales &&\
    rm -fR /var/www/html/vendor/citation-style-language &&\
    mv vendor/citation-style-language/ /var/www/html/vendor/ &&\
    chown -R www-data:www-data /var/www/html/vendor/citation-style-language

docker-php-entrypoint apache2-foreground
