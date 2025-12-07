#!/bin/sh
# If the socket is mounted at runtime, open it up for www-data
if [ -S /var/run/docker.sock ]; then
  chmod 777 /var/run/docker.sock
fi

# Ensure container management scripts are executable
chmod +x /var/www/html/manage_containers.sh
chmod 777 /var/www/html/manage_containers.sh

# Create logs and data directories with proper permissions
mkdir -p /var/www/html/logs
mkdir -p /var/www/html/data
chmod 777 /var/www/html/logs
chmod 777 /var/www/html/data

# If the database file doesn't exist or is coming from a volume mount, ensure proper permissions
if [ ! -f /var/www/html/data/db.sqlite ] || [ "$(stat -c %U /var/www/html/data/db.sqlite)" != "www-data" ]; then
  touch /var/www/html/data/db.sqlite
  chown www-data:www-data /var/www/html/data/db.sqlite
  chmod 666 /var/www/html/data/db.sqlite
fi

# Set ownership of all files to www-data
chown -R www-data:www-data /var/www/html

# Initialize database schema if needed
su -s /bin/sh -c "php /var/www/html/setup.php" www-data

# Pass through to Apache
exec "$@"
