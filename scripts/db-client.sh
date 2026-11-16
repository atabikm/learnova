#!/bin/sh
set -eu

# Convenience wrapper to run the mysql client against the compose MariaDB service.
# Usage: ./scripts/db-client.sh [mysql-args]

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/docker-compose.yml"

# Default to the root password used in docker-compose.yml
: ${MYSQL_ROOT_PASSWORD:=secret}

docker compose -f "$COMPOSE_FILE" exec -T dbclient mysql -hmariadb -uroot -p"${MYSQL_ROOT_PASSWORD}" "$@"
