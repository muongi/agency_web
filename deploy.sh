#!/bin/bash

# ===========================
# Agency Laravel Deploy Script
# ===========================
#
# Deploys as SSH user muongi (SSH key auth). Remote privileged steps use sudo
# with the password from deploy.secrets.sh (gitignored).
#
# One-time setup:
#   cp deploy.secrets.example.sh deploy.secrets.sh
#   chmod 600 deploy.secrets.sh
#   # edit deploy.secrets.sh — set REMOTE_SUDO_PASSWORD
#
# Then: ./deploy.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SECRETS_FILE="${SCRIPT_DIR}/deploy.secrets.sh"

if [[ ! -f "${SECRETS_FILE}" ]]; then
  echo "ERROR: Missing ${SECRETS_FILE}"
  echo "Copy deploy.secrets.example.sh to deploy.secrets.sh and set REMOTE_SUDO_PASSWORD"
  exit 1
fi

# shellcheck source=deploy.secrets.sh
source "${SECRETS_FILE}"

if [[ -z "${REMOTE_SUDO_PASSWORD:-}" ]]; then
  echo "ERROR: REMOTE_SUDO_PASSWORD is empty in deploy.secrets.sh"
  exit 1
fi

# Local source directory (where your Laravel project lives)
LOCAL_DIR="/var/www/html/agency/"

# Remote SSH details
REMOTE_USER="muongi"
REMOTE_HOST="213.136.64.220"
REMOTE_PORT="2223"
REMOTE_DIR="/var/www/html/agency/"

run_remote() {
  ssh -p "${REMOTE_PORT}" "${REMOTE_USER}@${REMOTE_HOST}" \
    "REMOTE_SUDO_PASSWORD=$(printf %q "$REMOTE_SUDO_PASSWORD") REMOTE_DIR=$(printf %q "$REMOTE_DIR") bash -s"
}

# Print header
echo "==========================================="
echo "Starting Laravel deployment to ${REMOTE_HOST}"
echo "==========================================="

# Step 1: Allow muongi to write (previous deploy sets apache:apache ownership)
echo "Preparing remote directory permissions..."
run_remote <<'REMOTE'
set -euo pipefail
sudo_cmd() { printf '%s\n' "$REMOTE_SUDO_PASSWORD" | sudo -S "$@"; }
sudo_cmd chown -R muongi:apache "$REMOTE_DIR"
sudo_cmd chmod -R g+w "$REMOTE_DIR"
REMOTE

# Step 2: Sync files
# Excluded paths are not transferred and not deleted on the remote (--delete).
# User uploads (logos, photos, CNSS documents) and server runtime data live only on the server.
echo "Syncing files..."
rsync -avz --progress --delete \
  --no-owner --no-group \
  --exclude ".git" \
  --exclude "node_modules" \
  --exclude "vendor" \
  --exclude ".env" \
  --exclude "deploy.secrets.sh" \
  --exclude ".cursor" \
  --exclude ".phpunit.result.cache" \
  --exclude "bootstrap/cache/*.php" \
  --exclude "storage/app/public/" \
  --exclude "storage/app/cnss/" \
  --exclude "storage/logs/" \
  --exclude "storage/framework/" \
  --exclude "public/storage" \
  -e "ssh -p ${REMOTE_PORT}" \
  "${LOCAL_DIR}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"

# Step 3: Fix permissions, apply DB updates (migrate + seed), clear Laravel cache remotely
echo "Running migrations, seeders, cache clear, and PHP-FPM restart on remote..."
run_remote <<'REMOTE'
set -euo pipefail
sudo_cmd() { printf '%s\n' "$REMOTE_SUDO_PASSWORD" | sudo -S "$@"; }
sudo_cmd chown -R apache:apache "$REMOTE_DIR"
cd "$REMOTE_DIR"
echo '[deploy] php artisan storage:link'
sudo_cmd -u apache php artisan storage:link --force
echo '[deploy] php artisan migrate --force'
sudo_cmd -u apache php artisan migrate --force
echo '[deploy] php artisan db:seed --class=PermissionsSeeder --force'
sudo_cmd -u apache php artisan db:seed --class=PermissionsSeeder --force
echo '[deploy] php artisan db:seed --force'
sudo_cmd -u apache php artisan db:seed --force
echo '[deploy] php artisan optimize:clear'
sudo_cmd -u apache php artisan optimize:clear
echo '[deploy] php artisan view:clear'
sudo_cmd -u apache php artisan view:clear
echo '[deploy] systemctl restart php-fpm'
sudo_cmd systemctl restart php-fpm
REMOTE

echo "==========================================="
echo "Agency successfully deployed"
echo "==========================================="
