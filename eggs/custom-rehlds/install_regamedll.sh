#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# ReGameDLL_CS Installer
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# ReGameDLL_CS é específico para cstrike (ou czero)
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/regamedll_install"

# Permite especificar versão, ou latest
VERSION="${VERSION:-latest}"

echo "================================="
echo " ReGameDLL_CS Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl unzip ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR" "$GAME_DIR/dlls"

#############################################
# 1. Fetch release info (robusto)
#############################################

GITHUB_API="https://api.github.com/repos/rehlds/ReGameDLL_CS/releases"

if [ "$VERSION" = "latest" ]; then
    RELEASE_URL="$GITHUB_API/latest"
else
    RELEASE_URL="$GITHUB_API/tags/$VERSION"
fi

echo "[INFO] Fetching release info from: $RELEASE_URL"
RELEASE_JSON=$(curl -sSL "$RELEASE_URL")

# Verifica erro da API do GitHub
if echo "$RELEASE_JSON" | grep -q '"message"[[:space:]]*:'; then
    MSG=$(echo "$RELEASE_JSON" | grep -o '"message"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | cut -d'"' -f4)
    echo "[ERROR] GitHub API error: $MSG"
    exit 1
fi

RELEASE_TAG=$(echo "$RELEASE_JSON" | grep -o '"tag_name"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | cut -d'"' -f4)
echo "[INFO] Release tag: $RELEASE_TAG"

# Encontra a URL do zip (regamedll-bin-*.zip)
ASSET_URL=$(echo "$RELEASE_JSON" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep 'regamedll-bin.*\.zip' | head -1 | cut -d'"' -f4)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find regamedll-bin-*.zip asset for version: $VERSION"
    exit 1
fi

echo "[INFO] Downloading ReGameDLL_CS from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/regamedll.zip" "$ASSET_URL"

#############################################
# 2. Extract & Install
#############################################

echo "[INFO] Extracting files..."
unzip -q -o "$TMP_DIR/regamedll.zip" -d "$TMP_DIR/extracted"

echo "[INFO] Installing to $GAME_DIR..."
# A pasta alvo dentro do zip para os binários Linux
ZIP_LINUX_DIR="$TMP_DIR/extracted/bin/linux32/cstrike"

if [ -d "$ZIP_LINUX_DIR" ]; then
    # Faz backup do cs.so original se existir e não tiver sido backupeado
    if [ -f "$GAME_DIR/dlls/cs.so" ] && [ ! -f "$GAME_DIR/dlls/cs.so.bak" ]; then
        mv "$GAME_DIR/dlls/cs.so" "$GAME_DIR/dlls/cs.so.bak"
        echo "[INFO] Backed up original cs.so to cs.so.bak"
    fi
    
    # Copia todo o conteúdo de bin/linux32/cstrike (game_init.cfg, dlls/cs.so, etc) para a raiz do jogo
    cp -r "$ZIP_LINUX_DIR/"* "$GAME_DIR/"
    echo "[INFO] Successfully copied ReGameDLL_CS files (including configs) to $GAME_DIR."
else
    echo "[ERROR] 'bin/linux32/cstrike' not found inside the zip!"
    exit 1
fi

#############################################
# 3. Cleanup
#############################################

rm -rf "$TMP_DIR"

echo "================================="
echo " ReGameDLL_CS Installed Successfully!"
echo "================================="
