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
