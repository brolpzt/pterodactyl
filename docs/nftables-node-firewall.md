# Firewall nftables no Node

O painel gere uma chain nftables dedicada em cada node com Wings, com **sync completo** (o que não estiver na lista desejada é removido).

## Chain

```
table inet ptero {
  chain ptero_ports {
    type filter hook input priority -10; policy accept;
    # rules ACCEPT geridas pelo sync: ip daddr + tcp|udp dport
  }
}
```

- `policy accept` mantém SSH/Wings acessíveis; o valor do sync é inventário idempotente das portas de jogo.
- Comentário por rule: `ptero:<ip>:<port>:<proto>`.

## Flags

| Campo | Onde | Significado |
|-------|------|-------------|
| `nodes.nftables_enabled` | Node | Node usa sync nftables |
| `allocations.nftables_managed` | Allocation | Porta entra na lista desejada |

**Exclusão mútua com Gcore:** se `nftables_managed=true` então `gcore_protected=false`, e vice-versa. Lifecycle Gcore só processa `gcore_protected`; nftables só `nftables_managed`.

## Protocolos (egg `port_slots`)

Mesmo modelo da Gcore (`gcore_proto` / `gcore_policy`):

- `gcore_proto` vazio ou `any` → TCP + UDP (se houver policy ou proto `any`)
- `tcp` / `udp` → uma rule
- ambos vazios → skip (não abre)

## API Wings

`PUT /api/system/nftables` (auth de sistema do daemon):

```json
{
  "rules": [
    { "ip": "89.x.x.x", "port": 7777, "proto": "udp" }
  ]
}
```

Wings faz: garantir table/chain → `flush chain` → `add rule` para cada entrada.

## Lifecycle

- **Create server:** se node `nftables_enabled` e allocs `nftables_managed` → sync da chain.
- **Delete server:** desmarca `nftables_managed` dessas allocs → sync.
- Admin: aba **Firewall Nftables** (toggle + Sync agora + lista desejada) e checkbox em **Assign New Allocations**.

## Deploy Wings

Requisitos no node: pacote `nftables` (`/usr/sbin/nft`), processo Wings como root (`CAP_NET_ADMIN`).

```bash
# Build (Docker se não houver Go local)
cd wings
docker run --rm -v "$PWD":/src -w /src golang:1.25-bookworm \
  go build -o build/wings_linux_amd64 -ldflags="-s -w" wings.go

# Node (ex.: node050 / 10.8.0.6)
scp build/wings_linux_amd64 debian@NODE:/tmp/wings_nft_new
ssh debian@NODE 'sudo systemctl stop wings && sudo install -m 755 /tmp/wings_nft_new /usr/local/bin/wings && sudo systemctl start wings'

# Smoke após Sync no painel
sudo nft list chain inet ptero ptero_ports
```

**Já deployado:** `hostgamer-01` (`10.8.0.6`, binário em `/usr/local/bin/wings`) com suporte a `PUT /api/system/nftables`.

No painel: migration `2026_10_11_000000_add_nftables_flags_to_nodes_and_allocations`, ativar o node, marcar allocations, **Sync agora**.

## Painel (código)

- `app/Services/Nftables/NftablesFirewallSyncService.php`
- `app/Repositories/Wings/DaemonNftablesRepository.php`
- Wings: `wings/nftables/`, rota em `wings/router/router.go`
