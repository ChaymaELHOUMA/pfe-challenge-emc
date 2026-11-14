FROM php:8.3-cli-alpine

# Dépendances système et utilitaires
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    linux-headers \
    mysql-client \
    zip

# Extensions PHP indispensables pour Symfony et MySQL
RUN docker-php-ext-install \
    intl \
    opcache \
    pdo_mysql \
    zip

# Configuration OPcache recommandée
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Composer officiel
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dépendances Composer en cache Docker
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Code source
COPY . .

# Finalisation Composer et permissions var/
RUN composer dump-autoload --optimize && \
    mkdir -p var/cache var/log && \
    chmod -R 777 var

EXPOSE 8000

# Script d'initialisation et démarrage
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
