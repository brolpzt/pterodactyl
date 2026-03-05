#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# ReSemiClip Installer
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# ReSemiClip é um plugin metamod, fica na pasta do jogo
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/resemiclip_install"

# Permite especificar versão, ou latest
VERSION="${VERSION:-latest}"

echo "================================="
echo " ReSemiClip Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl unzip ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR" "$GAME_DIR/addons"

#############################################
# 1. Fetch release info (robusto)
#############################################

GITHUB_API="https://api.github.com/repos/rehlds/resemiclip/releases"

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

# Encontra a URL do zip
ASSET_URL=$(echo "$RELEASE_JSON" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep '\.zip' | head -1 | cut -d'"' -f4)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find .zip asset for version: $VERSION"
    exit 1
fi

echo "[INFO] Downloading ReSemiClip from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/resemiclip.zip" "$ASSET_URL"

#############################################
# 2. Extract & Install
#############################################

echo "[INFO] Extracting files..."
unzip -q -o "$TMP_DIR/resemiclip.zip" -d "$TMP_DIR/extracted"

echo "[INFO] Installing to $GAME_DIR..."
# O zip contém uma pasta "addons". Copiamos ela direto para a raiz do mod (ex: cstrike/)
cp -r "$TMP_DIR/extracted/addons" "$GAME_DIR/"

#############################################
# 3. Cleanup Windows DLLs
#############################################

echo "[INFO] Removing Windows DLLs..."
find "$GAME_DIR/addons/resemiclip" -type f -name "*.dll" -delete || true

#############################################
# 4. Add to metamod plugins.ini
#############################################

PLUGIN_FILE="$GAME_DIR/addons/metamod/plugins.ini"

echo "[INFO] Configuring Metamod plugins.ini..."
mkdir -p "$(dirname "$PLUGIN_FILE")"
touch "$PLUGIN_FILE"

# Verifica se já está configurado
if ! grep -q "resemiclip_mm_i386.so" "$PLUGIN_FILE"; then
    echo "linux addons/resemiclip/resemiclip_mm_i386.so" >> "$PLUGIN_FILE"
    echo "[INFO] Added ReSemiClip to plugins.ini"
else
    echo "[INFO] ReSemiClip is already in plugins.ini"
fi

#############################################
# 5. Cleanup
#############################################

rm -rf "$TMP_DIR"

echo "================================="
echo " ReSemiClip Installed Successfully!"
echo "================================="
