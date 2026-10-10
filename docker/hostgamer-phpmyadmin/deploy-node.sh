#!/usr/bin/env bash
# Deploy phpMyAdmin SSO bridge on a game node.
# Usage:
#   export PHPMYADMIN_SSO_SECRET=...
#   export PMA_PUBLIC_HOST=phpmyadmin-node050.hostgamer.net
#   export PMA_MYSQL_HOST=10.8.0.6
#   sudo ./deploy-node.sh /docker/hostgamer-phpmyadmin
set -euo pipefail

DEST="${1:-/docker/hostgamer-phpmyadmin}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

: "${PHPMYADMIN_SSO_SECRET:?Set PHPMYADMIN_SSO_SECRET (same as panel .env)}"
: "${PMA_PUBLIC_HOST:?Set PMA_PUBLIC_HOST e.g. phpmyadmin-node050.hostgamer.net}"
: "${PMA_MYSQL_HOST:?Set PMA_MYSQL_HOST e.g. 10.8.0.6}"

PMA_PORT="${PMA_PORT:-3306}"
REDEEM_URL="${PHPMYADMIN_REDEEM_URL:-https://control.hostgamer.net/api/internal/phpmyadmin/redeem}"
PMA_ABSOLUTE_URI="https://${PMA_PUBLIC_HOST}/"

mkdir -p "$DEST"
cp -f "$SCRIPT_DIR/sso.php" "$DEST/sso.php"
cp -f "$SCRIPT_DIR/config.user.inc.php" "$DEST/config.user.inc.php"
cp -f "$SCRIPT_DIR/login-required.php" "$DEST/login-required.php"
cp -f "$SCRIPT_DIR/docker-compose.example.yml" "$DEST/docker-compose.yml"

cat >"$DEST/.env" <<EOF
PHPMYADMIN_SSO_SECRET=${PHPMYADMIN_SSO_SECRET}
PHPMYADMIN_REDEEM_URL=${REDEEM_URL}
PMA_ABSOLUTE_URI=${PMA_ABSOLUTE_URI}
PMA_HOST=${PMA_MYSQL_HOST}
PMA_PORT=${PMA_PORT}
EOF
chmod 600 "$DEST/.env"

cd "$DEST"
if ! docker image inspect phpmyadmin:5-apache >/dev/null 2>&1; then
  echo "Pulling phpmyadmin:5-apache ..."
  docker pull phpmyadmin:5-apache
fi

docker compose --env-file .env up -d
docker ps --filter name=hostgamer-phpmyadmin --format '{{.Names}} {{.Status}} {{.Ports}}'
curl -sS -o /dev/null -w "local_http:%{http_code}\n" "http://127.0.0.1:8088/" || true

echo
echo "OK. Configure Cloudflare Tunnel public hostname:"
echo "  ${PMA_PUBLIC_HOST} -> http://127.0.0.1:8088"
echo "Panel template must resolve this host (PHPMYADMIN_URL_TEMPLATE=https://phpmyadmin-{node}.hostgamer.net)."
