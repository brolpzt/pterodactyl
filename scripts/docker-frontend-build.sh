#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODE="${1:-production}"
NODE_IMAGE="${NODE_IMAGE:-node:22-alpine}"

case "$MODE" in
    production)
        BUILD_CMD="yarn run build:production"
        ;;
    development)
        BUILD_CMD="yarn run build"
        ;;
    *)
        echo "Usage: $0 [production|development]" >&2
        exit 1
        ;;
esac

echo "Building frontend with Docker (${NODE_IMAGE}, mode: ${MODE})..."

docker run --rm \
    -v "${ROOT_DIR}:/app" \
    -w /app \
    "${NODE_IMAGE}" \
    sh -c "yarn install --frozen-lockfile && ${BUILD_CMD}"

echo "Frontend build complete. Assets are in public/assets/"
