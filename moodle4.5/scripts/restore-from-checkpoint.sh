#!/bin/bash

echo "Finding available checkpoints..."

# Find timestamps from the database backup files
# The sed commands strip the prefix and suffix to isolate the timestamp
CHECKPOINTS=($(ls backups/moodle-db-backup.*.sql 2>/dev/null | sed -e 's/backups\/moodle-db-backup.//' -e 's/.sql//' | sort -r))

if [ ${#CHECKPOINTS[@]} -eq 0 ]; then
    echo "No checkpoints found in the backups/ directory."
    exit 1
fi

echo "Please select a checkpoint to restore from:"

PS3="Enter a number (or q to quit): "
select TIMESTAMP in "${CHECKPOINTS[@]}" "Quit"; do
    if [[ "$TIMESTAMP" == "Quit" ]]; then
        echo "Restore cancelled."
        exit 0
    fi

    if [[ -n "$TIMESTAMP" ]]; then
        echo "You have selected checkpoint: $TIMESTAMP"
        break
    else
        echo "Invalid selection. Please try again."
    fi
done

read -p "ARE YOU SURE you want to restore checkpoint '$TIMESTAMP'? This will permanently delete the current database and moodledata. (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    echo "Restore cancelled."
    exit 1
fi

# --- Restore Database ---
echo "Restoring PostgreSQL database..."
# Drop and recreate the database to ensure it's clean
docker-compose exec -T moodle-db psql -U moodle -c "DROP DATABASE IF EXISTS moodle;" > /dev/null
docker-compose exec -T moodle-db psql -U moodle -c "CREATE DATABASE moodle WITH OWNER moodle;" > /dev/null
# Restore the database from the backup file
cat backups/moodle-db-backup.$TIMESTAMP.sql | docker-compose exec -T moodle-db psql -U moodle -d moodle
echo "Database restore complete."

# --- Restore Moodle Data ---
echo "Restoring moodledata directory..."
# Clean the moodledata directory
docker-compose exec -T moodle-php sh -c "rm -rf /var/www/moodledata/* && rm -rf /var/www/moodledata/..?* /var/www/moodledata/.[!.]*"
# Extract the backup into the moodledata directory
cat backups/moodledata-backup.$TIMESTAMP.tar.gz | docker-compose exec -T moodle-php tar -xzf - -C /
echo "Moodle data restore complete."

echo "
Checkpoint '$TIMESTAMP' restored successfully."
