#!/bin/bash
# dev.sh - development server script

# Exit on error
set -e

# Path to SQLite database
DB_FILE="./src/data/db.sqlite"

# Delete database if it exists
if [ -f "$DB_FILE" ]; then
    echo "Deleting existing database: $DB_FILE"
    rm "$DB_FILE"
else
    echo "No database to delete at $DB_FILE"
fi

touch src/data/db.sqlite

php src/setup.php

# Start PHP built-in server
echo "Starting PHP server on http://localhost:8000"
php -S 0.0.0.0:8000 -t ./src
