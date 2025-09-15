#!/bin/bash

# Generate a timestamp
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")

echo "Creating checkpoint with timestamp: $TIMESTAMP"

# --- Backup Configuration Files ---
echo "Backing up configuration files..."
cp docker-compose.yml backups/configs/docker-compose.yml.$TIMESTAMP
cp nginx/default.conf backups/configs/nginx-default.conf.$TIMESTAMP
cp php/Dockerfile backups/configs/php-Dockerfile.$TIMESTAMP
cp php/custom.ini backups/configs/php-custom.ini.$TIMESTAMP
echo "Configuration files backed up."

# --- Backup Database ---
echo "Backing up PostgreSQL database..."
docker-compose exec -T moodle-db pg_dump -U moodle -d moodle > backups/moodle-db-backup.$TIMESTAMP.sql
echo "Database backup complete."

# --- Backup Moodle Data ---
echo "Backing up moodledata directory..."
docker-compose exec -T moodle-php tar -czf - /var/www/moodledata > backups/moodledata-backup.$TIMESTAMP.tar.gz
echo "Moodle data backup complete."

echo "Checkpoint created successfully."

# --- Enforce Retention Policy ---
echo "Enforcing retention policy (keeping last 3 backups)..."

# Database backups
ls -1t backups/moodle-db-backup.*.sql 2>/dev/null | tail -n +4 | xargs -I {} rm -- {}
echo "Cleaned up old database backups."

# Moodledata backups
ls -1t backups/moodledata-backup.*.tar.gz 2>/dev/null | tail -n +4 | xargs -I {} rm -- {}
echo "Cleaned up old moodledata backups."