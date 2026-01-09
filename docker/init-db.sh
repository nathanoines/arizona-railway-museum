#!/bin/bash

# Wait for MySQL to be ready
echo "Waiting for MySQL to start..."
sleep 10

# Check if MySQL is running
if ! mysqladmin ping -h localhost --silent; then
    echo "MySQL is not running yet, starting initialization..."
fi

# Create database and user if they don't exist
mysql -u root <<-EOSQL
    CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD}';
    CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
    GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'localhost';
    GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "Database initialization complete!"
