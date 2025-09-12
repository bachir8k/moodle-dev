<!-- Note for AI and humans: This document provides the step-by-step guide for setting up the high-performance development environment using Docker named volumes. This is a primary context document. -->

# Installation Guide

This guide provides step-by-step instructions for setting up the local and VPS environments.

## PHP Extensions

The following PHP extensions are required for Moodle and are installed in the `moodle-php` container:

*   gd
*   pdo
*   pdo_pgsql
*   pgsql
*   zip
*   intl
*   soap
*   exif

## Recommended Setup: High-Performance Named Volumes

This setup provides a high-performance development environment by using Docker named volumes, which avoids the file I/O slowness inherent in sharing files from a Windows host.

### Step 1: Modify Docker Configuration

First, modify two files to prepare the environment for using named volumes.

**1. Edit `php/Dockerfile`**

Add the following lines to the end of the `php/Dockerfile`. This copies the Moodle source code into the image itself and sets the correct initial permissions.

```dockerfile
# Copy Moodle source code and set permissions
COPY ./moodle /var/www/html/
RUN chown -R www-data:www-data /var/www/html
```

**2. Edit `docker-compose.yml`**

Update the `build` context for the `moodle-php` service and replace all bind mounts for `moodle` and `moodledata` with named volumes.

Your final `docker-compose.yml` should look like this:

```yaml
services:
  moodle-nginx:
    image: nginx:latest
    container_name: moodle-nginx
    ports:
      - "9000:80"
    volumes:
      - moodle-code:/var/www/html
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - moodle-php

  moodle-php:
    container_name: moodle-php
    build:
      context: .
      dockerfile: ./php/Dockerfile
    volumes:
      - moodle-code:/var/www/html
      - moodledata:/var/www/moodledata
    depends_on:
      - moodle-db
    restart: always

  moodle-db:
    image: postgres:15
    container_name: moodle-db
    volumes:
      - postgres-data:/var/lib/postgresql/data
    environment:
      - POSTGRES_USER=moodle
      - POSTGRES_PASSWORD=w9zR5@y#sE!vP_r8k
      - POSTGRES_DB=moodle
    ports:
      - "5432:5432"
    restart: always

volumes:
  postgres-data:
  moodle-code:
  moodledata:
```

### Step 2: Build and Start the Environment

1.  **IMPORTANT:** Ensure no `config.php` file exists in your local `d:\moodle-dev\moodle` directory before building.
2.  Tear down any old environment and build the new image:
    ```bash
    docker-compose down -v
    docker-compose build
    ```
3.  Start the new environment. Docker will create the named volumes and populate the `moodle-code` volume from the image you just built.
    ```bash
    docker-compose up -d
    ```

### Step 3: Install Moodle

Run the command-line installer. This will create the database tables and the `config.php` file inside the `moodle-code` volume.

```bash
docker-compose exec moodle-php sh -c "sleep 10 && php admin/cli/install.php --non-interactive --agree-license --dbtype=pgsql --dbhost=moodle-db --dbname=moodle --dbuser=moodle --dbpass='YOUR_DB_PASSWORD' --prefix=mdl_ --wwwroot=http://localhost:9000 --dataroot=/var/www/moodledata --adminuser='YOUR_ADMIN_USER' --adminpass='YOUR_ADMIN_PASSWORD' --adminemail='admin@example.com' --fullname='Moodle Site' --shortname='Moodle'"
```
*(Note: Replace password and user placeholders with your desired credentials. The database password is in `docker-compose.yml`)*

### Step 4: Fix Data Directory Permissions

The `moodledata` volume is created by Docker and is owned by `root`. You must change its ownership to allow the Moodle web server to write to it.

```bash
docker-compose exec moodle-php chown -R www-data:www-data /var/www/moodledata
```

### Step 5: Final Configuration

To prevent redirect errors, add the `reverseproxy` setting to the `config.php` file that was created inside the volume. You can do this by connecting to the running container with VS Code's "Dev Containers" extension and editing `/var/www/html/config.php`.

Add this line before the final `require_once` statement:
```php
$CFG->reverseproxy = true;
```

Your site is now ready and configured for high performance.
