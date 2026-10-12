#!/bin/bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/agency}"
RETAIN_DAYS="${RETAIN_DAYS:-14}"
STAMP="$(date +%F_%H%M)"
FILE="${BACKUP_DIR}/agency-${STAMP}.sql.gz"

mkdir -p "${BACKUP_DIR}"
mysqldump -u root --single-transaction --set-gtid-purged=OFF agency | gzip > "${FILE}"
find "${BACKUP_DIR}" -name 'agency-*.sql.gz' -mtime +"${RETAIN_DAYS}" -delete

echo "Backup written to ${FILE}"
