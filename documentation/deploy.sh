#!/bin/bash
#
# Note for AI and humans: This is the master deployment script for the VPS.
# It automates the entire setup process. It is a key file for understanding the deployment strategy.
#
# This script automates the deployment of the Moodle environment on a fresh Ubuntu server.
#

set -e

# --- Configuration Variables (Edit these) ---
GIT_REPO_SSH_URL="git@github.com:bachir8k/moodle-dev.git" # <-- IMPORTANT: Use the SSH URL from your repo page
DOMAIN="moodle.alphacodes.org"
PROJECT_DIR="/srv/moodle-app" # Directory to clone the project into

# Moodle Admin User (will be created on install)
MOODLE_ADMIN_USER="admin"
MOODLE_ADMIN_PASS=")eHZF6Vy*1A2" # <-- IMPORTANT: Change this to a strong, unique password for production
MOODLE_ADMIN_EMAIL="admin@example.com"

# SMTP Configuration (placeholders)
SMTP_HOST="your.smtp.host"
SMTP_PORT="587"
SMTP_USER="your_smtp_username"
SMTP_PASS="your_smtp_password"
SMTP_SECURITY="tls" # Can be 'tls', 'ssl', or ''

# --- End of Configuration ---


echo "--- Moodle Deployment Script ---"

# 1. Install Prerequisites
echo "\[1/7] Installing Docker, Docker Compose, and Git..."
apt-get update
apt-get install -y docker.io docker-compose-v2 git

# 2. Set up SSH Deploy Key for Git
echo "\[2/7] Setting up SSH Deploy Key..."
if [ ! -f /root/.ssh/id_ed25519_github ]; then
    ssh-keygen -t ed25519 -C "moodle-deploy-key-$(date +'%%Y-%%m-%%d')" -f /root/.ssh/id_ed25519_github -N ""
    echo "-----------------------------------------------------------------"
    echo "ACTION REQUIRED: Add the following SSH key to your GitHub repository's Deploy Keys."
    echo "Go to Your Repo -> Settings -> Deploy Keys -> Add deploy key"
    echo "Give it a title (e.g., 'VPS Deploy Key'), paste the key below, and DO NOT check 'Allow write access'."
    echo "-----------------------------------------------------------------"
    cat /root/.ssh/id_ed25519_github.pub
    echo "-----------------------------------------------------------------"
    read -p "Press [Enter] key after you have added the deploy key to GitHub..."
else
    echo "Deploy key already exists. Skipping generation."
fi

# 3. Clone or Update the Repository
echo "\[3/7] Cloning project repository..."
if [ -d "$PROJECT_DIR" ]; then
    echo "Project directory already exists. Pulling latest changes."
    cd "$PROJECT_DIR"
    git pull
else
    # Configure ssh to use the correct key
    mkdir -p /root/.ssh
    echo "Host github.com\n  IdentityFile /root/.ssh/id_ed25519_github" >> /root/.ssh/config
    chmod 600 /root/.ssh/config
    # Clone the repo
    git clone "$GIT_REPO_SSH_URL" "$PROJECT_DIR"
    cd "$PROJECT_DIR"
fi

# 4. Build and Start Docker Environment
echo "\[4/7] Building and starting Docker containers..."
docker-compose build
docker-compose up -d

echo "Waiting for containers to initialize..."
sleep 15

# 5. Install Moodle
echo "\[5/7] Running Moodle installation..."
docker-compose exec -T moodle-php php admin/cli/install.php \
    --non-interactive \
    --agree-license \
    --dbtype=pgsql \
    --dbhost=moodle-db \
    --dbname=moodle \
    --dbuser=moodle \
    --dbpass='w9zR5@y#sE!vP_r8k' \
    --prefix=mdl_ \
    --wwwroot="https://$DOMAIN" \
    --dataroot=/var/www/moodledata \
    --adminuser="$MOODLE_ADMIN_USER" \
    --adminpass="$MOODLE_ADMIN_PASS" \
    --adminemail="$MOODLE_ADMIN_EMAIL" \
    --fullname="$DOMAIN" \
    --shortname="$DOMAIN"

# 6. Set Permissions and Final Configuration
echo "\[6/7] Setting final permissions and configuration..."
# Fix moodledata permissions
docker-compose exec -T moodle-php chown -R www-data:www-data /var/www/moodledata

# Add reverse proxy and SMTP settings to config.php
CONFIG_PHP_PATH="$PROJECT_DIR/moodle/config.php"
docker-compose exec -T moodle-php bash -c "\
    sed -i \"/require_once/i \\\\\$\\CFG->sslproxy = true;\\\
\" /var/www/html/config.php && \
    sed -i \"/require_once/i \\\\\$\\CFG->smtpuser = \\\"$SMTP_USER\\\"\";\" /var/www/html/config.php && \
    sed -i \"/require_once/i \\\\\$\\CFG->smtppass = \\\"$SMTP_PASS\\\"\";\" /var/www/html/config.php && \
    sed -i \"/require_once/i \\\\\$\\CFG->smtphosts = \\\"$SMTP_HOST:$SMTP_PORT\\\"\";\" /var/www/html/config.php && \
    sed -i \"/require_once/i \\\\\$\\CFG->smtpsecure = \\\"$SMTP_SECURITY\\\"\";\" /var/www/html/config.php && \
    sed -i \"/require_once/i \\\\\$\\CFG->smtpauthtype = \\\"LOGIN\\\"\";\" /var/www/html/config.php
"

# 7. Set up Moodle Cron Job
echo "\[7/7] Setting up Moodle cron job..."
# Add a cron job to run every minute
(crontab -l 2>/dev/null; echo "* * * * * /usr/bin/docker-compose -f $PROJECT_DIR/docker-compose.yml exec -T moodle-php php /var/www/html/admin/cli/cron.php") | crontab -

echo "
--- DEPLOYMENT COMPLETE ---

Your Moodle site should now be accessible at: https://$DOMAIN

Admin Username: $MOODLE_ADMIN_USER

IMPORTANT: Remember to configure your DNS to point $DOMAIN to this server's IP address.
Also, ensure HestiaCP (or your other reverse proxy) is configured to forward traffic to port 9000 on this server.
"
