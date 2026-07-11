#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WINGS_DIR="${ROOT_DIR}/wings"
GO_IMAGE="${GO_IMAGE:-golang:1.25}"
GOOS="${GOOS:-linux}"
GOARCH="${GOARCH:-amd64}"
OUTPUT_NAME="${OUTPUT_NAME:-wings}"
OUTPUT_PATH="${WINGS_DIR}/${OUTPUT_NAME}"

if [[ ! -f "${WINGS_DIR}/go.mod" ]]; then
    echo "Wings source not found at ${WINGS_DIR}" >&2
    exit 1
fi

if [[ -e "${OUTPUT_PATH}" && ! -w "${OUTPUT_PATH}" ]]; then
    echo "Removing previous root-owned binary..."
    docker run --rm -v "${WINGS_DIR}:/wings" alpine:3.20 rm -f "/wings/${OUTPUT_NAME}"
fi

echo "Building Wings with Docker (${GO_IMAGE}, ${GOOS}/${GOARCH})..."

CACHE_MOD="${WINGS_DIR}/.cache/go-mod"
CACHE_BUILD="${WINGS_DIR}/.cache/go-build"
mkdir -p "${CACHE_MOD}" "${CACHE_BUILD}"

docker run --rm \
    --user "$(id -u):$(id -g)" \
    -v "${WINGS_DIR}:/wings" \
    -v "${CACHE_MOD}:/go/pkg/mod" \
    -v "${CACHE_BUILD}:/tmp/go-build" \
    -w /wings \
    -e GOTOOLCHAIN=auto \
    -e CGO_ENABLED=0 \
    -e GOOS="${GOOS}" \
    -e GOARCH="${GOARCH}" \
    -e GOMODCACHE=/go/pkg/mod \
    -e GOCACHE=/tmp/go-build \
    "${GO_IMAGE}" \
    sh -c "go mod download && go build -buildvcs=false -trimpath -ldflags='-s -w' -o /wings/${OUTPUT_NAME} ."

if [[ ! -x "${OUTPUT_PATH}" ]]; then
    chmod +x "${OUTPUT_PATH}"
fi

echo "Wings build complete: ${OUTPUT_PATH}"
file "${OUTPUT_PATH}" || true
