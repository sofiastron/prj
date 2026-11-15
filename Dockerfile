# Image de démonstration / développement : PHP 8.3 + Apache. Voir INSTALL.md.
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    FallbackResource /index.php\n</Directory>\n' > /etc/apache2/conf-available/symfony.conf \
    && a2enconf symfony

WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p var && chown -R www-data:www-data var

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
