#!/bin/bash

# ===========================
# Agency Laravel Deploy Script
# ===========================

set -euo pipefail

# Local source directory (where your Laravel project lives)
LOCAL_DIR="/var/www/html/agency/"

# Remote SSH details
REMOTE_USER="root"
REMOTE_HOST="213.136.64.220"
REMOTE_PORT="2223"
REMOTE_DIR="/var/www/html/agency/"

# Print header
echo "==========================================="
echo "Starting Laravel deployment to ${REMOTE_HOST}"
echo "==========================================="

# Step 1: Sync files
echo "Syncing files..."
rsync -avz --progress --delete \
  --exclude ".git" \
  --exclude "node_modules" \
  --exclude "vendor" \
  --exclude ".env" \
  -e "ssh -p ${REMOTE_PORT}" \
  "${LOCAL_DIR}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"

# Step 2: Fix permissions, apply DB updates (migrate + seed), clear Laravel cache remotely
echo "Running migrations, seeders, cache clear, and PHP-FPM restart on remote..."
ssh -p "${REMOTE_PORT}" "${REMOTE_USER}@${REMOTE_HOST}" "
  set -euo pipefail
  chown -R apache:apache '${REMOTE_DIR}'
  cd '${REMOTE_DIR}'
  echo '[deploy] php artisan migrate --force'
  php artisan migrate --force
  echo '[deploy] php artisan db:seed --class=PermissionsSeeder --force'
  php artisan db:seed --class=PermissionsSeeder --force
  echo '[deploy] php artisan db:seed --force'
  php artisan db:seed --force
  echo '[deploy] php artisan optimize:clear'
  php artisan optimize:clear
  echo '[deploy] php artisan view:clear'
  php artisan view:clear
  echo '[deploy] systemctl restart php-fpm'
  systemctl restart php-fpm
"

echo "==========================================="
echo "Agency successfully deployed"
echo "==========================================="
