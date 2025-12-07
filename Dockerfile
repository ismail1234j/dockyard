# Use official PHP 8.1 Apache image (Debian Bullseye base)
FROM php:8.1-apache-bullseye

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data /src /var/www/html/
RUN chmod -R 777 /var/www/html

# Install system dependencies, PHP extensions, and Docker CLI
RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    sudo \
    git \
    unzip \
    zip \
    libsqlite3-dev \
    libicu-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite intl \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list \
    && apt-get update && apt-get install -y --no-install-recommends docker-ce-cli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Add www-data to docker group (make sure host group ID matches 999 if needed)
RUN groupadd -g 999 docker || true && usermod -aG docker www-data

# Create necessary directories before copying files
RUN mkdir -p /var/www/html/data \
    && mkdir -p /var/www/html/logs \
    && chmod 777 /var/www/html/data \
    && chmod 777 /var/www/html/logs

# Create empty database file with proper permissions
RUN touch /var/www/html/data/db.sqlite \
    && chown www-data:www-data /var/www/html/data/db.sqlite \
    && chmod 777 /var/www/html/data/db.sqlite

# Configure passwordless sudo for docker management scripts
RUN echo "www-data ALL=(ALL) NOPASSWD: /var/www/html/manage_containers.sh" > /etc/sudoers.d/manage-containers \
    && chmod 0440 /etc/sudoers.d/manage-containers

# Set proper permissions for executable scripts
RUN chmod +x /var/www/html/manage_containers.sh \
    && chmod +x /var/www/html/extras/entrypoint.sh

RUN git config --global --add safe.directory /var/www/html

# Copy entrypoint script to proper location and make it executable
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose HTTP port
EXPOSE 80

# Start Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
