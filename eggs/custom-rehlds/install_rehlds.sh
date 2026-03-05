#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# ReHLDS (Engine) Installer / Updater
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# ReHLDS binários vão na raiz da engine
TMP_DIR="/tmp/rehlds_update"

# Permite especificar versão, ou latest
VERSION="${VERSION:-latest}"

echo "================================="
echo " ReHLDS Engine Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl unzip ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR" "$SERVER_DIR"

#############################################
# 1. Fetch release info (robusto)
#############################################

GITHUB_API="https://api.github.com/repos/rehlds/ReHLDS/releases"

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
ASSET_URL=$(echo "$RELEASE_JSON" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep 'rehlds-bin.*\.zip' | head -1 | cut -d'"' -f4)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find .zip asset for version: $VERSION"
    exit 1
fi

echo "[INFO] Downloading ReHLDS from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/rehlds-bin.zip" "$ASSET_URL"

########################################
# 2. Extract & Install
########################################

echo "[INFO] Extracting ReHLDS Engine..."
unzip -q -o "$TMP_DIR/rehlds-bin.zip" -d "$TMP_DIR/rehlds"

LINUX32_PATH=$(find "$TMP_DIR/rehlds" -type d -name "linux32" | head -1)
if [ -n "$LINUX32_PATH" ]; then
    # Copia os arquivos binários para a raiz do servidor (/mnt/server/)
    echo "[INFO] Installing to $SERVER_DIR..."
    find "$LINUX32_PATH" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/" \;

    # Copia subpastas mantendo estrutura (valve/dlls, etc.)
    find "$LINUX32_PATH" -mindepth 1 -type d | while read -r subdir; do
        rel="${subdir#$LINUX32_PATH/}"
        mkdir -p "$SERVER_DIR/$rel"
        find "$subdir" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/$rel/" \;
    done

    chmod +x "$SERVER_DIR/hlds_linux" 2>/dev/null || true
    chmod +x "$SERVER_DIR/hltv" 2>/dev/null || true
    
    echo "[INFO] ReHLDS binaries successfully injected!"
else
    echo "[ERROR] Failed to find linux32 path in ReHLDS zip"
    exit 1
fi

########################################
# 3. Cleanup
########################################

rm -rf "$TMP_DIR"

echo "================================="
echo " ReHLDS Installed Successfully!"
echo "================================="
