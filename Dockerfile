# Use official PHP 8.4 Apache image (Debian Bullseye base)
FROM php:8.4-apache-bullseye

# Set working directory
WORKDIR /var/www/html

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
    && docker-php-ext-install pdo pdo_sqlite intl \
    # Install Docker CLI
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list \
    && apt-get update && apt-get install -y --no-install-recommends docker-ce-cli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Add www-data to docker group (make sure host group ID matches 999 if needed)
RUN groupadd -g 999 docker || true && usermod -aG docker www-data

# Copy application files
COPY . /var/www/html/

# Configure passwordless sudo only for docker script (if using CLI proxy)
RUN echo "www-data ALL=(ALL) NOPASSWD: /var/www/html/docker-minecraft.sh" > /etc/sudoers.d/docker-minecraft \
    && chmod 0440 /etc/sudoers.d/docker-minecraft

# (Optional) Configure Git safe directory
RUN git config --global --add safe.directory /var/www/html

# Create log directory and set permissions
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/logs \
    && chmod +x /var/www/html/docker-minecraft.sh

# Expose HTTP port
EXPOSE 80

COPY extras/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Start Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
