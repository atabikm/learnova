#!/bin/sh
set -eu

# Auto installer for development. Behavior:
# - If /var/www/html contains no Moodle code, clone a shallow Moodle 5.2 branch
# - If config.php missing, generate it from env vars and run CLI installer

MOODLE_DIR=/var/www/html
MOODLE_REPO=${MOODLE_REPO:-}
MOODLE_BRANCH=${MOODLE_BRANCH:-MOODLE_502_STABLE}
# wwwroot: prefer explicit WWWROOT, then MOODLE_WWWROOT, else http://localhost
WWWROOT=${WWWROOT:-${MOODLE_WWWROOT:-http://localhost}}
DATAROOT=${DATAROOT:-/var/www/moodledata}
# Database env compatibility: prefer DB* if set, fall back to MOODLE_DATABASE_* names
DBTYPE=${DBTYPE:-mariadb}
DBHOST=${DBHOST:-${MOODLE_DATABASE_HOST:-mariadb}}
DBNAME=${DBNAME:-${MOODLE_DATABASE_NAME:-moodle}}
DBUSER=${DBUSER:-${MOODLE_DATABASE_USER:-moodle}}
DBPASS=${DBPASS:-${MOODLE_DATABASE_PASSWORD:-moodle}}
ADMINUSER=${ADMINUSER:-admin}
ADMINPASS=${ADMINPASS:-LearnovaLMS2026!}
ADMINEMAIL=${ADMINEMAIL:-${MOODLE_ADMIN_EMAIL:-dev@lookmedia.id}}

clone_moodle() {
  echo "[auto-install] Checking for Moodle code in ${MOODLE_DIR}..."
  # Moodle 5 uses public/version.php; accept either layout when checking.
  if [ -f "${MOODLE_DIR}/version.php" ] || [ -f "${MOODLE_DIR}/public/version.php" ]; then
    echo "[auto-install] Moodle appears present, skipping clone"
    return
  fi
  echo "[auto-install] Moodle not found, cloning ${MOODLE_BRANCH} from ${MOODLE_REPO} into temporary location"
  TMPDIR=$(mktemp -d)
  git clone --depth 1 --branch ${MOODLE_BRANCH} ${MOODLE_REPO} "${TMPDIR}"
  echo "[auto-install] Copying core files into ${MOODLE_DIR} (preserving mounts)"
  # Ensure destination exists
  mkdir -p "${MOODLE_DIR}"
  # Copy while avoiding known plugin mountpoints. Adjust as needed.
    rsync -a --links \
      --exclude 'local/learnovareports' \
      --exclude 'blocks/learnovadashboard' \
      --exclude 'mod/learnovaquiz' \
      "${TMPDIR}/" "${MOODLE_DIR}/"
    # chown may fail transiently on files removed/created by rsync (or on
    # host-mounted files). Make this best-effort so the script doesn't abort
    # while copying a large tree.
    chown -R www-data:www-data ${MOODLE_DIR} || true
  rm -rf "${TMPDIR}"
}

# If MOODLE_REPO is empty, fall back to the official Moodle repo so a
# simple `docker compose up` will provision a site out-of-the-box. This keeps
# auto-install convenient for local development while still allowing override
# via the MOODLE_REPO env var.
if [ -z "${MOODLE_REPO}" ]; then
  echo "[auto-install] MOODLE_REPO is empty; falling back to official Moodle repo (https://github.com/moodle/moodle.git)"
  MOODLE_REPO="https://github.com/moodle/moodle.git"
fi

wait_for_db() {
  echo "[auto-install] Waiting for database at ${DBHOST}..."
  # wait-for-it style loop with longer timeout for container init
  attempts=30
  i=0
  while [ $i -lt $attempts ]; do
    i=$((i+1))
    if nc -z ${DBHOST} 3306 >/dev/null 2>&1; then
      echo "[auto-install] Database reachable"
      return 0
    fi
    echo "[auto-install] DB not reachable yet, retrying... (${i}/${attempts})"
    sleep 2
  done
  echo "[auto-install] Timeout waiting for DB" >&2
  return 1
}

create_config() {
  # If a non-empty config.php already exists and appears correct, skip.
  # If it exists but does not set dirroot to public, regenerate it because
  # the installer assumes a public/ webroot layout (Moodle 5.x).
  if [ -s "${MOODLE_DIR}/config.php" ]; then
    if grep -q "dirroot.*public" "${MOODLE_DIR}/config.php" || grep -q "libdir.*public" "${MOODLE_DIR}/config.php"; then
      echo "[auto-install] config.php already exists and appears correct; skipping generation"
      return
    else
      echo "[auto-install] config.php exists but does not point to public dir; regenerating"
    fi
  fi
  mkdir -p "${MOODLE_DIR}"
  # write config atomically to avoid races with other processes
  TMPCFG=$(mktemp)
  cat > "${TMPCFG}" <<PHP
<?php
unset(
    \$CFG
);
global \$CFG;
\$CFG = new stdClass();
\$CFG->dbtype    = '${DBTYPE}';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = '${DBHOST}';
\$CFG->dbname    = '${DBNAME}';
\$CFG->dbuser    = '${DBUSER}';
\$CFG->dbpass    = '${DBPASS}';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);
\$CFG->wwwroot   = '${WWWROOT}';
\$CFG->dataroot  = '${DATAROOT}';
\$CFG->admin     = '${ADMINUSER}';
\$CFG->directorypermissions = 02777;

// Set dirroot/libdir to the public webroot so CLI includes resolve correctly
\$CFG->dirroot = __DIR__ . '/public';
\$CFG->libdir = \$CFG->dirroot . '/lib';

require_once(\$CFG->libdir . '/setup.php');
PHP
  mv "${TMPCFG}" "${MOODLE_DIR}/config.php"
  # Best-effort chown; host mounts may prevent ownership changes.
  chown www-data:www-data ${MOODLE_DIR}/config.php || true
  echo "[auto-install] Generated config.php"
}

run_installer() {
  echo "[auto-install] Running CLI installer if needed"
  # If install.php still exists redirecting, ensure DB is installed
  # Check whether the config indicates installation. If the installer
  # created config.php but installation wasn't completed, install_database
  # will complete it. If config.php doesn't exist we will create it.
  if [ -f "${MOODLE_DIR}/config.php" ]; then
    echo "[auto-install] config.php exists; will attempt to finish installation if needed"
  fi

  wait_for_db || return 1

  # Ensure dataroot is present and writable by the web user. Host-mounted
  # directories can be owned by root which prevents the installer from
  # writing files. Attempt to create and chown here (best-effort).
  if [ ! -d "${DATAROOT}" ]; then
    mkdir -p "${DATAROOT}" || true
  fi
  chown -R www-data:www-data "${DATAROOT}" || true
  # If chown didn't take effect (host mounts often prevent it), relax perms so
  # the web user can still write to the dataroot. This is acceptable for local
  # development but should NOT be used in production.
  chmod -R 0777 "${DATAROOT}" || true

  # Robust DB connectivity check and self-heal.
  # Create a small PHP script that will try to connect as the configured
  # database user. If it fails and we have MYSQL_ROOT_PASSWORD in the
  # environment, try to create the database and grant privileges as root.
  echo "[auto-install] Checking DB connectivity (as configured DB user)..."
  cat > /tmp/aeon_check_db.php <<'PHP'
<?php
$h = getenv('MOODLE_DATABASE_HOST') ?: getenv('DBHOST') ?: 'mariadb';
$u = getenv('MOODLE_DATABASE_USER') ?: getenv('DBUSER') ?: 'moodle';
$p = getenv('MOODLE_DATABASE_PASSWORD') ?: getenv('DBPASS') ?: 'moodle';
$n = getenv('MOODLE_DATABASE_NAME') ?: getenv('DBNAME') ?: 'moodle';
printf("connect attempt: host=%s user=%s db=%s\n", $h, $u, $n);
mysqli_report(MYSQLI_REPORT_OFF);
$m = @new mysqli($h, $u, $p, $n);
if ($m->connect_errno) { printf("CONNECT_ERR: (%d) %s\n", $m->connect_errno, $m->connect_error); exit(1); }
$res = $m->query('SHOW TABLES');
if ($res === false) { printf("SHOW_ERR: %s\n", $m->error); exit(2); }
printf("DB_OK\n");
exit(0);
PHP

  php /tmp/aeon_check_db.php || true

  RC=$?
  if [ "$RC" -ne 0 ]; then
    if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
      echo "[auto-install] DB connection failed as app user, attempting to create DB/user as root"
      cat > /tmp/aeon_create_db.php <<'PHP'
<?php
$h = getenv('MOODLE_DATABASE_HOST') ?: getenv('DBHOST') ?: 'mariadb';
$root = getenv('MYSQL_ROOT_PASSWORD') ?: getenv('MARIADB_ROOT_PASSWORD') ?: '';
if (empty($root)) { echo "no root password provided\n"; exit(2); }
$m = @new mysqli($h, 'root', $root);
if ($m->connect_errno) { printf("ROOT_CONNECT_ERR: (%d) %s\n", $m->connect_errno, $m->connect_error); exit(3); }
if (!$m->query("CREATE DATABASE IF NOT EXISTS `moodle` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")) {
    printf("CREATE_DB_ERR: %s\n", $m->error);
}
// Create/grant user and privileges. This form will create the user if missing on most MariaDB/MySQL
if (!$m->query("GRANT ALL PRIVILEGES ON `moodle`.* TO 'moodle'@'%' IDENTIFIED BY 'moodle';")) {
    printf("GRANT_ERR: %s\n", $m->error);
}
$m->query('FLUSH PRIVILEGES');
echo "ROOT_DB_SETUP_DONE\n";
exit(0);
PHP

      php /tmp/aeon_create_db.php || true
      echo "[auto-install] Re-checking DB connectivity after root attempt"
      php /tmp/aeon_check_db.php || true
    else
      echo "[auto-install] DB connection failed and no MYSQL_ROOT_PASSWORD available to auto-fix"
    fi
  fi

  # Run installer with display_errors enabled so stack traces surface in container logs
  su -s /bin/sh -c "php -d display_errors=1 -d error_reporting=E_ALL ${MOODLE_DIR}/admin/cli/install.php --non-interactive --agree-license --wwwroot=${WWWROOT} --dataroot=${DATAROOT} --dbtype=${DBTYPE} --dbhost=${DBHOST} --dbname=${DBNAME} --dbuser=${DBUSER} --dbpass='${DBPASS}' --fullname='Learnova Learning Management System' --shortname='LearnovaLMS' --adminuser=${ADMINUSER} --adminpass='${ADMINPASS}' --adminemail='${ADMINEMAIL}'" www-data || true

  su -s /bin/sh -c "php -d display_errors=1 -d error_reporting=E_ALL ${MOODLE_DIR}/admin/cli/install_database.php --adminuser=${ADMINUSER} --adminpass='${ADMINPASS}' --adminemail='${ADMINEMAIL}' --fullname='Learnova Learning Management System' --shortname='LearnovaLMS' --agree-license" www-data || true
}

clone_moodle
create_config
run_installer

# End of script - let the original entrypoint continue (apache)
