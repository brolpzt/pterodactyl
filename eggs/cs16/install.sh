#!/bin/bash
# shellcheck disable=SC2086
set -e

#########################
# CONFIGURATION
#########################

SERVER_DIR="/mnt/server"
TMP_DIR="/tmp/cs16_install"

STEAM_USER="${STEAM_USER:-anonymous}"
STEAM_PASS="${STEAM_PASS:-}"
STEAM_AUTH="${STEAM_AUTH:-}"

SRCDS_APPID="${SRCDS_APPID:-90}"
HLDS_GAME="${HLDS_GAME:-cstrike}"

VERSION="${VERSION:-latest}"
REGAMEDLL_VERSION="${REGAMEDLL_VERSION:-latest}"
SRCDS_BETAID="${SRCDS_BETAID:-}"
VALIDATE="${VALIDATE:-0}"

# AMXX admin (egg variable: STEAM_ID)
STEAM_ID="${STEAM_ID:-}"
AMXX_ADMIN_FLAGS="${AMXX_ADMIN_FLAGS:-abcdefghijklmnopqrstu}"
AMXX_ADMIN_NAME="${AMXX_ADMIN_NAME:-Server Owner}"

AMXX_BASE_URL="https://www.amxmodx.org/amxxdrop/1.10/amxmodx-1.10.0-git5474-base-linux.tar.gz"
AMXX_ADDON_URL="https://www.amxmodx.org/amxxdrop/1.10/amxmodx-1.10.0-git5474-cstrike-linux.tar.gz"

GAME_DIR="$SERVER_DIR/$HLDS_GAME"

#########################
# HELPERS
#########################

log_info() {
    echo "[INFO] $1"
}

die() {
    echo "[ERROR] $1"
    exit 1
}

github_release_json() {
    local api_base="$1"
    local version="$2"

    if [ "$version" = "latest" ]; then
        curl -sSL "$api_base/latest"
    else
        curl -sSL "$api_base/tags/$version"
    fi
}

github_asset_url() {
    local json="$1"
    local pattern="$2"

    echo "$json" | grep -oP '"browser_download_url"\s*:\s*"\K[^"]+' | grep -E "$pattern" | head -1
}

add_metamod_plugin() {
    local entry="$1"
    local plugin_file="$GAME_DIR/addons/metamod/plugins.ini"

    mkdir -p "$(dirname "$plugin_file")"
    touch "$plugin_file"

    if ! grep -qF "$entry" "$plugin_file"; then
        echo "$entry" >> "$plugin_file"
        log_info "Added to plugins.ini: $entry"
    fi
}

configure_users_ini() {
    local users_ini="$GAME_DIR/addons/amxmodx/configs/users.ini"

    if [ ! -f "$users_ini" ]; then
        log_info "users.ini not found; skipping admin setup."
        return 0
    fi

    if [ -z "$STEAM_ID" ]; then
        log_info "STEAM_ID not set; keeping default users.ini."
        return 0
    fi

    if ! echo "$STEAM_ID" | grep -qiE '^STEAM_[0-5]:[01]:[0-9]+$'; then
        die "Invalid STEAM_ID format. Use STEAM_0:X:XXXXX."
    fi

    local admin_line
    admin_line="\"${STEAM_ID}\" \"\" \"${AMXX_ADMIN_FLAGS}\" \"ce\" ; ${AMXX_ADMIN_NAME}"

    if grep -qF "\"${STEAM_ID}\"" "$users_ini"; then
        log_info "STEAM_ID already present in users.ini."
        return 0
    fi

    {
        echo ""
        echo "; Admin configured by Pterodactyl install (STEAM_ID)"
        echo "$admin_line"
    } >> "$users_ini"

    log_info "Admin added to users.ini for $STEAM_ID"
}

#########################
# PACKAGES
#########################

apt-get update -qq > /dev/null 2>&1
apt-get install -y -qq curl unzip tar ca-certificates > /dev/null 2>&1 \
    || die "Failed to install packages."

#########################
# PREP
#########################

log_info "Cleaning $SERVER_DIR..."
rm -rf "${SERVER_DIR:?}"/*
mkdir -p "$TMP_DIR" "$SERVER_DIR"

#########################
# 1. STEAMCMD / HLDS
#########################

log_info "Installing SteamCMD..."
mkdir -p "$SERVER_DIR/steamcmd"
curl -sSL -o /tmp/steamcmd.tar.gz https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz
tar -xzf /tmp/steamcmd.tar.gz -C "$SERVER_DIR/steamcmd"
rm -f /tmp/steamcmd.tar.gz

export HOME="$SERVER_DIR"

VALIDATE_FLAG=""
[ "$VALIDATE" = "1" ] && VALIDATE_FLAG="validate"

BETA_FLAGS=""
[ -n "$SRCDS_BETAID" ] && BETA_FLAGS="-beta $SRCDS_BETAID"

log_info "Running SteamCMD (AppID: $SRCDS_APPID, game: $HLDS_GAME)..."
"$SERVER_DIR/steamcmd/steamcmd.sh" \
    +force_install_dir "$SERVER_DIR" \
    +login "$STEAM_USER" "$STEAM_PASS" "$STEAM_AUTH" \
    +app_set_config "$SRCDS_APPID" mod "$HLDS_GAME" \
    +app_update "$SRCDS_APPID" $BETA_FLAGS $VALIDATE_FLAG \
    +quit

mkdir -p "$SERVER_DIR/.steam/sdk32"
cp -f "$SERVER_DIR/steamcmd/linux32/steamclient.so" "$SERVER_DIR/.steam/sdk32/steamclient.so" 2>/dev/null || true

log_info "SteamCMD / HLDS install complete."

#########################
# 2. REHLDS
#########################

log_info "Installing ReHLDS (version: $VERSION)..."
RELEASE_JSON=$(github_release_json "https://api.github.com/repos/rehlds/ReHLDS/releases" "$VERSION")

if echo "$RELEASE_JSON" | grep -q '"message"[[:space:]]*:'; then
    MSG=$(echo "$RELEASE_JSON" | grep -oP '"message"\s*:\s*"\K[^"]+' | head -1)
    die "GitHub API error: $MSG"
fi

RELEASE_TAG=$(echo "$RELEASE_JSON" | grep -oP '"tag_name"\s*:\s*"\K[^"]+' | head -1)
ASSET_URL=$(github_asset_url "$RELEASE_JSON" 'rehlds-bin.*\.zip')
[ -n "$ASSET_URL" ] || die "Could not find rehlds-bin asset for version: $VERSION"

log_info "ReHLDS release: $RELEASE_TAG"
curl -sSL -o "$TMP_DIR/rehlds-bin.zip" "$ASSET_URL"
unzip -q -o "$TMP_DIR/rehlds-bin.zip" -d "$TMP_DIR/rehlds"

LINUX32_PATH=$(find "$TMP_DIR/rehlds" -type d -name "linux32" | grep "bin/linux32" | head -1)
[ -n "$LINUX32_PATH" ] || die "linux32 folder not found inside ReHLDS zip."

find "$LINUX32_PATH" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/" \;
find "$LINUX32_PATH" -mindepth 1 -type d | while read -r subdir; do
    rel="${subdir#$LINUX32_PATH/}"
    mkdir -p "$SERVER_DIR/$rel"
    find "$subdir" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/$rel/" \;
done

chmod +x "$SERVER_DIR/hlds_linux" 2>/dev/null || true
chmod +x "$SERVER_DIR/hltv" 2>/dev/null || true

log_info "ReHLDS installed."

#########################
# 3. REGAMEDLL_CS
#########################

log_info "Installing ReGameDLL_CS (version: $REGAMEDLL_VERSION)..."
RG_JSON=$(github_release_json "https://api.github.com/repos/rehlds/ReGameDLL_CS/releases" "$REGAMEDLL_VERSION")

if echo "$RG_JSON" | grep -q '"message"[[:space:]]*:'; then
    MSG=$(echo "$RG_JSON" | grep -oP '"message"\s*:\s*"\K[^"]+' | head -1)
    die "GitHub API error: $MSG"
fi

RG_TAG=$(echo "$RG_JSON" | grep -oP '"tag_name"\s*:\s*"\K[^"]+' | head -1)
RG_URL=$(github_asset_url "$RG_JSON" 'regamedll-bin.*\.zip')
[ -n "$RG_URL" ] || die "Could not find regamedll-bin asset for version: $REGAMEDLL_VERSION"

log_info "ReGameDLL_CS release: $RG_TAG"
curl -sSL -o "$TMP_DIR/regamedll.zip" "$RG_URL"
unzip -q -o "$TMP_DIR/regamedll.zip" -d "$TMP_DIR/regamedll"

ZIP_LINUX_DIR="$TMP_DIR/regamedll/bin/linux32/cstrike"
[ -d "$ZIP_LINUX_DIR" ] || die "bin/linux32/cstrike not found inside ReGameDLL zip."

mkdir -p "$GAME_DIR/dlls"
if [ -f "$GAME_DIR/dlls/cs.so" ] && [ ! -f "$GAME_DIR/dlls/cs.so.bak" ]; then
    mv "$GAME_DIR/dlls/cs.so" "$GAME_DIR/dlls/cs.so.bak"
    log_info "Backed up original cs.so to cs.so.bak"
fi

cp -r "$ZIP_LINUX_DIR/"* "$GAME_DIR/"
log_info "ReGameDLL_CS installed."

#########################
# 4. METAMOD-R
#########################

log_info "Installing Metamod-R..."
MM_JSON=$(github_release_json "https://api.github.com/repos/rehlds/Metamod-R/releases" "latest")
MM_URL=$(github_asset_url "$MM_JSON" 'metamod-bin.*\.zip')
[ -n "$MM_URL" ] || die "Could not find Metamod-R asset."

curl -sSL -o "$TMP_DIR/metamod.zip" "$MM_URL"
unzip -q -o "$TMP_DIR/metamod.zip" -d "$TMP_DIR/metamod"
cp -r "$TMP_DIR/metamod/addons" "$GAME_DIR/"
find "$GAME_DIR/addons/metamod" -type f -name "*.dll" -delete || true

LIBLIST="$GAME_DIR/liblist.gam"
if [ -f "$LIBLIST" ]; then
    sed -i '/gamedll_linux/d' "$LIBLIST"
    echo 'gamedll_linux "addons/metamod/metamod_i386.so"' >> "$LIBLIST"
    log_info "liblist.gam updated for Metamod."
else
    die "liblist.gam not found in $GAME_DIR"
fi

log_info "Metamod-R installed."

#########################
# 5. AMX MOD X
#########################

log_info "Installing AMX Mod X..."
curl -sSL -o "$TMP_DIR/amxmodx_base.tar.gz" "$AMXX_BASE_URL"
curl -sSL -o "$TMP_DIR/amxmodx_addon.tar.gz" "$AMXX_ADDON_URL"

mkdir -p "$TMP_DIR/amxmodx"
tar -xzf "$TMP_DIR/amxmodx_base.tar.gz" -C "$TMP_DIR/amxmodx"
tar -xzf "$TMP_DIR/amxmodx_addon.tar.gz" -C "$TMP_DIR/amxmodx"
cp -r "$TMP_DIR/amxmodx/addons" "$GAME_DIR/"

add_metamod_plugin "linux addons/amxmodx/dlls/amxmodx_mm_i386.so"

log_info "AMX Mod X installed."

#########################
# 6. AMXX ADMIN (users.ini)
#########################

configure_users_ini

#########################
# 7. SERVER CONFIG
#########################

mkdir -p "$GAME_DIR"
touch "$GAME_DIR/listip.cfg"
touch "$GAME_DIR/banned.cfg"

log_info "Generating server.cfg..."
cat << 'EOF' > "$GAME_DIR/server.cfg"
// Server: name, password and rcon
hostname ""
rcon_password ""
sv_password ""

// Network / ReHLDS
sv_maxrate "100000"
sv_minrate "25000"
sv_maxupdaterate "102"
sv_minupdaterate "30"
sv_maxunlag "1"

// Gameplay
mp_autoteambalance "1"
mp_buytime "0.25"
mp_c4timer "35"
mp_flashlight "1"
mp_footsteps "1"
mp_forcechasecam "0"
mp_forcecamera "0"
mp_freezetime "3"
mp_friendlyfire "0"
mp_hostagepenalty "0"
mp_limitteams "1"
mp_roundtime "2"
mp_timelimit "20"
mp_tkpunish "0"
sv_alltalk "1"
sv_gravity "800"
sv_maxspeed "320"

// Server
sv_allowdownload "1"
sv_allowupload "1"
sv_aim "0"
sv_cheats "0"
pausable "0"
sv_timeout "60"

// ReHLDS security
sv_rehlds_movecmdrate_max_avg "1500"
sv_rehlds_stringcmdrate_max_avg "250"

exec listip.cfg
exec banned.cfg
EOF

#########################
# CLEANUP
#########################

rm -rf "$TMP_DIR"
chmod -R 777 "$SERVER_DIR"

echo "-----------------------------------------"
echo "[INFO] CS 1.6 (ReHLDS + ReGameDLL + AMXX) installed."
echo "[INFO] Game   : $HLDS_GAME"
echo "[INFO] Server : $SERVER_DIR"
if [ -n "$STEAM_ID" ]; then
    echo "[INFO] AMXX admin: $STEAM_ID"
fi
echo "-----------------------------------------"
echo "Installation completed successfully!"
echo "-----------------------------------------"
