#!/bin/bash
# shellcheck disable=SC2086
set -e

#############################################
# Pack 4fun Installer - B2 Private
# Alpine compatible
#############################################

SERVER_DIR="/mnt/server"
HLDS_GAME="${HLDS_GAME:-cstrike}"
GAME_DIR="$SERVER_DIR/$HLDS_GAME"
TMP_DIR="/tmp/pack_4fun_install"

# Credenciais Backblaze B2 (Bucket Privado)
B2_KEY_ID="001d9fbf9d37cc10000000019"
B2_APPLICATION_KEY="K001n76gsMdkSBnyh3qiB6O3Gqj6ryo"
B2_BUCKET_NAME="hostgamer"
B2_FILE_PATH="addons/cstrike/pack_4fun_v1.tar.xz"

echo "================================="
echo " Pack Maps 4Fun V1 Installer (B2)"
echo "================================="

# Instala dependências silenciosamente (jq é necessário para processar o JSON da API do B2)
apk add --no-cache curl tar xz jq >/dev/null 2>&1 || true

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

echo "[INFO] Authorizing with Backblaze B2..."
# Autentica na API do B2 para obter o Token e a URL de Download
AUTH_RESPONSE=$(curl -s https://api.backblazeb2.com/b2api/v2/b2_authorize_account -u "${B2_KEY_ID}:${B2_APPLICATION_KEY}")
AUTH_TOKEN=$(echo "$AUTH_RESPONSE" | jq -r '.authorizationToken')
DOWNLOAD_URL=$(echo "$AUTH_RESPONSE" | jq -r '.downloadUrl')

if [ "$AUTH_TOKEN" == "null" ] || [ -z "$AUTH_TOKEN" ]; then
    echo "[ERROR] Failed to authorize with Backblaze B2. Please check your credentials."
    exit 1
fi

echo "[INFO] Downloading Pack 4fun from private B2 bucket..."
# Baixa o arquivo usando o Token de autorização no cabeçalho
curl -sSL -H "Authorization: $AUTH_TOKEN" \
    -o "$TMP_DIR/pack_4fun.tar.xz" \
    "${DOWNLOAD_URL}/file/${B2_BUCKET_NAME}/${B2_FILE_PATH}"

echo "[INFO] Extracting files into $GAME_DIR..."
mkdir -p "$GAME_DIR"
# Usamos --strip-components=1 para ignorar a pasta raiz do arquivo e extrair o conteúdo direto
tar -xf "$TMP_DIR/pack_4fun.tar.xz" -C "$GAME_DIR" --strip-components=1

#############################################
# Cleanup
#############################################
rm -rf "$TMP_DIR"

echo "================================="
echo " Pack 4fun Installed Successfully!"
echo "================================="
