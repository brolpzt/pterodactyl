#!/bin/bash
# shellcheck disable=SC2086
set -e

#########################
# CONFIGURAÇÕES
#########################

SERVER_DIR="/mnt/server"
TMP_DIR="/tmp/rehlds_install"

# Credenciais Steam (anonymous por padrão)
STEAM_USER="${STEAM_USER:-anonymous}"
STEAM_PASS="${STEAM_PASS:-}"
STEAM_AUTH="${STEAM_AUTH:-}"

# Game
SRCDS_APPID="${SRCDS_APPID:-90}"
HLDS_GAME="${HLDS_GAME:-valve}"

# ReHLDS
VERSION="${VERSION:-latest}"

# Beta branch (opcional)
SRCDS_BETAID="${SRCDS_BETAID:-}"

# Validate com SteamCMD? (recomendado desligar para ReHLDS)
VALIDATE="${VALIDATE:-0}"

# Auto Update
AUTO_UPDATE="${AUTO_UPDATE:-0}"

# Limpa o diretório do servidor antes de iniciar
echo "-----------------------------------------"
echo "[INFO] Cleaning up $SERVER_DIR before installation..."
echo "-----------------------------------------"
rm -rf "${SERVER_DIR:?}"/*

mkdir -p "$TMP_DIR" "$SERVER_DIR"

#########################
# 1. STEAMCMD
#########################

echo "-----------------------------------------"
echo "[INFO] Installing SteamCMD..."
echo "-----------------------------------------"

mkdir -p "$SERVER_DIR/steamcmd"
curl -sSL -o /tmp/steamcmd.tar.gz https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz
tar -xzf /tmp/steamcmd.tar.gz -C "$SERVER_DIR/steamcmd"
rm -f /tmp/steamcmd.tar.gz

export HOME="$SERVER_DIR"

# Monta flags de validate
VALIDATE_FLAG=""
if [ "$VALIDATE" = "1" ]; then
    VALIDATE_FLAG="validate"
fi

# Monta flag de beta branch
BETA_FLAGS=""
if [ -n "$SRCDS_BETAID" ]; then
    BETA_FLAGS="-beta $SRCDS_BETAID"
fi

echo "-----------------------------------------"
echo "[INFO] Running SteamCMD to install HLDS..."
echo "[INFO]   AppID : $SRCDS_APPID"
echo "[INFO]   Game  : $HLDS_GAME"
echo "[INFO]   Beta  : ${SRCDS_BETAID:-public}"
echo "-----------------------------------------"

"$SERVER_DIR/steamcmd/steamcmd.sh" \
    +force_install_dir "$SERVER_DIR" \
    +login "$STEAM_USER" "$STEAM_PASS" "$STEAM_AUTH" \
    +app_set_config "$SRCDS_APPID" mod "$HLDS_GAME" \
    +app_update "$SRCDS_APPID" $BETA_FLAGS $VALIDATE_FLAG \
    +quit

# Copia bibliotecas Steam necessárias
mkdir -p "$SERVER_DIR/.steam/sdk32"
cp -f "$SERVER_DIR/steamcmd/linux32/steamclient.so" "$SERVER_DIR/.steam/sdk32/steamclient.so" 2>/dev/null || true

echo "[INFO] SteamCMD / HLDS install complete."

#########################
# 2. REHLDS
#########################

echo "-----------------------------------------"
echo "[INFO] Installing ReHLDS (version: $VERSION)..."
echo "-----------------------------------------"

GITHUB_API="https://api.github.com/repos/rehlds/ReHLDS/releases"

if [ "$VERSION" = "latest" ]; then
    RELEASE_URL="$GITHUB_API/latest"
else
    # Busca a release pela tag exata
    RELEASE_URL="$GITHUB_API/tags/$VERSION"
fi

echo "[INFO] Fetching release info from: $RELEASE_URL"
RELEASE_JSON=$(curl -sSL "$RELEASE_URL")

# Verifica se a requisição retornou corretamente (rate limit ou erro)
if echo "$RELEASE_JSON" | grep -q '"message"[[:space:]]*:'; then
    MSG=$(echo "$RELEASE_JSON" | grep -oP '"message"\s*:\s*"\K[^"]+' | head -1)
    echo "[ERROR] GitHub API error: $MSG"
    exit 1
fi

# Pega a tag/versão real da release
RELEASE_TAG=$(echo "$RELEASE_JSON" | grep -oP '"tag_name"\s*:\s*"\K[^"]+' | head -1)
echo "[INFO] Release tag: $RELEASE_TAG"

# Encontra o asset rehlds-bin-*.zip
ASSET_URL=$(echo "$RELEASE_JSON" | grep -oP '"browser_download_url"\s*:\s*"\K[^"]+' | grep 'rehlds-bin.*\.zip' | head -1)

if [ -z "$ASSET_URL" ]; then
    echo "[ERROR] Could not find rehlds-bin-*.zip asset for version: $VERSION"
    echo "[DEBUG] Available assets:"
    echo "$RELEASE_JSON" | grep -oP '"browser_download_url"\s*:\s*"\K[^"]+'
    exit 1
fi

echo "[INFO] Downloading ReHLDS from: $ASSET_URL"
curl -sSL -o "$TMP_DIR/rehlds-bin.zip" "$ASSET_URL"

echo "[INFO] Extracting ReHLDS..."
unzip -q -o "$TMP_DIR/rehlds-bin.zip" -d "$TMP_DIR/rehlds"

# Pasta linux32 dentro do zip: bin/linux32/
LINUX32_PATH=$(find "$TMP_DIR/rehlds" -type d -name "linux32" | grep "bin/linux32" | head -1)

if [ -z "$LINUX32_PATH" ]; then
    echo "[ERROR] linux32 folder not found inside the zip!"
    echo "[DEBUG] Zip contents:"
    unzip -l "$TMP_DIR/rehlds-bin.zip"
    exit 1
fi

echo "[INFO] ReHLDS linux32 path: $LINUX32_PATH"

# Copia arquivos raiz do linux32 para $SERVER_DIR
# (hlds_linux, engine_i486.so, core.so, demoplayer.so, filesystem_stdio.so, hltv, proxy.so)
echo "[INFO] Copying ReHLDS binaries to $SERVER_DIR..."
find "$LINUX32_PATH" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/" \;

# Copia subpastas (valve/dlls, etc.) mantendo estrutura
find "$LINUX32_PATH" -mindepth 1 -type d | while read -r subdir; do
    rel="${subdir#$LINUX32_PATH/}"
    mkdir -p "$SERVER_DIR/$rel"
    find "$subdir" -maxdepth 1 -type f -exec cp -f {} "$SERVER_DIR/$rel/" \;
done

# Garante permissão de execução no binário principal
chmod +x "$SERVER_DIR/hlds_linux" 2>/dev/null || true
chmod +x "$SERVER_DIR/hltv" 2>/dev/null || true

# Cria arquivos padrão de ips/bans para evitar erros do console
echo "[INFO] Creating listip.cfg and banned.cfg..."
mkdir -p "$SERVER_DIR/$HLDS_GAME"
touch "$SERVER_DIR/$HLDS_GAME/listip.cfg"
touch "$SERVER_DIR/$HLDS_GAME/banned.cfg"

echo "[INFO] Generating optimized server.cfg..."
cat << 'EOF' > "$SERVER_DIR/$HLDS_GAME/server.cfg"
// Servidor: Nome, Senha e Rcon
hostname ""
rcon_password ""
sv_password ""

// Configurações de Rede e Otimização ReHLDS/ReGameDLL
sv_maxrate "100000"
sv_minrate "25000"
sv_maxupdaterate "102"
sv_minupdaterate "30"
sv_maxunlag "1"

// Variáveis de Jogo (Gameplay)
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
sv_alltalk "1"     // Ajuda o plugin ReVoice a funcionar o mic geral
sv_gravity "800"
sv_maxspeed "320"

// Variáveis de Servidor
sv_allowdownload "1"
sv_allowupload "1"
sv_aim "0"
sv_cheats "0"
pausable "0"
sv_timeout "60"

// ReHLDS / Segurança Adicional (ReGameDLL_CS permite muitos controles granulares)
sv_rehlds_movecmdrate_max_avg "1500" // Limita flood de pacotes cmd
sv_rehlds_stringcmdrate_max_avg "250" // Limita flood de pacotes scripts

// Proteções contra erros do console
exec listip.cfg
exec banned.cfg
EOF

# Limpeza
rm -rf "$TMP_DIR"

echo "-----------------------------------------"
echo "[INFO] ReHLDS $RELEASE_TAG installed successfully!"
echo "[INFO] Game   : $HLDS_GAME"
echo "[INFO] Server : $SERVER_DIR"
echo "-----------------------------------------"
echo "Installation completed successfully!"
echo "-----------------------------------------"
