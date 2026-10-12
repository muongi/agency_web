#!/bin/bash

set -euo pipefail

REMOTE_USER="muongi"
REMOTE_HOST="213.136.64.220"
REMOTE_PORT="2223"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "Copying muongi-deploy.sudoers to ${REMOTE_USER}@${REMOTE_HOST}..."
scp -P "${REMOTE_PORT}" "${SCRIPT_DIR}/muongi-deploy.sudoers" \
  "${REMOTE_USER}@${REMOTE_HOST}:/tmp/muongi-deploy.sudoers"

echo "Installing sudoers (enter muongi's sudo password when prompted)..."
ssh -tt -p "${REMOTE_PORT}" "${REMOTE_USER}@${REMOTE_HOST}" bash -s <<'REMOTE'
set -euo pipefail
sudo visudo -cf /tmp/muongi-deploy.sudoers
sudo cp /tmp/muongi-deploy.sudoers /etc/sudoers.d/muongi-deploy
sudo chmod 440 /etc/sudoers.d/muongi-deploy
rm -f /tmp/muongi-deploy.sudoers
echo "Installed. Verifying passwordless sudo..."
sudo -n chown -R muongi:apache /var/www/html/agency/
echo "OK — passwordless deploy sudo is active."
REMOTE
