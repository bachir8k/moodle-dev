<!-- Note for AI and humans: This document outlines the Docker volume setup and PostgreSQL performance configurations. It is a key file for understanding the environment's architecture. -->

# Configuration Guide

This guide provides details about the configuration of Moodle, Nginx, PostgreSQL, and other services.

## Docker Environment

The `docker-compose.yml` file is configured to use high-performance named volumes for the Moodle source code and data.

*   **`moodle-code`:** A named volume containing the Moodle PHP source code. This code is copied into the volume from the Docker image during the initial build, which provides a significant performance improvement over sharing files from the host machine.
*   **`moodledata`:** A named volume for Moodle's data directory. This directory stores user files, cache, and session data.
*   **`postgres-data`:** A named volume for the PostgreSQL database files.

This setup is optimized for speed in a development environment on Windows or macOS.

## PostgreSQL Performance Tuning

To improve database performance, the following settings have been applied to the PostgreSQL server via `ALTER SYSTEM` commands. These are not stored in the repository but are applied to the database instance itself.

*   **`shared_buffers = '1GB'`**: This allocates 1GB of memory to the database for caching data, significantly speeding up repeated read queries. The default is only 128MB.
*   **`maintenance_work_mem = '256MB'`**: This provides more memory for maintenance tasks like creating indexes and running backups, which speeds up installation and site administration tasks. The default is 64MB.
