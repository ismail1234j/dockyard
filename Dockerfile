# So this (theoretically) should save memory and compute
FROM php:8.3-fpm-alpine

# Get our deps setup
RUN apk add --no-cache \
    bash \
    curl \
    git \
    sudo \
    sqlite \
    sqlite-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    tzdata \
    nginx \
    docker-cli \
    dos2unix

# PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite intl opcache

# OPCache should hopefully reduce compute
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.memory_consumption=64"; \
  echo "opcache.interned_strings_buffer=8"; \
  echo "opcache.max_accelerated_files=10000"; \
  echo "opcache.validate_timestamps=0"; \
} > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy the app (and set perms)
COPY --chown=www-data:www-data /src /var/www/html/
RUN chmod -R 777 /var/www/html

# Docker perms
RUN addgroup docker 2>/dev/null || true \
  && addgroup www-data docker \
  && echo "www-data ALL=(ALL) NOPASSWD: /var/www/html/manage_containers.sh" > /etc/sudoers.d/manage-containers \
  && chmod 0440 /etc/sudoers.d/manage-containers

# We need db & dirs set
RUN mkdir -p /var/www/html/data /var/www/html/logs \
  && chmod 777 /var/www/html/data /var/www/html/logs \
  && touch /var/www/html/data/db.sqlite \
  && chown www-data:www-data /var/www/html/data/db.sqlite \
  && chmod 777 /var/www/html/data/db.sqlite

# Scripts
RUN chmod +x /var/www/html/private/manage_containers.sh

# Nginx conf
COPY nginx/default.conf /etc/nginx/http.d/default.conf

# Entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && dos2unix /usr/local/bin/entrypoint.sh \
    && dos2unix /var/www/html/private/manage_containers.sh

# Expose HTTP port
EXPOSE 80

# Start Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
