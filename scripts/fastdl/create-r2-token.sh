#!/usr/bin/env bash
#
# Cria um Account API Token do Cloudflare com permissão R2 (Object Read & Write)
# em um bucket específico e imprime as credenciais S3 para o nó FastDL do Pterodactyl.
#
# Autenticação (escolha UMA opção):
#   A) API Token:  CLOUDFLARE_API_TOKEN
#   B) Global Key: CLOUDFLARE_EMAIL + CLOUDFLARE_GLOBAL_API_KEY
#
# A Global API Key tem acesso total à conta — use só em ambiente confiável.
# O token criado pelo script é o do FastDL (escopo só no bucket).
#
# Uso:
#   cp scripts/fastdl/env.example scripts/fastdl/.env
#   ./scripts/fastdl/create-r2-token.sh
#
# Variáveis opcionais:
#   R2_JURISDICTION   default|eu|fedramp (padrão: default)
#   TOKEN_NAME        nome do token (padrão: pterodactyl-fastdl)
#   R2_PUBLIC_HOST    domínio público (só informativo na saída)
#   R2_PUBLIC_URL     URL pública completa, ex. https://fastdl.example.com

set -euo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly API_BASE="https://api.cloudflare.com/client/v4"

TOKEN_NAME="${TOKEN_NAME:-pterodactyl-fastdl}"
R2_JURISDICTION="${R2_JURISDICTION:-default}"
PERMISSION_GROUP_NAME="${PERMISSION_GROUP_NAME:-Workers R2 Storage Bucket Item Write}"
CF_AUTH_MODE=""

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { printf '%b\n' "$*"; }
die() { log "${RED}Erro:${NC} $*"; exit 1; }

require_cmd() {
    local cmd
    for cmd in "$@"; do
        command -v "$cmd" >/dev/null 2>&1 || die "Comando obrigatório não encontrado: $cmd"
    done
}

load_env_file() {
    local env_file="${SCRIPT_DIR}/.env"
    if [[ -f "$env_file" ]]; then
        # shellcheck disable=SC1090
        set -a && source "$env_file" && set +a
        log "${YELLOW}Carregado:${NC} ${env_file}"
    fi
}

resolve_auth_mode() {
    if [[ -n "${CLOUDFLARE_API_TOKEN:-}" ]]; then
        CF_AUTH_MODE="bearer"
        return 0
    fi

    if [[ -n "${CLOUDFLARE_GLOBAL_API_KEY:-}" && -n "${CLOUDFLARE_EMAIL:-}" ]]; then
        CF_AUTH_MODE="global_key"
        return 0
    fi

    # Alias comum
    if [[ -n "${CLOUDFLARE_API_KEY:-}" && -n "${CLOUDFLARE_EMAIL:-}" ]]; then
        CLOUDFLARE_GLOBAL_API_KEY="${CLOUDFLARE_API_KEY}"
        CF_AUTH_MODE="global_key"
        return 0
    fi

    return 1
}

cf_request() {
    local method="$1"
    local path="$2"
    local data="${3:-}"

    local args=(
        -sS
        -X "$method"
        -H "Content-Type: application/json"
    )

    if [[ "$CF_AUTH_MODE" == "bearer" ]]; then
        args+=(-H "Authorization: Bearer ${CLOUDFLARE_API_TOKEN}")
    else
        args+=(
            -H "X-Auth-Email: ${CLOUDFLARE_EMAIL}"
            -H "X-Auth-Key: ${CLOUDFLARE_GLOBAL_API_KEY}"
        )
    fi

    if [[ -n "$data" ]]; then
        args+=(--data "$data")
    fi

    curl "${args[@]}" "${API_BASE}${path}"
}

json_get() {
    local expr="$1"
    jq -r "$expr"
}

check_api_success() {
    local response="$1"
    local success
    success="$(printf '%s' "$response" | json_get '.success')"
    if [[ "$success" != "true" ]]; then
        log "${RED}Resposta da API Cloudflare:${NC}"
        printf '%s\n' "$response" | jq '.' >&2 || printf '%s\n' "$response" >&2
        die "A API retornou success=false."
    fi
}

find_permission_group_id() {
    local response
    response="$(cf_request GET "/accounts/${CLOUDFLARE_ACCOUNT_ID}/tokens/permission_groups")"
    check_api_success "$response"

    local group_id
    group_id="$(printf '%s' "$response" | jq -r --arg name "$PERMISSION_GROUP_NAME" '
        .result[]
        | select(.name == $name)
        | .id
    ' | head -n1)"

    if [[ -z "$group_id" || "$group_id" == "null" ]]; then
        log "${YELLOW}Grupos R2 disponíveis:${NC}"
        printf '%s' "$response" | jq -r '.result[] | select(.name | test("R2"; "i")) | "  - \(.name) (\(.id))"' >&2
        die "Permission group não encontrado: ${PERMISSION_GROUP_NAME}"
    fi

    printf '%s' "$group_id"
}

verify_bucket_exists() {
    local response
    response="$(cf_request GET "/accounts/${CLOUDFLARE_ACCOUNT_ID}/r2/buckets")"
    check_api_success "$response"

    local found
    found="$(printf '%s' "$response" | jq -r --arg bucket "$R2_BUCKET_NAME" '
        [.result.buckets[]? | select(.name == $bucket) | .name] | first // empty
    ')"

    if [[ -z "$found" ]]; then
        log "${YELLOW}Buckets R2 nesta conta:${NC}"
        printf '%s' "$response" | jq -r '.result.buckets[]?.name // empty' | sed 's/^/  - /' >&2
        die "Bucket não encontrado: ${R2_BUCKET_NAME}"
    fi
}

create_r2_token() {
    local permission_group_id="$1"
    local resource_key="com.cloudflare.edge.r2.bucket.${CLOUDFLARE_ACCOUNT_ID}_${R2_JURISDICTION}_${R2_BUCKET_NAME}"

    local payload
    payload="$(jq -n \
        --arg name "$TOKEN_NAME" \
        --arg pg_id "$permission_group_id" \
        --arg resource "$resource_key" \
        '{
            name: $name,
            policies: [
                {
                    effect: "allow",
                    permission_groups: [{ id: $pg_id }],
                    resources: { ($resource): "*" }
                }
            ]
        }')"

    local response
    response="$(cf_request POST "/accounts/${CLOUDFLARE_ACCOUNT_ID}/tokens" "$payload")"
    check_api_success "$response"

    printf '%s' "$response"
}

derive_secret_access_key() {
    local token_value="$1"
    printf '%s' "$token_value" | openssl dgst -sha256 -hex | awk '{print $2}'
}

usage() {
    cat <<'EOF'
Cria credenciais S3 (R2) para FastDL via API Cloudflare.

Autenticação (uma das opções):
  CLOUDFLARE_API_TOKEN
  ou
  CLOUDFLARE_EMAIL + CLOUDFLARE_GLOBAL_API_KEY

Obrigatório:
  CLOUDFLARE_ACCOUNT_ID  ID da conta (dashboard R2, canto direito)
  R2_BUCKET_NAME         Nome do bucket R2

Opcional:
  R2_JURISDICTION=default
  TOKEN_NAME=pterodactyl-fastdl
  R2_PUBLIC_HOST / R2_PUBLIC_URL

Exemplo com Global API Key:
  cp scripts/fastdl/env.example scripts/fastdl/.env
  # preencha CLOUDFLARE_EMAIL, CLOUDFLARE_GLOBAL_API_KEY, ACCOUNT_ID, BUCKET
  ./scripts/fastdl/create-r2-token.sh
EOF
}

main() {
    if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
        usage
        exit 0
    fi

    require_cmd curl jq openssl awk sed head

    load_env_file

    if ! resolve_auth_mode; then
        die "Defina CLOUDFLARE_API_TOKEN ou (CLOUDFLARE_EMAIL + CLOUDFLARE_GLOBAL_API_KEY)."
    fi

    [[ -n "${CLOUDFLARE_ACCOUNT_ID:-}" ]] || die "Defina CLOUDFLARE_ACCOUNT_ID."
    [[ -n "${R2_BUCKET_NAME:-}" ]] || die "Defina R2_BUCKET_NAME."

    if [[ "$CF_AUTH_MODE" == "global_key" ]]; then
        log "${YELLOW}Auth:${NC} Global API Key (${CLOUDFLARE_EMAIL})"
    else
        log "${YELLOW}Auth:${NC} API Token"
    fi

    log "${GREEN}→${NC} Verificando bucket ${R2_BUCKET_NAME}..."
    verify_bucket_exists

    log "${GREEN}→${NC} Buscando permission group..."
    local permission_group_id
    permission_group_id="$(find_permission_group_id)"
    log "   ${PERMISSION_GROUP_NAME} = ${permission_group_id}"

    log "${GREEN}→${NC} Criando token ${TOKEN_NAME}..."
    local response token_id token_value secret_key endpoint
    response="$(create_r2_token "$permission_group_id")"

    token_id="$(printf '%s' "$response" | json_get '.result.id')"
    token_value="$(printf '%s' "$response" | json_get '.result.value')"

    [[ -n "$token_id" && "$token_id" != "null" ]] || die "Não foi possível obter o id do token."
    [[ -n "$token_value" && "$token_value" != "null" ]] || die "Não foi possível obter o value do token (só aparece na criação)."

    secret_key="$(derive_secret_access_key "$token_value")"
    endpoint="https://${CLOUDFLARE_ACCOUNT_ID}.r2.cloudflarestorage.com"

    log ""
    log "${GREEN}Token R2 criado com sucesso.${NC} Guarde o Secret — ele não será exibido de novo."
    log ""
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log "  Campos para o nó FastDL (Admin → FastDL Nodes → S3)"
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log ""
    log "  Storage Type:              s3"
    log "  Bucket:                    ${R2_BUCKET_NAME}"
    log "  S3 Endpoint:               ${endpoint}"
    log "  Region:                    auto"
    log "  Access Key ID:             ${token_id}"
    log "  Secret Access Key:         ${secret_key}"
    log "  Use path-style endpoint:   Sim (marcar)"
    log "  Key Prefix:                /"
    if [[ -n "${R2_PUBLIC_HOST:-}" ]]; then
        log "  Public Host:               ${R2_PUBLIC_HOST}"
    fi
    if [[ -n "${R2_PUBLIC_URL:-}" ]]; then
        log "  Public URL:                ${R2_PUBLIC_URL}"
    elif [[ -n "${R2_PUBLIC_HOST:-}" ]]; then
        log "  Public URL:                https://${R2_PUBLIC_HOST}"
    fi
    log ""
    log "  URL FastDL por servidor:   {public_url}/{uuid8}/"
    log ""
    log "${YELLOW}Token Cloudflare (revogar no dashboard se vazar):${NC} ${TOKEN_NAME}"
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

main "$@"
