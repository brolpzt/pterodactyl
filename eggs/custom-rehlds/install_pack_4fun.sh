#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# Pack 4fun Installer
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/pack_4fun_install"

# Variável de ambiente com a URL de download (pode ser enviada pelo Egg)
PACK_URL="${PACK_URL:-}"

echo "================================="
echo " Pack Maps 4Fun V1 Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl tar xz >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

if [ -n "$PACK_URL" ]; then
    echo "[INFO] Downloading Pack 4fun from $PACK_URL..."
    curl -sSL -o "$TMP_DIR/pack_4fun.tar.xz" "$PACK_URL"
    
    echo "[INFO] Extracting files into $GAME_DIR..."
    mkdir -p "$GAME_DIR"
    tar -xf "$TMP_DIR/pack_4fun.tar.xz" -C "$GAME_DIR"
elif [ -f "$SERVER_DIR/pack_4fun_v1.tar.xz" ]; then
    echo "[INFO] Found local file pack_4fun_v1.tar.xz in $SERVER_DIR. Extracting into $GAME_DIR..."
    mkdir -p "$GAME_DIR"
    tar -xf "$SERVER_DIR/pack_4fun_v1.tar.xz" -C "$GAME_DIR"
else
    echo "[ERROR] No PACK_URL provided or local pack_4fun_v1.tar.xz found!"
    echo "Please configure the URL in the egg variables or upload the file to your server root."
    exit 1
fi

#############################################
# Cleanup
#############################################
rm -rf "$TMP_DIR"

# Opcional: remover o arquivo local que o usuário possa ter upado caso queira poupar espaço
# rm -f "$SERVER_DIR/pack_4fun_v1.tar.xz"

echo "================================="
echo " Pack 4fun Installed Successfully!"
echo "================================="
