#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# Reunion Installer
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# Se HLDS_GAME não estiver setado, usamos cstrike como padrão
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/reunion_install"

# Permite especificar versão, ou latest
VERSION="${VERSION:-latest}"

echo "================================="
echo " Reunion Installer (Metamod Plugin)"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl unzip ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR" "$GAME_DIR/addons/reunion"

#############################################
# 1. Fetch release info (robusto)
#############################################

GITHUB_API="https://api.github.com/repos/rehlds/reunion/releases"

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

# Encontra a URL do zip (Reunion só tem um asset zip principal)
ASSET_URL=$(echo "$RELEASE_JSON" | grep -o '"browser_download_url"[[:space:]]*:[[:space:]]*"[^"]*"' | grep '\.zip' | head -1 | cut -d'"' -f4)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find .zip asset for version: $VERSION"
    exit 1
fi

echo "[INFO] Downloading Reunion from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/reunion.zip" "$ASSET_URL"

#############################################
# 2. Extract & Install
#############################################

echo "[INFO] Extracting files..."
unzip -q -o "$TMP_DIR/reunion.zip" -d "$TMP_DIR/extracted"

echo "[INFO] Installing to $GAME_DIR/addons/reunion..."
# O executável linux fica em `bin/Linux/reunion_mm_i386.so`
cp "$TMP_DIR/extracted/bin/Linux/reunion_mm_i386.so" "$GAME_DIR/addons/reunion/"

# O arquivo de configuração base fica na raiz do zip `reunion.cfg`
cp "$TMP_DIR/extracted/reunion.cfg" "$GAME_DIR/addons/reunion/"

# O Reunion EXIGE que o SteamIdHashSalt não seja muito curto ou vazio.
# Gerando um salt aleatório de 32 chars e consertando o arquivo .cfg.
echo "[INFO] Generating and injecting SteamIdHashSalt into reunion.cfg..."
SALT=$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32 || true)
if [ -n "$SALT" ]; then
    sed -i "s/^[[:space:]]*SteamIdHashSalt.*/SteamIdHashSalt = $SALT/" "$GAME_DIR/addons/reunion/reunion.cfg"
    echo "[INFO] Salt injected successfully ($SALT)."
else
    echo "[WARNING] Could not generate SteamIdHashSalt! You must edit reunion.cfg manually."
fi

#############################################
# 3. Add to metamod plugins.ini
#############################################

PLUGIN_FILE="$GAME_DIR/addons/metamod/plugins.ini"

echo "[INFO] Configuring Metamod plugins.ini..."
mkdir -p "$(dirname "$PLUGIN_FILE")"
touch "$PLUGIN_FILE"

# Verifica se já está configurado
if ! grep -q "reunion_mm_i386.so" "$PLUGIN_FILE"; then
    echo "linux addons/reunion/reunion_mm_i386.so" >> "$PLUGIN_FILE"
    echo "[INFO] Added Reunion to plugins.ini"
else
    echo "[INFO] Reunion is already in plugins.ini"
fi

#############################################
# 4. Cleanup
#############################################

rm -rf "$TMP_DIR"

echo "================================="
echo " Reunion Installed Successfully!"
echo "================================="
