#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# AMX Mod X Installer
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/amxmodx_install"

AMXX_BASE_URL="https://www.amxmodx.org/amxxdrop/1.10/amxmodx-1.10.0-git5474-base-linux.tar.gz"
AMXX_ADDON_URL="https://www.amxmodx.org/amxxdrop/1.10/amxmodx-1.10.0-git5474-cstrike-linux.tar.gz"

echo "================================="
echo " AMX Mod X Installer"
echo "================================="

# Instala dependências silenciosamente
apk add --no-cache curl tar ca-certificates >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

#############################################
# 1. Download AMX Mod X (Base & Cstrike)
#############################################

echo "[INFO] Downloading AMX Mod X Base..."
curl -sSL -o "$TMP_DIR/amxmodx_base.tar.gz" "$AMXX_BASE_URL"

echo "[INFO] Downloading AMX Mod X Cstrike/CZero Addon..."
curl -sSL -o "$TMP_DIR/amxmodx_addon.tar.gz" "$AMXX_ADDON_URL"

#############################################
# 2. Extract & Install
#############################################

echo "[INFO] Extracting files..."

mkdir -p "$TMP_DIR/extracted"

# Extrai os pacotes (tar irá criar a pasta 'addons/amxmodx' etc.)
tar -xzf "$TMP_DIR/amxmodx_base.tar.gz" -C "$TMP_DIR/extracted"
tar -xzf "$TMP_DIR/amxmodx_addon.tar.gz" -C "$TMP_DIR/extracted"

echo "[INFO] Installing to $GAME_DIR..."
# Copia a estrutura extraída para a raiz do mod
cp -r "$TMP_DIR/extracted/addons" "$GAME_DIR/"

#############################################
# 3. Add to metamod plugins.ini
#############################################

PLUGIN_FILE="$GAME_DIR/addons/metamod/plugins.ini"

echo "[INFO] Configuring Metamod plugins.ini..."
mkdir -p "$(dirname "$PLUGIN_FILE")"
touch "$PLUGIN_FILE"

# Verifica se já está configurado
if ! grep -q "amxmodx_mm_i386.so" "$PLUGIN_FILE"; then
    echo "linux addons/amxmodx/dlls/amxmodx_mm_i386.so" >> "$PLUGIN_FILE"
    echo "[INFO] Added AMX Mod X to plugins.ini"
else
    echo "[INFO] AMX Mod X is already in plugins.ini"
fi

#############################################
# 4. Cleanup
#############################################

rm -rf "$TMP_DIR"

echo "================================="
echo " AMX Mod X Installed Successfully!"
echo "================================="
