#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# ReAPI Installer
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# ReAPI é um módulo do amxmodx, fica na pasta do jogo
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/reapi_install"

# Permite especificar versão, ou latest
VERSION="${VERSION:-latest}"

echo "================================="
echo " ReAPI Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl unzip ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR" "$GAME_DIR/addons"

#############################################
# 1. Fetch release info (robusto)
#############################################

GITHUB_API="https://api.github.com/repos/rehlds/ReAPI/releases"

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

# Encontra a URL do zip (filtrando apenas pelo zip do reapi-bin)
ASSET_URL=$(echo "$RELEASE_JSON" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep 'reapi-bin.*\.zip' | head -1 | cut -d'"' -f4)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find .zip asset for version: $VERSION"
    exit 1
fi

echo "[INFO] Downloading ReAPI from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/reapi.zip" "$ASSET_URL"

#############################################
# 2. Extract & Install
#############################################

echo "[INFO] Extracting files..."
unzip -q -o "$TMP_DIR/reapi.zip" -d "$TMP_DIR/extracted"

echo "[INFO] Installing to $GAME_DIR..."
# O zip do ReAPI contém a pasta "addons/amxmodx/..."
if [ -d "$TMP_DIR/extracted/addons" ]; then
    cp -r "$TMP_DIR/extracted/addons/"* "$GAME_DIR/addons/"
    echo "[INFO] Successfully copied ReAPI files to $GAME_DIR/addons."
else
    echo "[WARNING] 'addons/' not found at root inside the zip! Searching..."
    cp -r "$TMP_DIR/extracted/"* "$GAME_DIR/" || true
fi

#############################################
# 3. Cleanup Windows DLLs
#############################################

echo "[INFO] Removing Windows DLLs..."
find "$GAME_DIR/addons" -type f -name "*.dll" -delete || true

#############################################
# 4. Enable ReAPI module in AMX Mod X
#############################################

MODULES_INI="$GAME_DIR/addons/amxmodx/configs/modules.ini"

if [ -f "$MODULES_INI" ]; then
    echo "[INFO] Configuring ReAPI in modules.ini..."
    
    # Se existe ;reapi comentado, remove o ponto-e-vírgula
    if grep -q "^;[[:space:]]*reapi" "$MODULES_INI"; then
        sed -i 's/^;[[:space:]]*reapi/reapi/' "$MODULES_INI"
        echo "[INFO] Uncommoned reapi in modules.ini"
    # Se não existe reapi nem comentado nem descomentado, adiciona no final
    elif ! grep -q "^reapi" "$MODULES_INI"; then
        echo "reapi" >> "$MODULES_INI"
        echo "[INFO] Appended reapi to modules.ini"
    else
        echo "[INFO] ReAPI is already enabled in modules.ini"
    fi
else
    echo "[WARNING] modules.ini not found! Is AMX Mod X installed? Make sure to enable ReAPI manually later."
fi

#############################################
# 5. Cleanup
#############################################

rm -rf "$TMP_DIR"

echo "================================="
echo " ReAPI Installed Successfully!"
echo "================================="
