# phpMyAdmin bridge (por node)

Bridge SSO + signon para um phpMyAdmin **local** em cada node. Documentação completa: [`docs/phpmyadmin-sso.md`](../../docs/phpmyadmin-sso.md).

## Arquivos

| Arquivo | Função |
|---------|--------|
| `sso.php` | Valida HMAC, redeem no painel, preenche sessão signon |
| `config.user.inc.php` | `auth_type=signon`, host MariaDB, `PmaAbsoluteUri` |
| `login-required.php` | Sem SSO → 302 `https://hostgamer.net` |
| `docker-compose.example.yml` | Template Compose (`127.0.0.1:8088`) |
| `.env.example` | Secret / URI / host MariaDB |
| `deploy-node.sh` | Copia, gera compose e sobe o container |

## Deploy rápido

```bash
export NODE_SHORT=node050
export PMA_PUBLIC_HOST=phpmyadmin-node050.hostgamer.net
export PMA_MYSQL_HOST=10.8.0.6
export PHPMYADMIN_SSO_SECRET='<mesmo do painel>'

sudo ./deploy-node.sh /docker/hostgamer-phpmyadmin
```

No Cloudflare Tunnel: hostname `PMA_PUBLIC_HOST` → `http://127.0.0.1:8088`.
