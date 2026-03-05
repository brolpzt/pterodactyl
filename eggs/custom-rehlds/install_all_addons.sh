#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# All-In-One Addons Installer for ReHLDS
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
# Se HLDS_GAME não estiver setado, usamos cstrike como padrão
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/addons_install"

echo "============================================="
echo " Installing All ReHLDS Addons..."
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
# 0. ReHLDS (Engine Update)
########################################
echo "--- [0/9] Installing/Updating ReHLDS Engine ---"
REHLDS_URL=$(get_github_release_url "rehlds/ReHLDS" "rehlds-bin.*\.zip")
curl -sSL -o "$TMP_DIR/rehlds-bin.zip" "$REHLDS_URL"
unzip -q -o "$TMP_DIR/rehlds-bin.zip" -d "$TMP_DIR/rehlds"

LINUX32_PATH=$(find "$TMP_DIR/rehlds" -type d -name "linux32" | head -1)
if [ -n "$LINUX32_PATH" ]; then
    # Copia os arquivos binários para a raiz do servidor (/mnt/server/)
    find "$LINUX32_PATH" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/" \;

    # Copia subpastas mantendo estrutura (valve/dlls, etc.)
    find "$LINUX32_PATH" -mindepth 1 -type d | while read -r subdir; do
        rel="${subdir#$LINUX32_PATH/}"
        mkdir -p "$SERVER_DIR/$rel"
        find "$subdir" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/$rel/" \;
    done

    chmod +x "$SERVER_DIR/hlds_linux" 2>/dev/null || true
    chmod +x "$SERVER_DIR/hltv" 2>/dev/null || true
    
    echo "[INFO] ReHLDS binaries injected into $SERVER_DIR"
else
    echo "[ERROR] failed to find linux32 path in ReHLDS zip"
fi

########################################
# 1. Metamod-R
########################################
echo "--- [1/9] Installing Metamod-R ---"
MM_URL=$(get_github_release_url "rehlds/Metamod-R" "metamod-bin.*\.zip")
curl -sSL -o "$TMP_DIR/metamod.zip" "$MM_URL"
unzip -q -o "$TMP_DIR/metamod.zip" -d "$TMP_DIR/metamod"

cp -r "$TMP_DIR/metamod/addons" "$GAME_DIR/"
find "$GAME_DIR/addons/metamod" -type f -name "*.dll" -delete || true

# Configura o liblist.gam
LIBLIST="$GAME_DIR/liblist.gam"
if [ -f "$LIBLIST" ]; then
    sed -i '/gamedll_linux/d' "$LIBLIST"
    echo 'gamedll_linux "addons/metamod/metamod_i386.so"' >> "$LIBLIST"
fi

########################################
# 2. ReGameDLL_CS
########################################
echo "--- [2/9] Installing ReGameDLL_CS ---"
RG_URL=$(get_github_release_url "rehlds/ReGameDLL_CS" "regamedll-bin.*\.zip")
curl -sSL -o "$TMP_DIR/regamedll.zip" "$RG_URL"
unzip -q -o "$TMP_DIR/regamedll.zip" -d "$TMP_DIR/regamedll"

ZIP_LINUX_DIR="$TMP_DIR/regamedll/bin/linux32/cstrike"
if [ -d "$ZIP_LINUX_DIR" ]; then
    # Faz backup do cs.so original se existir e não tiver sido backupeado
    if [ -f "$GAME_DIR/dlls/cs.so" ] && [ ! -f "$GAME_DIR/dlls/cs.so.bak" ]; then
        mv "$GAME_DIR/dlls/cs.so" "$GAME_DIR/dlls/cs.so.bak"
    fi
    mkdir -p "$GAME_DIR/dlls"
    # Copia config, cs.so e afins para a raiz de cstrike/
    cp -r "$ZIP_LINUX_DIR/"* "$GAME_DIR/"
fi

########################################
# 3. Reunion
########################################
echo "--- [3/9] Installing Reunion ---"
RU_URL=$(get_github_release_url "rehlds/reunion" "\.zip")
curl -sSL -o "$TMP_DIR/reunion.zip" "$RU_URL"
unzip -q -o "$TMP_DIR/reunion.zip" -d "$TMP_DIR/reunion"

mkdir -p "$GAME_DIR/addons/reunion"
cp "$TMP_DIR/reunion/bin/Linux/reunion_mm_i386.so" "$GAME_DIR/addons/reunion/"
cp "$TMP_DIR/reunion/reunion.cfg" "$GAME_DIR/addons/reunion/"

# Injeta Salt seguro obrigatorio do Reunion
SALT=$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32 || true)
if [ -n "$SALT" ]; then
    sed -i "s/^[[:space:]]*SteamIdHashSalt.*/SteamIdHashSalt = $SALT/" "$GAME_DIR/addons/reunion/reunion.cfg"
fi

add_to_plugins_ini "linux addons/reunion/reunion_mm_i386.so"

########################################
# 4. ReVoice
########################################
echo "--- [4/9] Installing ReVoice ---"
RV_URL=$(get_github_release_url "rehlds/ReVoice" "revoice.*\.zip")
curl -sSL -o "$TMP_DIR/revoice.zip" "$RV_URL"
unzip -q -o "$TMP_DIR/revoice.zip" -d "$TMP_DIR/revoice"

mkdir -p "$GAME_DIR/addons/revoice"
cp "$TMP_DIR/revoice/bin/linux32/revoice_mm_i386.so" "$GAME_DIR/addons/revoice/"
if [ -f "$TMP_DIR/revoice/revoice.cfg" ]; then
    cp "$TMP_DIR/revoice/revoice.cfg" "$GAME_DIR/addons/revoice/"
fi

add_to_plugins_ini "linux addons/revoice/revoice_mm_i386.so"

########################################
# 5. ReChecker
########################################
echo "--- [5/9] Installing ReChecker ---"
RC_URL=$(get_github_release_url "rehlds/rechecker" "rechecker.*\.zip")
curl -sSL -o "$TMP_DIR/rechecker.zip" "$RC_URL"
unzip -q -o "$TMP_DIR/rechecker.zip" -d "$TMP_DIR/rechecker"

if [ -d "$TMP_DIR/rechecker/bin/addons" ]; then
    cp -r "$TMP_DIR/rechecker/bin/addons/"* "$GAME_DIR/addons/"
fi
find "$GAME_DIR/addons/rechecker" -type f -name "*.dll" -delete || true

add_to_plugins_ini "linux addons/rechecker/rechecker_mm_i386.so"

########################################
# 6. ReSemiClip
########################################
echo "--- [6/9] Installing ReSemiClip ---"
RS_URL=$(get_github_release_url "rehlds/resemiclip" "\.zip")
curl -sSL -o "$TMP_DIR/resemiclip.zip" "$RS_URL"
unzip -q -o "$TMP_DIR/resemiclip.zip" -d "$TMP_DIR/resemiclip"

cp -r "$TMP_DIR/resemiclip/addons" "$GAME_DIR/"
find "$GAME_DIR/addons/resemiclip" -type f -name "*.dll" -delete || true

add_to_plugins_ini "linux addons/resemiclip/resemiclip_mm_i386.so"

########################################
# 7. AMX Mod X
########################################
echo "--- [7/9] Installing AMX Mod X ---"
AMX_BASE_URL="https://www.amxmodx.org/amxxdrop/1.10/amxmodx-1.10.0-git5474-base-linux.tar.gz"
AMX_ADDON_URL="https://www.amxmodx.org/amxxdrop/1.10/amxmodx-1.10.0-git5474-cstrike-linux.tar.gz"

curl -sSL -o "$TMP_DIR/amx_base.tar.gz" "$AMX_BASE_URL"
curl -sSL -o "$TMP_DIR/amx_addon.tar.gz" "$AMX_ADDON_URL"

mkdir -p "$TMP_DIR/amx"
tar -xzf "$TMP_DIR/amx_base.tar.gz" -C "$TMP_DIR/amx"
tar -xzf "$TMP_DIR/amx_addon.tar.gz" -C "$TMP_DIR/amx"
cp -r "$TMP_DIR/amx/addons" "$GAME_DIR/"

add_to_plugins_ini "linux addons/amxmodx/dlls/amxmodx_mm_i386.so"

########################################
# 8. ReAPI
########################################
echo "--- [8/9] Installing ReAPI ---"

# Fetch latest ReAPI using our github helper
RA_URL=$(get_github_release_url "rehlds/ReAPI" "reapi-bin.*\.zip")
curl -sSL -o "$TMP_DIR/reapi.zip" "$RA_URL"
unzip -q -o "$TMP_DIR/reapi.zip" -d "$TMP_DIR/reapi"

# O zip do ReAPI contém a pasta "addons/amxmodx/..."
if [ -d "$TMP_DIR/reapi/addons" ]; then
    cp -r "$TMP_DIR/reapi/addons/"* "$GAME_DIR/addons/"
else
    # Fallback search
    cp -r "$TMP_DIR/reapi/"* "$GAME_DIR/" || true
fi

# Cleanup Windows DLLs
find "$GAME_DIR/addons" -type f -name "*.dll" -delete || true

# Configura o reapi no modules.ini se o amxmodx estiver instalado
MODULES_INI="$GAME_DIR/addons/amxmodx/configs/modules.ini"

if [ -f "$MODULES_INI" ]; then
    echo "[INFO] Configuring ReAPI in modules.ini..."
    if grep -q "^;[[:space:]]*reapi" "$MODULES_INI"; then
        sed -i 's/^;[[:space:]]*reapi/reapi/' "$MODULES_INI"
    elif ! grep -q "^reapi" "$MODULES_INI"; then
        echo "reapi" >> "$MODULES_INI"
    fi
else
    echo "[WARNING] modules.ini not found! Module ReAPI not activated automatically."
fi

########################################
# 9. Hitbox Fixer
########################################
echo "--- [9/10] Installing Hitbox Fixer ---"

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
echo " All Addons Installed Successfully!"
echo "============================================="
