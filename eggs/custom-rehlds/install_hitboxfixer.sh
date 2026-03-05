#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# Hitbox Fixer Installer for ReHLDS
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# Se HLDS_GAME não estiver setado, usamos cstrike como padrão
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/hitboxfixer_install"

echo "============================================="
echo " Installing Hitbox Fixer..."
echo "============================================="

# Instala dependências silenciosamente
apk add --no-cache curl wget unzip tar ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR" "$GAME_DIR"

# Função utilitária para pegar a URL de release do Github via API
get_github_release_url() {
    local repo=$1
    local filter=$2
    local api_url="https://api.github.com/repos/$repo/releases/latest"
    
    local release_json
    release_json=$(curl -sSL "$api_url")
    
    # Verifica erro da API
    if echo "$release_json" | grep -q '"message"[[:space:]]*:'; then
        local msg
        msg=$(echo "$release_json" | grep -o '"message"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | cut -d'"' -f4)
        echo "[ERROR] GitHub API error on $repo: $msg" >&2
        return 1
    fi
    
    local asset_url
    asset_url=$(echo "$release_json" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep "$filter" | head -1 | cut -d'"' -f4)
    
    if [ -z "$asset_url" ]; then
        echo "[ERROR] Could not find asset matching '$filter' for $repo" >&2
        return 1
    fi
    
    echo "$asset_url"
}

# Prepara arquivo do Metamod
PLUGIN_FILE="$GAME_DIR/addons/metamod/plugins.ini"
mkdir -p "$(dirname "$PLUGIN_FILE")"
touch "$PLUGIN_FILE"

# Função utilitária para add plugin
add_to_plugins_ini() {
    local entry=$1
    if ! grep -q "$entry" "$PLUGIN_FILE"; then
        echo "$entry" >> "$PLUGIN_FILE"
    fi
}

########################################
# 1. Hitbox Fixer
########################################
echo "--- Installing Hitbox Fixer ---"

HF_URL=$(get_github_release_url "Garey27/hitbox_fixer" "hitbox_fix-bin.*\.zip")
curl -sSL -o "$TMP_DIR/hitbox_fix.zip" "$HF_URL"
unzip -q -o "$TMP_DIR/hitbox_fix.zip" -d "$TMP_DIR/hitbox_fix"

if [ -d "$TMP_DIR/hitbox_fix/addons" ]; then
    cp -r "$TMP_DIR/hitbox_fix/addons/"* "$GAME_DIR/addons/"
fi
find "$GAME_DIR/addons/hitboxfixer" -type f -name "*.dll" -delete || true

add_to_plugins_ini "linux addons/hitboxfixer/hitbox_fix_mm_i386.so"

########################################
# Cleanup
########################################
rm -rf "$TMP_DIR"

echo "============================================="
echo " Hitbox Fixer Installed Successfully!"
echo "============================================="
