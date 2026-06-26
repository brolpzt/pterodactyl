# SSO — Integração com painel externo

Este documento descreve como integrar um painel de controle externo (ex.: `clientarea.hostgamer.net`) com o Pterodactyl (`control.hostgamer.net`) para que o cliente acesse diretamente a tela de um servidor **sem digitar senha**, de forma segura.

## Visão geral

1. O cliente está autenticado no **seu painel externo**.
2. Ao clicar em "Gerenciar servidor", o **backend do seu painel** gera um link assinado (válido por poucos segundos, uso único).
3. O navegador do cliente é redirecionado para o Pterodactyl.
4. O Pterodactyl valida a assinatura, cria a sessão web e redireciona para `/server/{uuidShort}`.

O segredo compartilhado **nunca** deve estar no frontend (JavaScript). Apenas o servidor do seu painel gera os links.

---

## Configuração no Pterodactyl

Adicione ao `.env` do painel:

```env
SSO_ENABLED=true
SSO_SECRET=cole-aqui-uma-string-aleatoria-longa-minimo-64-caracteres
SSO_TOKEN_TTL=60
SSO_USER_IDENTIFIER=external_id
```

| Variável | Descrição |
|----------|-----------|
| `SSO_ENABLED` | `true` para ativar o endpoint |
| `SSO_SECRET` | Segredo compartilhado com o painel externo (mesmo valor nos dois lados) |
| `SSO_TOKEN_TTL` | Tempo máximo de validade do link em segundos (padrão: 60) |
| `SSO_USER_IDENTIFIER` | `external_id` (recomendado) ou `id` |
| `SSO_ALLOW_SERVER_EXTERNAL_ID` | `true` para aceitar `servers.external_id` no parâmetro `server` |
| `SSO_ALLOWED_IPS` | Opcional: IPs permitidos separados por vírgula (raramente necessário) |

Após alterar o `.env`:

```bash
php artisan config:cache
```

### Pré-requisitos de dados

- Cada usuário no Pterodactyl deve ter `external_id` preenchido com o ID do cliente no seu painel.
- O parâmetro `server` na URL deve ser o **`uuidShort`** do servidor (ex.: `f42a054e`), visível na URL do painel.

Você pode definir `external_id` via API Application ao criar usuários/servidores, ou diretamente no admin.

---

## Endpoint

```
GET https://control.hostgamer.net/auth/sso
```

### Parâmetros (query string)

| Parâmetro | Obrigatório | Descrição |
|-----------|-------------|-----------|
| `user` | Sim | `external_id` do usuário no Pterodactyl |
| `server` | Sim | `uuidShort` do servidor (8 caracteres) |
| `expires` | Sim | Unix timestamp (segundos) de expiração |
| `nonce` | Sim | Valor aleatório hexadecimal (mín. 16 chars) |
| `signature` | Sim | HMAC-SHA256 do payload (ver abaixo) |

### Resposta

- **Sucesso:** redirect `302` para `/server/{uuidShort}` com sessão autenticada.
- **Falha:** `403 Forbidden` com mensagem genérica (não expõe detalhes do erro).

### Segurança embutida

- Assinatura HMAC com segredo compartilhado
- Link expira rapidamente (`expires` + `SSO_TOKEN_TTL`)
- `nonce` de uso único (replay impossível)
- Verificação de que o usuário é dono ou subusuário do servidor
- Rate limit: 30 requisições/minuto por IP
- Eventos de auditoria: `auth:sso` e `auth:sso-fail`

Usuários com 2FA (TOTP) **não precisam** do checkpoint ao entrar via SSO — o painel externo já autenticou o cliente.

---

## Algoritmo de assinatura

Os quatro parâmetros abaixo formam o **payload canônico** (ordem fixa):

```
user={user}&server={server}&expires={expires}&nonce={nonce}
```

Regras:

- Use `rawurlencode` / `PHP_QUERY_RFC3986` em cada valor.
- `expires` é string numérica no payload.
- `signature` **não** entra no payload.

Assinatura:

```
signature = HMAC-SHA256(payload, SSO_SECRET)
```

Compare com `hash_equals` no PHP (comparação segura contra timing attacks).

---

## Implementação no painel externo (PHP)

### Funções auxiliares

```php
<?php

function pterodactyl_sso_payload(string $user, string $server, int $expires, string $nonce): string
{
    return http_build_query([
        'user' => $user,
        'server' => $server,
        'expires' => (string) $expires,
        'nonce' => $nonce,
    ], '', '&', PHP_QUERY_RFC3986);
}

function pterodactyl_sso_sign(string $user, string $server, int $expires, string $nonce, string $secret): string
{
    return hash_hmac('sha256', pterodactyl_sso_payload($user, $server, $expires, $nonce), $secret);
}

function pterodactyl_sso_url(
    string $panelUrl,
    string $userExternalId,
    string $serverUuidShort,
    string $secret,
    int $ttlSeconds = 60
): string {
    $expires = time() + $ttlSeconds;
    $nonce = bin2hex(random_bytes(16));
    $signature = pterodactyl_sso_sign($userExternalId, $serverUuidShort, $expires, $nonce, $secret);

    $query = http_build_query([
        'user' => $userExternalId,
        'server' => $serverUuidShort,
        'expires' => $expires,
        'nonce' => $nonce,
        'signature' => $signature,
    ], '', '&', PHP_QUERY_RFC3986);

    return rtrim($panelUrl, '/') . '/auth/sso?' . $query;
}
```

### Rota no seu painel (exemplo Laravel)

```php
// routes/web.php — usuário já autenticado no seu painel
Route::get('/servers/{service}/manage', function (Service $service) {
  abort_unless($service->user_id === auth()->id(), 403);

  $url = pterodactyl_sso_url(
      panelUrl: config('pterodactyl.url'),           // https://control.hostgamer.net
      userExternalId: (string) auth()->user()->id,  // mesmo valor em users.external_id
      serverUuidShort: $service->pterodactyl_uuid_short,
      secret: config('pterodactyl.sso_secret'),
      ttlSeconds: 60,
  );

  return redirect()->away($url);
})->middleware('auth')->name('servers.manage');
```

### Botão na interface

```html
<a href="{{ route('servers.manage', $service) }}" class="btn btn-primary">
  Gerenciar servidor
</a>
```

O link aponta para **seu** backend, que gera o SSO e redireciona — nunca gere a assinatura no JavaScript.

---

## Implementação em Node.js

```javascript
const crypto = require('crypto');

function buildPayload(user, server, expires, nonce) {
  const params = new URLSearchParams();
  params.append('user', user);
  params.append('server', server);
  params.append('expires', String(expires));
  params.append('nonce', nonce);
  return params.toString();
}

function sign(user, server, expires, nonce, secret) {
  const payload = buildPayload(user, server, expires, nonce);
  return crypto.createHmac('sha256', secret).update(payload).digest('hex');
}

function buildSsoUrl(panelUrl, userExternalId, serverUuidShort, secret, ttlSeconds = 60) {
  const expires = Math.floor(Date.now() / 1000) + ttlSeconds;
  const nonce = crypto.randomBytes(16).toString('hex');
  const signature = sign(userExternalId, serverUuidShort, expires, nonce, secret);

  const query = new URLSearchParams({
    user: userExternalId,
    server: serverUuidShort,
    expires: String(expires),
    nonce,
    signature,
  });

  return `${panelUrl.replace(/\/$/, '')}/auth/sso?${query}`;
}
```

---

## Exemplo de URL final

```
https://control.hostgamer.net/auth/sso?user=client-12345&server=f42a054e&expires=1719876543&nonce=a1b2c3d4e5f6789012345678abcdef01&signature=9f3c...
```

Após o redirect, o cliente verá:

```
https://control.hostgamer.net/server/f42a054e
```

---

## Checklist de implantação

- [ ] Gerar `SSO_SECRET` forte (ex.: `openssl rand -hex 64`)
- [ ] Configurar o mesmo segredo no painel externo
- [ ] Garantir `external_id` em todos os usuários sincronizados
- [ ] Armazenar `uuidShort` de cada servidor no painel externo
- [ ] Botão chama rota server-side (nunca JS)
- [ ] `SSO_ENABLED=true` no Pterodactyl
- [ ] `php artisan config:cache` após deploy
- [ ] Testar: link válido, link expirado, link reutilizado, servidor de outro usuário

---

## Solução de problemas

| Sintoma | Causa provável |
|---------|----------------|
| 403 sempre | Segredo diferente entre painéis, payload fora de ordem, ou `expires` no passado |
| User not found | `external_id` não definido no Pterodactyl |
| Server not found | `uuidShort` incorreto ou usuário não é dono/subusuário |
| Link já usado | Cliente atualizou a página — gere um novo link |
| SSO disabled | `SSO_ENABLED=false` ou `config:cache` desatualizado |

Consulte os activity logs do Pterodactyl (`auth:sso`, `auth:sso-fail`) para auditoria.

---

## Referência interna

A lógica de assinatura no Pterodactyl está em `Pterodactyl\Services\Auth\SsoLoginService::buildPayload()` e `::sign()`.

Use essas funções como referência para manter compatibilidade entre sistemas.
