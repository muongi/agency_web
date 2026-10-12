#!/usr/bin/env bash
# =====================================================================
# SUNU Agency — one-shot installer / updater for Rocky Linux 10
# =====================================================================
#
# Installs Apache (httpd) + PHP-FPM 8.3 + MySQL + Composer, clones the
# app from git, creates the database, writes .env, runs migrations and
# seeders, configures SELinux, firewall, vhost and the Laravel scheduler.
#
# Safe to re-run: a second run does `git pull`, composer install,
# migrate and cache clear (an update), and never overwrites .env or the DB.
#
# Offline fallback: if GitHub cannot be reached, the code comes from the
# install package zip (sunu_agency_<date>.zip) instead. Copy it to /tmp/,
# either as the .zip or unzipped. On a first install the package also
# provides the database dump and the uploaded files, even when git works.
#
# Usage (as root on the server):
#   curl -O <raw url of this file>   # or paste it with nano
#   chmod +x install-rocky10.sh
#   sudo ./install-rocky10.sh
#
# Override any setting below via env vars, e.g.:
#   sudo APP_PORT=8888 GIT_BRANCH=sunu-pilot ./install-rocky10.sh
#   sudo USE_PACKAGE=yes ./install-rocky10.sh        # skip git, use the zip only
#   sudo PACKAGE=/root/sunu_agency_X.zip ./install-rocky10.sh
# =====================================================================

set -euo pipefail

# ---------------------------- settings -------------------------------
GIT_REPO="${GIT_REPO:-git@github.com:muongi/agency_web.git}"   # ssh or https URL
GIT_BRANCH="${GIT_BRANCH:-sunu-pilot}"
APP_DIR="${APP_DIR:-/var/www/html/sunu_agency}"
APP_NAME="${APP_NAME:-SUNU Agency}"
APP_PORT="${APP_PORT:-80}"                      # 80, or e.g. 8888 for a side port
SERVER_NAME="${SERVER_NAME:-$(hostname -I | awk '{print $1}')}"
DB_NAME="${DB_NAME:-sunu_agency}"
DB_USER="${DB_USER:-sunu}"
DB_PASS="${DB_PASS:-$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 20)}"
SEED_PILOT="${SEED_PILOT:-yes}"                  # run SunuPilotSeeder on a first install without a DB dump
PACKAGE="${PACKAGE:-/tmp/sunu_agency_20261012_1811}" # install package: folder, or the same path + .zip
USE_PACKAGE="${USE_PACKAGE:-auto}"               # auto = only if git fails, yes = always, no = never
IMPORT_DATA="${IMPORT_DATA:-yes}"                # first install: load DB dump + uploads from the package
# ---------------------------------------------------------------------

log()  { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[warn] %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[error] %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run as root (sudo $0)"
grep -q 'release 10' /etc/rocky-release 2>/dev/null || warn "Not Rocky 10 — continuing anyway"

FIRST_INSTALL=no
[[ -f "$APP_DIR/.env" ]] || FIRST_INSTALL=yes

# ---------------------------- packages -------------------------------
log "Installing system packages"
dnf -y install git rsync unzip curl tar openssl policycoreutils-python-utils cronie \
  httpd mod_ssl \
  php php-cli php-fpm php-common php-mysqlnd php-pdo php-mbstring php-xml \
  php-gd php-zip php-bcmath php-intl php-opcache
# Optional extensions: some are bundled into php-common on newer releases
for pkg in php-process php-sodium; do
  dnf -y install "$pkg" >/dev/null 2>&1 || warn "$pkg not available (likely built in)"
done

# Rocky 10 names the MySQL server package by version; fall back to MariaDB
if ! rpm -q mysql-server mysql8.4-server >/dev/null 2>&1; then
  dnf -y install mysql-server || dnf -y install mysql8.4-server || {
    warn "MySQL package not found, installing MariaDB instead"
    dnf -y install mariadb-server
  }
fi
DB_SERVICE=mysqld
systemctl cat mysqld >/dev/null 2>&1 || DB_SERVICE=mariadb

log "Starting services"
systemctl enable --now "$DB_SERVICE" httpd php-fpm crond

# ---------------------------- install package ------------------------
# Accepts the unzipped folder (with code/ inside, possibly one level down)
# or the zip itself. Sets PKG_DIR, empty when no package is found.
PKG_DIR=""
find_pkg_dir() {
  local d
  for d in "$1" "$1"/*/; do
    if [[ -d "$d/code" ]]; then PKG_DIR="${d%/}"; return 0; fi
  done
  return 1
}
if [[ "$USE_PACKAGE" != no ]]; then
  if [[ -d "$PACKAGE" ]]; then
    find_pkg_dir "$PACKAGE" || true
  fi
  if [[ -z "$PKG_DIR" ]]; then
    ZIP="$PACKAGE"
    [[ "$ZIP" == *.zip ]] || ZIP="$PACKAGE.zip"
    if [[ -f "$ZIP" ]]; then
      log "Extracting install package $ZIP"
      rm -rf /tmp/sunu_pkg && mkdir -p /tmp/sunu_pkg
      unzip -q "$ZIP" -d /tmp/sunu_pkg
      find_pkg_dir /tmp/sunu_pkg || die "$ZIP has no code/ folder"
    fi
  fi
  if [[ -n "$PKG_DIR" ]]; then
    echo "Install package: $PKG_DIR"
  elif [[ "$USE_PACKAGE" == yes ]]; then
    die "USE_PACKAGE=yes but no package at $PACKAGE(.zip)"
  fi
fi

# ---------------------------- git access -----------------------------
# Decides SOURCE=git or SOURCE=package
git_reachable() {
  GIT_SSH_COMMAND="ssh -o BatchMode=yes -o ConnectTimeout=15" \
    timeout 60 git ls-remote "$GIT_REPO" >/dev/null 2>&1
}

SOURCE=git
if [[ "$USE_PACKAGE" == yes ]]; then
  SOURCE=package
elif [[ "$GIT_REPO" == git@* ]]; then
  KEY=/root/.ssh/id_ed25519
  if [[ ! -f "$KEY" ]]; then
    log "Creating SSH deploy key"
    mkdir -p /root/.ssh && chmod 700 /root/.ssh
    ssh-keygen -t ed25519 -N '' -f "$KEY" -C "deploy@$(hostname)" >/dev/null
  fi
  ssh-keyscan -H github.com >> /root/.ssh/known_hosts 2>/dev/null || true
fi

if [[ "$SOURCE" == git ]] && ! git_reachable; then
  if [[ "$GIT_REPO" == git@* ]]; then
    echo
    echo "GitHub not reachable with this server's key. To use git, add this public key to"
    echo "GitHub -> repo Settings -> Deploy keys (read-only):"
    echo "------------------------------------------------------------------------"
    cat /root/.ssh/id_ed25519.pub
    echo "------------------------------------------------------------------------"
  fi
  if [[ -n "$PKG_DIR" ]]; then
    warn "Git unavailable: falling back to install package $PKG_DIR"
    SOURCE=package
  else
    read -rp "Press ENTER once the key is added (or Ctrl+C, copy the zip to /tmp/ and re-run)... "
    git_reachable || die "Still cannot reach $GIT_REPO and no install package at $PACKAGE(.zip)"
  fi
fi

# ---------------------------- code -----------------------------------
mkdir -p "$(dirname "$APP_DIR")"
if [[ "$SOURCE" == git ]]; then
  if [[ -d "$APP_DIR/.git" ]]; then
    log "Updating code from git ($GIT_BRANCH)"
    git config --global --add safe.directory "$APP_DIR"
    git -C "$APP_DIR" fetch origin
    git -C "$APP_DIR" checkout "$GIT_BRANCH"
    git -C "$APP_DIR" reset --hard "origin/$GIT_BRANCH"
  elif [[ -d "$APP_DIR" && -n "$(ls -A "$APP_DIR")" ]]; then
    die "$APP_DIR exists but is not a git checkout (installed from the package?). Re-run with USE_PACKAGE=yes, or move it away."
  else
    log "Cloning $GIT_REPO ($GIT_BRANCH) into $APP_DIR"
    git clone --branch "$GIT_BRANCH" "$GIT_REPO" "$APP_DIR"
    git config --global --add safe.directory "$APP_DIR"
  fi
else
  log "Copying code from install package"
  # Server-only data (.env, uploads, logs, sessions) is excluded so --delete never touches it
  rsync -a --delete \
    --exclude '/.git/' --exclude '/.env' \
    --exclude '/storage/app/' --exclude '/storage/logs/' --exclude '/storage/framework/' \
    --exclude '/bootstrap/cache/*.php' --exclude '/public/storage' \
    "$PKG_DIR/code/" "$APP_DIR/"
fi

cd "$APP_DIR"

# The package ships vendor/, so Composer (and internet) is only needed for git installs
if [[ "$SOURCE" == git ]]; then
  if ! command -v composer >/dev/null 2>&1; then
    log "Installing Composer"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED" == "$ACTUAL" ]] || die "Composer installer checksum mismatch"
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
  fi
  log "Installing PHP dependencies"
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
fi
[[ -f vendor/autoload.php ]] || die "vendor/ missing — composer install failed and no package vendor/"

mkdir -p storage/app/public storage/app/cnss storage/logs \
  storage/framework/{cache/data,sessions,views} bootstrap/cache

# ---------------------------- database + .env ------------------------
if [[ "$FIRST_INSTALL" == yes ]]; then
  log "Creating database $DB_NAME and user $DB_USER"
  mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

  log "Writing .env"
  APP_URL="http://$SERVER_NAME"
  if [[ "$APP_PORT" != 80 ]]; then APP_URL="$APP_URL:$APP_PORT"; fi
  cp .env.example .env
  sed -i \
    -e "s|^APP_NAME=.*|APP_NAME=\"$APP_NAME\"|" \
    -e "s|^APP_ENV=.*|APP_ENV=production|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
    -e "s|^APP_URL=.*|APP_URL=$APP_URL|" \
    -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USER|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" \
    .env
  chmod 640 .env
  php artisan key:generate --force

  # Keep the DB credentials somewhere root-only so they are not lost
  printf 'DB_NAME=%s\nDB_USER=%s\nDB_PASS=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASS" > /root/sunu-db-credentials
  chmod 600 /root/sunu-db-credentials
fi

# ---------------------------- data from package ----------------------
DATA_IMPORTED=no
if [[ "$FIRST_INSTALL" == yes && "$IMPORT_DATA" == yes && -n "$PKG_DIR" ]]; then
  TABLES="$(mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")"
  if [[ -f "$PKG_DIR/database/sunu.sql" && "$TABLES" == 0 ]]; then
    log "Importing database dump into $DB_NAME"
    mysql -uroot "$DB_NAME" < "$PKG_DIR/database/sunu.sql"
    DATA_IMPORTED=yes
  elif [[ "$TABLES" != 0 ]]; then
    warn "$DB_NAME already has $TABLES tables, dump not imported"
  fi
  if [[ -f "$PKG_DIR/uploads/storage_app_public.tar.gz" ]]; then
    log "Restoring uploaded files"
    tar -xzf "$PKG_DIR/uploads/storage_app_public.tar.gz" -C "$APP_DIR/storage/app/"
  fi
fi

# ---------------------------- permissions + SELinux ------------------
log "Setting ownership and SELinux contexts"
chown -R apache:apache "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chmod 640 "$APP_DIR/.env"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

if command -v getenforce >/dev/null && [[ "$(getenforce)" != Disabled ]]; then
  semanage fcontext -a -t httpd_sys_content_t    "$APP_DIR(/.*)?"                 2>/dev/null || \
  semanage fcontext -m -t httpd_sys_content_t    "$APP_DIR(/.*)?"
  semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/storage(/.*)?"         2>/dev/null || \
  semanage fcontext -m -t httpd_sys_rw_content_t "$APP_DIR/storage(/.*)?"
  semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/bootstrap/cache(/.*)?" 2>/dev/null || \
  semanage fcontext -m -t httpd_sys_rw_content_t "$APP_DIR/bootstrap/cache(/.*)?"
  restorecon -RF "$APP_DIR"
  # Outbound HTTPS to the KATO API, and MySQL over TCP
  setsebool -P httpd_can_network_connect on
  setsebool -P httpd_can_network_connect_db on
  if [[ "$APP_PORT" != 80 && "$APP_PORT" != 443 ]]; then
    semanage port -a -t http_port_t -p tcp "$APP_PORT" 2>/dev/null || true
  fi
fi

# ---------------------------- artisan --------------------------------
log "Running migrations and caches"
artisan() { sudo -u apache php "$APP_DIR/artisan" "$@"; }
artisan storage:link --force
artisan migrate --force
artisan db:seed --class=PermissionsSeeder --force
if [[ "$FIRST_INSTALL" == yes ]]; then
  artisan db:seed --class=SunuSuperAdminSeeder --force
  # Pilot demo data only for an empty DB; an imported dump already has the real data
  if [[ "$SEED_PILOT" == yes && "$DATA_IMPORTED" == no ]]; then
    artisan db:seed --class=SunuPilotSeeder --force
  fi
fi
artisan optimize:clear
artisan config:cache
artisan view:cache

# ---------------------------- Apache vhost ---------------------------
log "Configuring Apache vhost on port $APP_PORT"
VHOST=/etc/httpd/conf.d/sunu.conf
{
  if [[ "$APP_PORT" != 80 ]]; then echo "Listen $APP_PORT"; fi
  cat <<CONF
<VirtualHost *:$APP_PORT>
    ServerName $SERVER_NAME
    DocumentRoot $APP_DIR/public

    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog  /var/log/httpd/sunu_error.log
    CustomLog /var/log/httpd/sunu_access.log combined
</VirtualHost>
CONF
} > "$VHOST"

# Bigger uploads (photos, CNSS documents)
cat > /etc/php.d/99-sunu.ini <<INI
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 512M
max_execution_time = 120
INI

httpd -t || die "Apache config test failed"
systemctl restart php-fpm httpd

# ---------------------------- firewall -------------------------------
if systemctl is-active --quiet firewalld; then
  log "Opening port $APP_PORT in firewalld"
  firewall-cmd --permanent --add-port="$APP_PORT/tcp" >/dev/null
  firewall-cmd --reload >/dev/null
fi

# ---------------------------- scheduler ------------------------------
log "Installing Laravel scheduler cron"
echo "* * * * * apache cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/sunu-scheduler
chmod 644 /etc/cron.d/sunu-scheduler

# ---------------------------- done -----------------------------------
log "Done"
echo "URL:        $(grep ^APP_URL= "$APP_DIR/.env" | cut -d= -f2-)"
if [[ "$SOURCE" == git ]]; then
  echo "App dir:    $APP_DIR  (git branch $GIT_BRANCH, commit $(git -C "$APP_DIR" rev-parse --short HEAD))"
else
  echo "App dir:    $APP_DIR  (from package $PKG_DIR)"
fi
if [[ "$DATA_IMPORTED" == yes ]]; then echo "Database:   imported from package dump"; fi
if [[ "$FIRST_INSTALL" == yes ]]; then echo "DB creds:   /root/sunu-db-credentials"; fi
echo "Logs:       $APP_DIR/storage/logs/laravel.log, /var/log/httpd/sunu_error.log"
echo "Update later by re-running this script."
