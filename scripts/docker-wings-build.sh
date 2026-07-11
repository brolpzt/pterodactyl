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

echo "Building Wings with Docker (${GO_IMAGE}, ${GOOS}/${GOARCH})..."

docker run --rm \
    -v "${WINGS_DIR}:/wings" \
    -v wings-go-mod-cache:/go/pkg/mod \
    -w /wings \
    -e GOTOOLCHAIN=auto \
    -e CGO_ENABLED=0 \
    -e GOOS="${GOOS}" \
    -e GOARCH="${GOARCH}" \
    "${GO_IMAGE}" \
    sh -c "go mod download && go build -buildvcs=false -trimpath -ldflags='-s -w' -o /wings/${OUTPUT_NAME} ."

chmod +x "${OUTPUT_PATH}"

echo "Wings build complete: ${OUTPUT_PATH}"
file "${OUTPUT_PATH}" || true
