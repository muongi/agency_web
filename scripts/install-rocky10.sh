#!/usr/bin/env bash
# =====================================================================
# SUNU Agency — installer AND updater for Rocky Linux 9 / 10
# =====================================================================
#
# First run  = INSTALL: Apache (httpd) + PHP-FPM + MySQL + Composer, code from
#              GitHub, database, .env, migrations, seeders, SELinux, firewall,
#              vhost, Laravel scheduler.
# Re-run     = UPDATE (when $APP_DIR/.env exists): pulls the branch from GitHub
#              and applies it safely:
#                1. backup  -> /root/sunu-backups/<date>/ (DB dump, code, commit, local edits)
#                2. preview -> new commits, changed files, new migrations; asks to continue
#                3. maintenance mode, code = GitHub, composer (only if composer.lock changed),
#                   migrate, permissions seeder, caches, maintenance off
#              The port and server name of the existing vhost are KEPT (8080 stays 8080).
#              .env, the database, storage/ (uploads, logs) and other cron files are never
#              overwritten. An app installed from the zip package (no .git) is turned into
#              a git checkout in place on the first update.
#
# Rollback   = ROLLBACK=last (or ROLLBACK=/root/sunu-backups/<date>): code back to the
#              commit saved in that backup; add RESTORE_DB=yes to also restore the database
#              dump taken just before that update.
#
# Zip fallback (install): if git cannot be used, the code comes from the install
# package sunu_agency_<date>.zip copied to /tmp/ (zip or unzipped folder). On a first
# install the package also provides the database dump and the uploaded files.
#
# GitHub access: with an ssh URL the script creates /root/.ssh/id_ed25519 and prints the
# public key when GitHub refuses it. Add it once in GitHub -> repo Settings -> Deploy keys
# (read-only), then re-run.
#
# Usage (as root on the server):
#   chmod +x install-rocky10.sh
#   sudo ./install-rocky10.sh                       # install, or update an existing install
#   sudo APP_PORT=8080 ./install-rocky10.sh         # first install on a side port
#   sudo ASSUME_YES=yes ./install-rocky10.sh        # update without the confirmation question
#   sudo ROLLBACK=last ./install-rocky10.sh         # undo the last update (code only)
#   sudo ROLLBACK=last RESTORE_DB=yes ./install-rocky10.sh   # code + database
#
# Other settings (env vars): GIT_REPO, GIT_BRANCH, APP_DIR, SERVER_NAME, DB_NAME, DB_USER,
#   SKIP_BACKUP=yes, KEEP_BACKUPS=10, UPGRADE_PACKAGES=yes (update: re-run the full dnf
#   install), USE_PACKAGE=yes|no|auto, PACKAGE=/path/to/sunu_agency_X.zip, SEED_PILOT=no.
# =====================================================================

set -Eeuo pipefail   # -E: the ERR trap (update rollback hint) also fires inside functions

# ---------------------------- settings -------------------------------
APP_PORT_GIVEN="${APP_PORT+yes}"                 # did the caller set it? (update keeps the vhost's)
SERVER_NAME_GIVEN="${SERVER_NAME+yes}"
GIT_REPO="${GIT_REPO:-git@github.com:muongi/agency_web.git}"   # ssh or https URL (or a local path)
GIT_BRANCH="${GIT_BRANCH:-sunu-pilot}"
APP_DIR="${APP_DIR:-/var/www/html/sunu_agency}"
APP_NAME="${APP_NAME:-SUNU Agency}"
APP_PORT="${APP_PORT:-80}"                      # install default; update reads the existing vhost
SERVER_NAME="${SERVER_NAME:-$(hostname -I 2>/dev/null | awk '{print $1}')}"
DB_NAME="${DB_NAME:-sunu_agency}"
DB_USER="${DB_USER:-sunu}"
DB_PASS="${DB_PASS:-$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 20)}"
SEED_PILOT="${SEED_PILOT:-yes}"                  # run SunuPilotSeeder on a first install without a DB dump
PACKAGE="${PACKAGE:-}"                           # install package zip or folder; empty = newest /tmp/sunu_agency_*
USE_PACKAGE="${USE_PACKAGE:-auto}"               # auto = only if git fails, yes = always, no = never
IMPORT_DATA="${IMPORT_DATA:-yes}"                # first install: load DB dump + uploads from the package
ASSUME_YES="${ASSUME_YES:-no}"                   # update: do not ask before applying
SKIP_BACKUP="${SKIP_BACKUP:-no}"
KEEP_BACKUPS="${KEEP_BACKUPS:-10}"
BACKUP_ROOT="${BACKUP_ROOT:-/root/sunu-backups}"
UPGRADE_PACKAGES="${UPGRADE_PACKAGES:-no}"
ROLLBACK="${ROLLBACK:-}"
RESTORE_DB="${RESTORE_DB:-no}"
VHOST="${VHOST:-/etc/httpd/conf.d/sunu.conf}"
# ---------------------------------------------------------------------

log()  { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[warn] %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[error] %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run as root (sudo $0)"

# sudo on Rocky resets PATH to secure_path, which leaves out /usr/local/bin (where Composer goes)
export PATH="/usr/local/bin:$PATH"
COMPOSER_BIN=/usr/local/bin/composer
grep -qE 'release (9|10)' /etc/rocky-release 2>/dev/null || warn "Not Rocky 9/10 — continuing anyway"

MODE=install
[[ -f "$APP_DIR/.env" ]] && MODE=update

artisan() { sudo -u apache php "$APP_DIR/artisan" "$@"; }

# Value of KEY in .env (quotes and CR removed)
env_get() {
  local v
  v="$(grep -E "^$1=" "$APP_DIR/.env" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '\r')"
  v="${v%\"}"; v="${v#\"}"; v="${v%\'}"; v="${v#\'}"
  printf '%s' "$v"
}

# mysql / mysqldump with the app's credentials from .env (root socket as fallback)
db_client() {
  local bin="$1"; shift
  local host port name user pass
  host="$(env_get DB_HOST)"; port="$(env_get DB_PORT)"; name="$(env_get DB_DATABASE)"
  user="$(env_get DB_USERNAME)"; pass="$(env_get DB_PASSWORD)"
  if [[ -n "$user" ]]; then
    MYSQL_PWD="$pass" "$bin" -h"${host:-127.0.0.1}" -P"${port:-3306}" -u"$user" "$@" "$name"
  else
    "$bin" -uroot "$@" "$name"
  fi
}

current_commit() { git -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || echo none; }

# ---------------------------- rollback --------------------------------
if [[ -n "$ROLLBACK" ]]; then
  [[ "$MODE" == update ]] || die "ROLLBACK needs an installed app ($APP_DIR/.env missing)"
  if [[ "$ROLLBACK" == last ]]; then
    ROLLBACK="$(ls -1d "$BACKUP_ROOT"/20* 2>/dev/null | tail -1 || true)"
  fi
  [[ -n "$ROLLBACK" && -d "$ROLLBACK" ]] || die "Backup folder not found (looked in $BACKUP_ROOT)"
  OLD_COMMIT="$(cat "$ROLLBACK/commit.txt" 2>/dev/null || echo none)"
  log "Rolling back from $ROLLBACK (commit $OLD_COMMIT, DB restore: $RESTORE_DB)"
  git config --global --add safe.directory "$APP_DIR" || true
  artisan down --retry=60 || true
  if [[ "$OLD_COMMIT" != none ]] && git -C "$APP_DIR" cat-file -e "$OLD_COMMIT^{commit}" 2>/dev/null; then
    git -C "$APP_DIR" reset --hard "$OLD_COMMIT"
  elif [[ -f "$ROLLBACK/code.tgz" ]]; then
    tar -xzf "$ROLLBACK/code.tgz" -C "$APP_DIR"
  else
    die "No commit and no code.tgz in $ROLLBACK"
  fi
  if [[ -x "$COMPOSER_BIN" ]]; then
    (cd "$APP_DIR" && COMPOSER_ALLOW_SUPERUSER=1 php "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction) \
      || warn "composer install failed; vendor/ left as it was"
  fi
  if [[ "$RESTORE_DB" == yes ]]; then
    [[ -f "$ROLLBACK/db.sql.gz" ]] || die "No db.sql.gz in $ROLLBACK"
    log "Restoring database from $ROLLBACK/db.sql.gz"
    # Exact restore: drop every table first (tables created by the failed update would stay otherwise)
    tables="$(db_client mysql -N -e 'SHOW TABLES')"
    if [[ -n "$tables" ]]; then
      { echo 'SET FOREIGN_KEY_CHECKS=0;'
        while read -r t; do printf 'DROP TABLE IF EXISTS `%s`;\n' "$t"; done <<< "$tables"
        echo 'SET FOREIGN_KEY_CHECKS=1;'; } | db_client mysql
    fi
    gunzip -c "$ROLLBACK/db.sql.gz" | db_client mysql
  fi
  chown -R apache:apache "$APP_DIR"
  command -v restorecon >/dev/null && restorecon -RF "$APP_DIR" || true
  artisan optimize:clear
  artisan config:cache
  artisan view:cache
  artisan up
  log "Rollback done: code at $(current_commit)$([[ "$RESTORE_DB" == yes ]] && echo ', database restored')"
  exit 0
fi

log "Mode: $MODE  (app $APP_DIR, branch $GIT_BRANCH, current commit $(current_commit))"

# ---------------------------- packages -------------------------------
install_all_packages() {
  # Minimal images ship curl-minimal, which conflicts with the curl package: only add curl if missing
  local curl_pkg=curl
  command -v curl >/dev/null && curl_pkg=""
  # shellcheck disable=SC2086
  dnf -y install git rsync unzip $curl_pkg tar gzip openssl policycoreutils-python-utils cronie \
    httpd mod_ssl \
    php php-cli php-fpm php-common php-mysqlnd php-pdo php-mbstring php-xml \
    php-gd php-zip php-bcmath php-intl php-opcache
  # Optional extensions: some are bundled into php-common on newer releases
  local pkg
  for pkg in php-process php-sodium; do
    dnf -y install "$pkg" >/dev/null 2>&1 || warn "$pkg not available (likely built in)"
  done
  # Rocky 10 names the MySQL server package by version; fall back to MariaDB
  if ! rpm -q mysql-server mysql8.4-server mariadb-server >/dev/null 2>&1; then
    dnf -y install mysql-server || dnf -y install mysql8.4-server || {
      warn "MySQL package not found, installing MariaDB instead"
      dnf -y install mariadb-server
    }
  fi
}
if [[ "$MODE" == install || "$UPGRADE_PACKAGES" == yes ]]; then
  log "Installing system packages"
  install_all_packages
else
  # Update: only what the update itself needs and is missing (no PHP upgrade by surprise)
  missing=()
  for tool in git rsync unzip curl tar gzip mysqldump; do
    command -v "$tool" >/dev/null || missing+=("$tool")
  done
  if (( ${#missing[@]} )); then
    log "Installing missing tools: ${missing[*]}"
    pkgs=()
    for tool in "${missing[@]}"; do
      case "$tool" in mysqldump) pkgs+=(mysql);; *) pkgs+=("$tool");; esac
    done
    dnf -y install "${pkgs[@]}" || dnf -y install mariadb || die "Could not install: ${missing[*]}"
  fi
fi

DB_SERVICE=mysqld
systemctl cat mysqld >/dev/null 2>&1 || DB_SERVICE=mariadb
log "Starting services"
systemctl enable --now "$DB_SERVICE" httpd php-fpm crond

# ---------------------------- port / server name (update keeps them) --
if [[ "$MODE" == update ]]; then
  if [[ -f "$VHOST" ]]; then
    if [[ -z "$APP_PORT_GIVEN" ]]; then
      APP_PORT="$(grep -oE '<VirtualHost \*:[0-9]+>' "$VHOST" | head -1 | grep -oE '[0-9]+' || true)"
      [[ -n "$APP_PORT" ]] || die "Cannot read the port from $VHOST; re-run with APP_PORT=<port>"
    fi
    if [[ -z "$SERVER_NAME_GIVEN" ]]; then
      SERVER_NAME="$(awk '/ServerName/ {print $2; exit}' "$VHOST")"
    fi
  elif [[ -z "$APP_PORT_GIVEN" ]]; then
    die "No $VHOST on this installed app; re-run with APP_PORT=<port> (e.g. 8080) so the port is not reset to 80"
  fi
  echo "Port $APP_PORT, server name $SERVER_NAME (kept from the existing install)"
fi

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
  if [[ -z "$PACKAGE" ]]; then
    # Newest /tmp/sunu_agency_* zip or folder
    PACKAGE="$(ls -1dt /tmp/sunu_agency_*.zip /tmp/sunu_agency_*/ 2>/dev/null | head -1 || true)"
    PACKAGE="${PACKAGE%/}"
  fi
  if [[ -n "$PACKAGE" && -d "$PACKAGE" ]]; then
    find_pkg_dir "$PACKAGE" || true
  fi
  if [[ -z "$PKG_DIR" && -n "$PACKAGE" ]]; then
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
    echo "Install package found: $PKG_DIR"
  elif [[ "$USE_PACKAGE" == yes ]]; then
    die "USE_PACKAGE=yes but no install package found (copy sunu_agency_*.zip to /tmp/)"
  else
    echo "No install package in /tmp/ (git only, no zip fallback)"
  fi
fi

# Switch to the zip after a git failure, or stop when there is no zip
fallback_to_package() {
  if [[ -n "$PKG_DIR" ]]; then
    warn "$1 -> falling back to install package $PKG_DIR"
    SOURCE=package
  else
    die "$1, and no install package in /tmp/. Copy sunu_agency_*.zip to /tmp/ and re-run."
  fi
}

# ---------------------------- git access -----------------------------
# Functions called from `if` run without set -e, so each step has `|| return 1`
export GIT_SSH_COMMAND="ssh -o BatchMode=yes -o ConnectTimeout=15"
repo_reachable()  { timeout 60 git ls-remote "$GIT_REPO" >/dev/null 2>&1; }
branch_exists()   { timeout 60 git ls-remote --exit-code --heads "$GIT_REPO" "$GIT_BRANCH" >/dev/null 2>&1; }

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

if [[ "$SOURCE" == git ]] && ! repo_reachable; then
  if [[ "$GIT_REPO" == git@* ]]; then
    echo
    echo "GitHub not reachable with this server's key. To use git, add this public key to"
    echo "GitHub -> repo Settings -> Deploy keys (read-only):"
    echo "------------------------------------------------------------------------"
    cat /root/.ssh/id_ed25519.pub
    echo "------------------------------------------------------------------------"
  fi
  if [[ "$MODE" == update ]]; then
    die "Cannot reach $GIT_REPO: add the key above, then re-run (nothing was changed)"
  elif [[ -n "$PKG_DIR" ]]; then
    fallback_to_package "Cannot reach $GIT_REPO"
  else
    read -rp "Press ENTER once the key is added (or Ctrl+C, copy the zip to /tmp/ and re-run)... "
    repo_reachable || die "Still cannot reach $GIT_REPO and no install package in /tmp/"
  fi
fi
if [[ "$SOURCE" == git ]] && ! branch_exists; then
  [[ "$MODE" == update ]] && die "Branch '$GIT_BRANCH' not found on $GIT_REPO (nothing was changed)"
  fallback_to_package "Branch '$GIT_BRANCH' not found on $GIT_REPO"
fi

# ---------------------------- update: fetch + preview + backup -------
BACKUP_DIR=""
OLD_COMMIT="$(current_commit)"
NEW_COMMIT=""
CONVERTED=no
IN_MAINTENANCE=no
LOCK_BEFORE=""

on_error() {
  local line="$1" cmd="$2"
  printf '\033[1;31m[error] Update failed (line %s): %s\033[0m\n' "$line" "$cmd" >&2
  if [[ "$IN_MAINTENANCE" == yes ]]; then
    artisan up >/dev/null 2>&1 && echo "Site taken out of maintenance mode." >&2 || true
  fi
  if [[ -n "$BACKUP_DIR" ]]; then
    echo "Backup of the state before this update: $BACKUP_DIR" >&2
    echo "Undo the code:            sudo ROLLBACK=$BACKUP_DIR $0" >&2
    echo "Undo code + database:     sudo ROLLBACK=$BACKUP_DIR RESTORE_DB=yes $0" >&2
  fi
}

if [[ "$MODE" == update && "$SOURCE" == git ]]; then
  trap 'on_error "${BASH_LINENO[0]:-$LINENO}" "$BASH_COMMAND"' ERR
  git config --global --add safe.directory "$APP_DIR"
  cd "$APP_DIR"
  if [[ ! -d .git ]]; then
    # Installed from the zip (or copied): make it a git checkout without touching any file yet
    log "Turning $APP_DIR into a git checkout (installed without git before)"
    git init -q
    git remote add origin "$GIT_REPO"
    CONVERTED=yes
  else
    git remote set-url origin "$GIT_REPO"
  fi
  # The script sets permissions itself (chmod 644/755): do not report mode changes as edits
  git config core.fileMode false
  log "Fetching $GIT_BRANCH from $GIT_REPO"
  git fetch -q origin "+refs/heads/$GIT_BRANCH:refs/remotes/origin/$GIT_BRANCH"
  NEW_COMMIT="$(git rev-parse --short "origin/$GIT_BRANCH")"
  if [[ -f composer.lock ]]; then LOCK_BEFORE="$(md5sum composer.lock | cut -d' ' -f1)"; fi

  if [[ "$CONVERTED" == yes ]]; then
    git reset -q --mixed "origin/$GIT_BRANCH"            # index only: shows how the files differ
    CHANGES="$(git status --porcelain --untracked-files=no)"
    echo "Files on this server that differ from GitHub ($GIT_BRANCH @ $NEW_COMMIT):"
    if [[ -n "$CHANGES" ]]; then echo "$CHANGES" | head -60; else echo "  (none — the code already matches GitHub)"; fi
    NEW_MIGRATIONS=""
  else
    LOCAL_EDITS="$(git status --porcelain --untracked-files=no)"
    if [[ "$OLD_COMMIT" == "$NEW_COMMIT" && -z "$LOCAL_EDITS" ]]; then
      log "Already up to date ($NEW_COMMIT): clearing caches only"
      artisan optimize:clear && artisan config:cache && artisan view:cache
      echo "Nothing else to do."
      exit 0
    fi
    echo "Commits to apply ($OLD_COMMIT -> $NEW_COMMIT):"
    git log --oneline "HEAD..origin/$GIT_BRANCH" | head -40 || true
    echo
    git diff --stat "HEAD" "origin/$GIT_BRANCH" | tail -25 || true
    NEW_MIGRATIONS="$(git diff --name-only --diff-filter=A HEAD "origin/$GIT_BRANCH" -- database/migrations || true)"
    if [[ -n "$LOCAL_EDITS" ]]; then
      warn "Files edited directly on this server (saved in the backup, then replaced by GitHub):"
      echo "$LOCAL_EDITS"
    fi
  fi
  if [[ -n "${NEW_MIGRATIONS:-}" ]]; then
    echo; echo "New migrations (will run):"; echo "$NEW_MIGRATIONS" | sed 's/^/  /'
  fi
  if ! git diff --quiet HEAD "origin/$GIT_BRANCH" -- composer.lock 2>/dev/null; then
    echo "composer.lock changed: PHP dependencies will be updated."
  fi

  if [[ "$ASSUME_YES" != yes && -t 0 ]]; then
    read -rp $'\nApply this update? A backup is taken first. [y/N] ' answer
    if [[ ! "$answer" =~ ^[yYoO] ]]; then
      [[ "$CONVERTED" == yes ]] && rm -rf .git          # leave the server exactly as it was
      echo "Cancelled, nothing changed."
      exit 0
    fi
  fi

  if [[ "$SKIP_BACKUP" != yes ]]; then
    BACKUP_DIR="$BACKUP_ROOT/$(date +%Y%m%d-%H%M%S)"
    log "Backup -> $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR" && chmod 700 "$BACKUP_ROOT" "$BACKUP_DIR"
    if [[ "$CONVERTED" == yes ]]; then echo none > "$BACKUP_DIR/commit.txt"; else echo "$OLD_COMMIT" > "$BACKUP_DIR/commit.txt"; fi
    git diff > "$BACKUP_DIR/local-changes.patch" || true
    git status --porcelain --untracked-files=no > "$BACKUP_DIR/changed-files.txt" || true
    tar -czf "$BACKUP_DIR/code.tgz" -C "$APP_DIR" \
      --exclude=./vendor --exclude=./node_modules --exclude=./storage --exclude=./.git .
    db_client mysqldump --single-transaction --routines --no-tablespaces | gzip > "$BACKUP_DIR/db.sql.gz"
    [[ "$(gunzip -c "$BACKUP_DIR/db.sql.gz" | head -c 200 | wc -c)" -gt 0 ]] || die "Database backup is empty: stopping before any change"
    du -sh "$BACKUP_DIR"/* | sed 's/^/  /'
    # Keep only the newest $KEEP_BACKUPS
    ls -1d "$BACKUP_ROOT"/20* 2>/dev/null | head -n -"$KEEP_BACKUPS" | xargs -r rm -rf
  else
    warn "SKIP_BACKUP=yes: no backup taken"
  fi

  log "Maintenance mode on"
  artisan down --retry=60 || true
  IN_MAINTENANCE=yes
  log "Code -> $GIT_BRANCH @ $NEW_COMMIT"
  git checkout -q -B "$GIT_BRANCH" "origin/$GIT_BRANCH"
  git reset -q --hard "origin/$GIT_BRANCH"
  if [[ "$CONVERTED" == yes ]]; then
    STALE="$(git clean -n -d -e .env -e /vendor -e /storage -e /node_modules -e /public/storage -e /bootstrap/cache || true)"
    if [[ -n "$STALE" ]]; then
      warn "Files no longer in the repo (left in place, delete by hand if unused):"
      echo "$STALE" | head -30
    fi
  fi
fi

# ---------------------------- install: code --------------------------
sync_from_git() {
  git config --global --add safe.directory "$APP_DIR" || return 1
  if [[ -d "$APP_DIR/.git" ]]; then
    log "Updating code from git ($GIT_BRANCH)"
    git -C "$APP_DIR" fetch origin || return 1
    git -C "$APP_DIR" checkout "$GIT_BRANCH" || return 1
    git -C "$APP_DIR" reset --hard "origin/$GIT_BRANCH" || return 1
  else
    log "Cloning $GIT_REPO ($GIT_BRANCH) into $APP_DIR"
    git clone --branch "$GIT_BRANCH" "$GIT_REPO" "$APP_DIR" || return 1
  fi
  git -C "$APP_DIR" config core.fileMode false || return 1
}

mkdir -p "$(dirname "$APP_DIR")"
if [[ "$MODE" == install ]]; then
  if [[ "$SOURCE" == git && ! -d "$APP_DIR/.git" && -d "$APP_DIR" && -n "$(ls -A "$APP_DIR")" ]]; then
    fallback_to_package "$APP_DIR already exists and is not a git checkout (installed from a package before?)"
  fi
  if [[ "$SOURCE" == git ]] && ! sync_from_git; then
    fallback_to_package "git clone/update failed"
  fi
fi

if [[ "$SOURCE" == package ]]; then
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
install_composer_deps() {
  if [[ ! -x "$COMPOSER_BIN" ]]; then
    log "Installing Composer"
    local expected actual
    expected="$(curl -fsSL https://composer.github.io/installer.sig)" || return 1
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');" || return 1
    actual="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")" || return 1
    [[ "$expected" == "$actual" ]] || { warn "Composer installer checksum mismatch"; return 1; }
    php /tmp/composer-setup.php --install-dir="$(dirname "$COMPOSER_BIN")" --filename=composer --quiet || return 1
    rm -f /tmp/composer-setup.php
  fi
  log "Installing PHP dependencies"
  COMPOSER_ALLOW_SUPERUSER=1 php "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction || return 1
}
NEED_COMPOSER=yes
if [[ "$MODE" == update && -f vendor/autoload.php && -n "$LOCK_BEFORE" && -f composer.lock \
      && "$LOCK_BEFORE" == "$(md5sum composer.lock | cut -d' ' -f1)" ]]; then
  NEED_COMPOSER=no
  echo "composer.lock unchanged: PHP dependencies kept"
fi
if [[ "$SOURCE" == git && "$NEED_COMPOSER" == yes ]] && ! install_composer_deps; then
  if [[ -d "$PKG_DIR/code/vendor" ]]; then
    warn "Composer failed -> copying vendor/ from install package"
    rsync -a --delete "$PKG_DIR/code/vendor/" "$APP_DIR/vendor/"
  else
    die "Composer failed and no install package vendor/ to fall back on"
  fi
fi
[[ -f vendor/autoload.php ]] || die "vendor/ missing in $APP_DIR"

mkdir -p storage/app/public storage/app/cnss storage/logs \
  storage/framework/{cache/data,sessions,views} bootstrap/cache

# ---------------------------- database + .env (install only) ---------
if [[ "$MODE" == install ]]; then
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

# ---------------------------- data from package (install only) -------
DATA_IMPORTED=no
if [[ "$MODE" == install && "$IMPORT_DATA" == yes && -n "$PKG_DIR" ]]; then
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
find "$APP_DIR" -path "$APP_DIR/.git" -prune -o -type d -exec chmod 755 {} +
find "$APP_DIR" -path "$APP_DIR/.git" -prune -o -type f -exec chmod 644 {} +
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
artisan storage:link --force
if [[ "$MODE" == update ]]; then
  artisan migrate:status 2>/dev/null | grep -i pending | sed 's/^/  /' || echo "  (no pending migration)"
fi
artisan migrate --force
artisan db:seed --class=PermissionsSeeder --force
if [[ "$MODE" == install ]]; then
  artisan db:seed --class=SunuSuperAdminSeeder --force
  # Pilot demo data only for an empty DB; an imported dump already has the real data
  if [[ "$SEED_PILOT" == yes && "$DATA_IMPORTED" == no ]]; then
    artisan db:seed --class=SunuPilotSeeder --force
  fi
fi
artisan optimize:clear
artisan config:cache
artisan view:cache

# ---------------------------- Apache vhost + PHP ini -----------------
# Written only when the content changes (an update keeps the existing port and name)
VHOST_CONTENT="$({
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
})"
PHP_INI_CONTENT="upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 512M
max_execution_time = 120"

RESTART_WEB=no
if [[ ! -f "$VHOST" || "$(cat "$VHOST")" != "$VHOST_CONTENT" ]]; then
  log "Writing Apache vhost $VHOST (port $APP_PORT)"
  [[ -f "$VHOST" ]] && cp "$VHOST" "$VHOST.bak-$(date +%Y%m%d%H%M%S)"
  printf '%s\n' "$VHOST_CONTENT" > "$VHOST"
  RESTART_WEB=yes
fi
if [[ ! -f /etc/php.d/99-sunu.ini || "$(cat /etc/php.d/99-sunu.ini)" != "$PHP_INI_CONTENT" ]]; then
  printf '%s\n' "$PHP_INI_CONTENT" > /etc/php.d/99-sunu.ini
  RESTART_WEB=yes
fi

httpd -t || die "Apache config test failed"
if [[ "$RESTART_WEB" == yes ]]; then
  systemctl restart php-fpm httpd
else
  systemctl reload php-fpm      # new code: reset OPcache
fi

# ---------------------------- firewall -------------------------------
if systemctl is-active --quiet firewalld; then
  if ! firewall-cmd --list-ports | grep -qw "$APP_PORT/tcp"; then
    log "Opening port $APP_PORT in firewalld"
    firewall-cmd --permanent --add-port="$APP_PORT/tcp" >/dev/null
    firewall-cmd --reload >/dev/null
  fi
fi

# ---------------------------- scheduler ------------------------------
echo "* * * * * apache cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/sunu-scheduler
chmod 644 /etc/cron.d/sunu-scheduler

# ---------------------------- maintenance off ------------------------
if [[ "$IN_MAINTENANCE" == yes ]]; then
  artisan up
  IN_MAINTENANCE=no
  log "Maintenance mode off"
fi
trap - ERR

# ---------------------------- done -----------------------------------
log "Done ($MODE)"
echo "URL:        $(grep ^APP_URL= "$APP_DIR/.env" | cut -d= -f2-)  (vhost port $APP_PORT)"
if [[ "$SOURCE" == git ]]; then
  echo "Code:       $APP_DIR  branch $GIT_BRANCH, commit $(current_commit)$([[ "$MODE" == update ]] && echo "  (was $OLD_COMMIT)")"
else
  echo "Code:       $APP_DIR  (from package $PKG_DIR)"
fi
if [[ "$DATA_IMPORTED" == yes ]]; then echo "Database:   imported from package dump"; fi
if [[ "$MODE" == install ]]; then echo "DB creds:   /root/sunu-db-credentials"; fi
if [[ -n "$BACKUP_DIR" ]]; then
  echo "Backup:     $BACKUP_DIR"
  echo "Undo:       sudo ROLLBACK=$BACKUP_DIR $0   (+ RESTORE_DB=yes for the database)"
fi
echo "Logs:       $APP_DIR/storage/logs/laravel.log, /var/log/httpd/sunu_error.log"
echo "Update later by re-running this script."
