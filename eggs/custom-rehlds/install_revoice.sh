#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# ReVoice Installer
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# ReVoice é um plugin metamod, fica na pasta do jogo
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/revoice_install"

# Permite especificar versão, ou latest
VERSION="${VERSION:-latest}"

echo "================================="
echo " ReVoice Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl unzip ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR" "$GAME_DIR/addons/revoice"

#############################################
# 1. Fetch release info (robusto)
#############################################

GITHUB_API="https://api.github.com/repos/rehlds/ReVoice/releases"

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

# Encontra a URL do zip (filtrando apenas pelo zip)
ASSET_URL=$(echo "$RELEASE_JSON" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep 'revoice.*\.zip' | head -1 | cut -d'"' -f4)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find .zip asset for version: $VERSION"
    exit 1
fi

echo "[INFO] Downloading ReVoice from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/revoice.zip" "$ASSET_URL"

#############################################
# 2. Extract & Install
#############################################

echo "[INFO] Extracting files..."
unzip -q -o "$TMP_DIR/revoice.zip" -d "$TMP_DIR/extracted"

echo "[INFO] Installing to $GAME_DIR..."
# O executável linux fica em `bin/linux32/revoice_mm_i386.so`
if [ -f "$TMP_DIR/extracted/bin/linux32/revoice_mm_i386.so" ]; then
    cp "$TMP_DIR/extracted/bin/linux32/revoice_mm_i386.so" "$GAME_DIR/addons/revoice/"
    echo "[INFO] Successfully copied revoice_mm_i386.so to $GAME_DIR/addons/revoice/."
else
    echo "[ERROR] revoice_mm_i386.so not found inside the zip!"
    exit 1
fi

# O arquivo de configuração (revoice.cfg) fica na raiz do zip
if [ -f "$TMP_DIR/extracted/revoice.cfg" ]; then
    cp "$TMP_DIR/extracted/revoice.cfg" "$GAME_DIR/addons/revoice/"
    echo "[INFO] Successfully copied revoice.cfg to $GAME_DIR/addons/revoice/."
fi

#############################################
# 3. Add to metamod plugins.ini
#############################################

PLUGIN_FILE="$GAME_DIR/addons/metamod/plugins.ini"

echo "[INFO] Configuring Metamod plugins.ini..."
mkdir -p "$(dirname "$PLUGIN_FILE")"
touch "$PLUGIN_FILE"

# Verifica se já está configurado
if ! grep -q "revoice_mm_i386.so" "$PLUGIN_FILE"; then
    echo "linux addons/revoice/revoice_mm_i386.so" >> "$PLUGIN_FILE"
    echo "[INFO] Added ReVoice to plugins.ini"
else
    echo "[INFO] ReVoice is already in plugins.ini"
fi

#############################################
# 4. Cleanup
#############################################

rm -rf "$TMP_DIR"

echo "================================="
echo " ReVoice Installed Successfully!"
echo "================================="
