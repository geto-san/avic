#!/usr/bin/env bash
# Local classroom runner: private database, captured emails, and PHP web server.
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ ${AVIC_ENV:-} == production ]]; then
  echo 'The classroom runner cannot be used in production.' >&2
  exit 1
fi
for program in php mariadb mariadbd mariadb-install-db curl flock; do
  command -v "$program" >/dev/null || { echo "Install $program before running the demo." >&2; exit 1; }
done

# The private directory is outside the website. Only this OS user can read it.
umask 077
runtime="${AVIC_DEMO_DIR:-${XDG_DATA_HOME:-$HOME/.local/share}/avic-class-demo}"
[[ $runtime == /* && $runtime != *"'"* && $runtime != *$'\n'* ]] || { echo 'Use an absolute demo directory without quotes or newlines.' >&2; exit 1; }
mkdir -p "$runtime"
[[ -O "$runtime" && ! -L "$runtime" ]] || { echo 'Unsafe demo directory.' >&2; exit 1; }
chmod 700 "$runtime"
exec 9>"$runtime/run.lock"
flock -n 9 || { echo 'The demo is already running in another terminal.' >&2; exit 1; }
port=${AVIC_DEMO_PORT:-8080}
[[ $port =~ ^[0-9]+$ && $port -ge 1024 && $port -le 65535 ]] || { echo 'Choose a port from 1024 to 65535.' >&2; exit 1; }
php -r '$s = @stream_socket_server("tcp://127.0.0.1:" . $argv[1]); if (!$s) { fwrite(STDERR, "Port is busy. Set AVIC_DEMO_PORT to another port.\n"); exit(1); }' "$port"

db_pid=''
web_pid=''
stop() {
  if [[ -n $web_pid ]]; then kill "$web_pid" 2>/dev/null || true; wait "$web_pid" 2>/dev/null || true; fi
  if [[ -n $db_pid ]]; then kill "$db_pid" 2>/dev/null || true; wait "$db_pid" 2>/dev/null || true; fi
}
trap stop EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ ! -d "$runtime/database/mysql" ]]; then
  mariadb-install-db --no-defaults --datadir="$runtime/database" --auth-root-authentication-method=normal --skip-test-db >"$runtime/install.log" 2>&1
fi
# No TCP listener: this database is accessible only through its private socket.
mariadbd --no-defaults --datadir="$runtime/database" --socket="$runtime/mysql.sock" --skip-networking \
  --pid-file="$runtime/mysql.pid" --log-error="$runtime/database.log" --innodb-buffer-pool-size=64M &
db_pid=$!
ready=0
for attempt in {1..60}; do
  if mariadb --no-defaults --socket="$runtime/mysql.sock" -uroot -e 'SELECT 1' >/dev/null 2>&1; then ready=1; break; fi
  kill -0 "$db_pid" 2>/dev/null || break
  sleep 0.5
done
[[ $ready == 1 ]] || { cat "$runtime/database.log" >&2; exit 1; }

[[ -f "$runtime/password" ]] || php -r 'echo bin2hex(random_bytes(24));' >"$runtime/password"
export AVIC_ENV=development AVIC_DB_HOST=localhost AVIC_DB_SOCKET="$runtime/mysql.sock"
export AVIC_DB_NAME=avic_demo AVIC_DB_USER=avic_demo AVIC_DB_PASS="$(cat "$runtime/password")"
export AVIC_DB_COLLATION=utf8mb4_unicode_ci
export AVIC_UPLOAD_DIR="$runtime/uploads" AVIC_PUBLIC_URL="http://127.0.0.1:$port"
export AVIC_MAIL_FROM=no-reply@localhost.test AVIC_DEMO_SECRET='Demo2026!'
unset AVIC_DB_PORT
mkdir -p "$AVIC_UPLOAD_DIR"
mariadb --no-defaults --socket="$runtime/mysql.sock" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS avic_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'avic_demo'@'localhost' IDENTIFIED BY '$AVIC_DB_PASS';
GRANT ALL ON avic_demo.* TO 'avic_demo'@'localhost';
SQL
php bin/migrate.php
# Seeding again would overwrite students' changes to the sample records.
if [[ ! -f "$runtime/seeded" ]]; then
  php config/db/seed-demo.php --accounts-only
  touch "$runtime/seeded"
fi
touch "$runtime/mail.eml"
php -d "sendmail_path=/bin/cat >> '$runtime/mail.eml'" -S "127.0.0.1:$port" bin/serve-router.php >"$runtime/web.log" 2>&1 &
web_pid=$!
ready=0
for attempt in {1..20}; do
  if curl --fail --silent "$AVIC_PUBLIC_URL/config/api/health.php" >/dev/null; then ready=1; break; fi
  kill -0 "$web_pid" 2>/dev/null || break
  sleep 0.5
done
[[ $ready == 1 ]] || { cat "$runtime/web.log" >&2; exit 1; }
cat <<INFO

AVIC is running at $AVIC_PUBLIC_URL
Password for all demo accounts: Demo2026!
  Claimant: doreen@example.ug
  Adjuster: brian.okot@avic.ug
  Garage:   desk@kigongomotors.ug

Reset emails are captured locally, never sent externally:
  cat $runtime/mail.eml
Server log: $runtime/web.log
Demo data: $runtime (persistent; keep this directory to retain accounts and records)
Press Ctrl+C to stop the website and its database.
INFO
wait "$web_pid"
