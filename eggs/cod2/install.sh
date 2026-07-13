#!/bin/bash
# shellcheck disable=SC2086
set -e

# ========================== PACKAGES ==========================

apt-get update -qq > /dev/null 2>&1
if ! apt-get install -y -qq curl unzip wget > /dev/null 2>&1; then
    echo "[ERROR] Failed to install packages" >> /tmp/install.log
    exit 1
fi

# ========================== CONFIGURATION ==========================

DEBUG=false
BUCKET_NAME="pterodactyl-repo"
S3_PATH="nests/cod2"
LOCAL_PATH="/mnt/server"

# Official rclone .deb from GitHub releases
RCLONE_VERSION="1.74.4"
RCLONE_DOWNLOAD_URL="https://github.com/rclone/rclone/releases/download/v${RCLONE_VERSION}/rclone-v${RCLONE_VERSION}-linux-amd64.deb"

R2_ACCOUNT_ID="708d02dab878ee77c3404055c66c4a5f"
R2_ACCESS_KEY_ID="27d036c2ec6828f81f5a613d78bac4b0"
R2_SECRET_ACCESS_KEY="0a3a79e5ea4733a1109091719105a0751df9b2d98026ee4ae649a5de48bc2aaf"
R2_ENDPOINT="https://${R2_ACCOUNT_ID}.r2.cloudflarestorage.com"

RCLONE_DEB="/tmp/rclone.deb"
RCLONE_BIN="rclone"
RCLONE_CONFIG="/tmp/rclone.conf"

# ========================== FUNCTIONS ==========================

log_debug() {
    [ "$DEBUG" = true ] && echo "[DEBUG] $1"
}

log_info() {
    echo "[INFO] $1"
}

die() {
    echo "[ERROR] $1"
    exit 1
}

install_rclone() {
    log_info "Downloading rclone..."
    curl -fsSL --connect-timeout 30 --max-time 600 \
        "$RCLONE_DOWNLOAD_URL" \
        -o "$RCLONE_DEB" \
        || die "Failed to download rclone."

    log_info "Installing rclone..."
    dpkg -i "$RCLONE_DEB" >/dev/null 2>&1 || apt-get install -f -y -qq >/dev/null 2>&1
    rm -f "$RCLONE_DEB"

    command -v rclone >/dev/null 2>&1 || die "rclone not found after installation."
    log_info "rclone installed successfully."
}

cleanup_rclone() {
    rm -f "$RCLONE_CONFIG"
}

# ========================== EXECUTION ==========================

log_info "Starting server installation..."
mkdir -p "$LOCAL_PATH"

install_rclone

log_info "Configuring rclone for R2..."
cat > "$RCLONE_CONFIG" <<EOF
[r2]
type = s3
provider = Cloudflare
access_key_id = $R2_ACCESS_KEY_ID
secret_access_key = $R2_SECRET_ACCESS_KEY
endpoint = $R2_ENDPOINT
acl = private
no_check_bucket = true
EOF

log_info "Syncing from R2 (r2:${BUCKET_NAME}/${S3_PATH})..."
"$RCLONE_BIN" sync \
    --config "$RCLONE_CONFIG" \
    "r2:${BUCKET_NAME}/${S3_PATH}" \
    "$LOCAL_PATH" \
    --stats 10s \
    --stats-one-line \
    || die "Failed to sync from R2."

cleanup_rclone
log_info "R2 sync completed."

# ========================== CoD2x ==========================

log_info "Downloading CoD2x..."
cd "$LOCAL_PATH"
curl -s https://api.github.com/repos/callofduty2x/CoD2x/releases/latest \
    | grep browser_download_url \
    | grep linux \
    | cut -d '"' -f 4 \
    | xargs wget -O CoD2x_latest_linux.zip

unzip -j CoD2x_latest_linux.zip 'libCoD2x.so'
rm -f CoD2x_latest_linux.zip

chmod -R 777 "$LOCAL_PATH"
echo "Server setup complete."
