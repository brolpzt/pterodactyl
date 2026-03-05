#!/bin/bash
# shellcheck disable=SC2086
set -e

#########################
# CONFIGURAÇÕES
#########################

SERVER_DIR="/mnt/server"
# Se HLDS_GAME não estiver setado, usamos cstrike como padrão
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/install_metamod"

LIBLIST="$GAME_DIR/liblist.gam"
MM_DIR="$GAME_DIR/addons/metamod"
PLUGIN_FILE="$MM_DIR/plugins.ini"

# Permite especificar versão, ou latest
VERSION="${VERSION:-latest}"

echo "================================="
echo " Metamod-R Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl unzip ca-certificates >/dev/null 2>&1 || true

mkdir -p "$TMP_DIR" "$GAME_DIR"

########################################
# 1. Fetch release info (robusto)
########################################

GITHUB_API="https://api.github.com/repos/rehlds/Metamod-R/releases"

if [ "$VERSION" = "latest" ]; then
    RELEASE_URL="$GITHUB_API/latest"
else
    RELEASE_URL="$GITHUB_API/tags/$VERSION"
fi

echo "[INFO] Fetching release info from: $RELEASE_URL"
RELEASE_JSON=$(curl -sSL "$RELEASE_URL")

# Verifica erro da API do GitHub (ex: rate limit)
if echo "$RELEASE_JSON" | grep -q '"message"[[:space:]]*:'; then
    MSG=$(echo "$RELEASE_JSON" | grep -o '"message"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | cut -d'"' -f4)
    echo "[ERROR] GitHub API error: $MSG"
    exit 1
fi

RELEASE_TAG=$(echo "$RELEASE_JSON" | grep -o '"tag_name"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | cut -d'"' -f4)
echo "[INFO] Release tag: $RELEASE_TAG"

# Encontra a URL do zip (filtrando apenas pelo zip binário, ignorando source)
ASSET_URL=$(echo "$RELEASE_JSON" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep 'metamod-bin.*\.zip' | head -1 | cut -d'"' -f4)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find metamod-bin-*.zip asset for version: $VERSION"
    exit 1
fi

echo "[INFO] Downloading Metamod-R from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/metamod.zip" "$ASSET_URL"

########################################
# 2. Extract & Install
########################################

echo "[INFO] Extracting files..."
unzip -q -o "$TMP_DIR/metamod.zip" -d "$TMP_DIR/extracted"

echo "[INFO] Installing to $GAME_DIR..."
# O zip contém uma pasta "addons". Copiamos ela direto para a raiz do mod (ex: cstrike/)
cp -r "$TMP_DIR/extracted/addons" "$GAME_DIR/"

########################################
# 3. Cleanup Windows DLLs
########################################

echo "[INFO] Removing Windows DLLs..."
find "$MM_DIR" -type f -name "*.dll" -delete || true

########################################
# 4. Ensure plugins.ini
########################################

echo "[INFO] Creating plugins.ini..."
if [ ! -f "$PLUGIN_FILE" ]; then
    touch "$PLUGIN_FILE"
    echo "; Metamod plugins" >> "$PLUGIN_FILE"
    echo "criado: $PLUGIN_FILE"
fi

########################################
# 5. Configure liblist.gam
########################################

echo "[INFO] Configuring liblist.gam..."
if [ ! -f "$LIBLIST" ]; then
    echo "[WARNING] liblist.gam not found in $GAME_DIR!"
    echo "[WARNING] You will need to manually configure it to load Metamod."
else
    # Remove qualquer linha gamedll_linux existente
    sed -i '/gamedll_linux/d' "$LIBLIST"
    
    # Adiciona a linha do metamod (importante: não precisa de ponto e vírgula ou aspas duplas adicionais por fora)
    echo 'gamedll_linux "addons/metamod/metamod_i386.so"' >> "$LIBLIST"
    echo "[INFO] liblist.gam updated successfully."
fi

########################################
# 6. Cleanup temporários
########################################

rm -rf "$TMP_DIR"

echo "================================="
echo " Metamod-R Installed Successfully"
echo "================================="
