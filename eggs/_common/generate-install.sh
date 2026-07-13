#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EGGS_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
TEMPLATE="${SCRIPT_DIR}/install.template.sh"

generate() {
    local game="$1"
    local apt_packages="$2"
    local post_sync_file="${3:-}"
    local out="${EGGS_DIR}/${game}/install.sh"
    local tmp
    tmp="$(mktemp)"

    mkdir -p "$(dirname "$out")"
    sed \
        -e "s/__GAME__/${game}/g" \
        -e "s/__APT_PACKAGES__/${apt_packages}/g" \
        "${TEMPLATE}" > "$tmp"

    if [ -n "$post_sync_file" ]; then
        awk '
            /__POST_SYNC__/ {
                while ((getline line < postfile) > 0) print line
                close(postfile)
                next
            }
            { print }
        ' postfile="$post_sync_file" "$tmp" > "$out"
    else
        sed '/__POST_SYNC__/d' "$tmp" > "$out"
    fi

    rm -f "$tmp"
    chmod +x "$out"
    echo "Generated ${out}"
}

generate "cod1" "curl"
generate "cod2" "curl unzip wget" "${SCRIPT_DIR}/post-sync-cod2x.sh"
generate "cod4" "curl"
generate "coduo" "curl"
generate "mohaa" "curl"
