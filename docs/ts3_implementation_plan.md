# Plano de Implementacao - Painel TS3 (Egg 12)

Este documento descreve o plano para integrar um painel de controle TeamSpeak 3 no Pterodactyl quando o servidor utilizar o Egg TS3 (no seu caso, `egg_id = 12`).

## Status de implementacao (atual)

### Fases

- Fase 1: concluida;
- Fase 2: concluida;
- Fase 3: concluida;
- Fase 4: concluida;
- Fase 5: nao iniciada;
- Fase 6: nao iniciada;
- Fase 7: nao iniciada.

### Concluido nesta etapa

- Exposicao de `egg_id` no payload de servidor (backend + frontend mapper);
- Helper central de dominio TS3 criado: `app/Support/ServerType.php` com `isTs3(Server $server)`;
- Bloqueio backend de `Files` para TS3 via middleware;
- Bloqueio backend de `Backups` para TS3 via middleware;
- Garantia de bloqueio backend independente da UI (rotas protegidas no servidor para acesso direto por API/URL);
- Ocultacao de `Files` e `Backups` no sidebar para TS3;
- Redirecionamento de rotas bloqueadas (`/files` e `/backups`) no frontend para TS3;
- API TS3 inicial via Query com endpoints de:
  - overview (`/ts3/overview`);
  - actions (`/ts3/actions/{action}` para start/stop/restart/reinstall);
  - snapshots (`/ts3/snapshots`, create/restore/delete);
  - bans (`/ts3/bans`);
  - tokens (`/ts3/tokens`);
  - logs (`/ts3/logs`);
  - html viewer (`/ts3/html-viewer`).
- Tratamento de erros TS3 Query padronizado com excecao dedicada:
  - `app/Exceptions/Service/Ts3/Ts3QueryException.php`.
- Auditoria de operacoes sensiveis implementada para gerenciamento de bans/tokens.
- Frontend TS3 completo com menu, rotas e telas:
  - `/ts3` (overview com tabela de informacoes + service management);
  - `/ts3/snapshots`;
  - `/ts3/bans`;
  - `/ts3/tokens`;
  - `/ts3/logs`;
  - `/ts3/html-viewer`.
- Roteamento condicional por tipo de servidor:
  - TS3 exibe menu dedicado;
  - nao-TS3 nao acessa rotas `/ts3/*`.

### Pendente (proximas etapas)

- snapshots TS3 completos (listar/criar/restaurar/deletar);
- telas frontend TS3 (overview em tabela, bans, tokens, logs, html viewer, snapshots);
- amarrar permissoes dedicadas `ts3.*` (opcional ideal);
- testes automatizados backend/frontend para os novos endpoints e fluxos TS3.

## Objetivo

Quando um servidor for TS3:

- exibir um painel TS3 dedicado no frontend;
- executar acoes TS3 via backend e TS3 ServerQuery;
- usar snapshots TS3 no lugar de backups tradicionais;
- desabilitar completamente o acesso ao menu e endpoints de `Files`.

## Escopo do MVP

- Deteccao de servidor TS3 por `egg_id = 12`;
- menu condicional no frontend (ocultar `Files` e `Backups`);
- bloqueio real no backend para rotas `/files/*` e `/backups/*` em servidores TS3;
- endpoints de snapshots TS3 (listar, criar, restaurar, deletar);
- endpoints TS3 para tokens, bans, logs e html viewer;
- visao TS3 completa com tabela de informacoes do servidor e acoes principais;
- todas as funcoes TS3 (tokens, bans, html viewer, logs e snapshots) comunicando via TS3 Query no backend.

## Fora do escopo inicial

- comando SQL livre para query TS3;
- automacoes avancadas de music bot;
- redesign visual completo do painel.

---

## Fase 0 - Preparacao e decisoes

1. Confirmar regra de identificacao TS3:
   - curto prazo: `egg_id === 12`;
   - medio prazo: feature flag no Egg (ex.: `ts3_panel`).
2. Definir estrategia de conexao:
   - opcao rapida: Panel conecta direto no TS3 Query;
   - opcao robusta: proxy via Wings para evitar limitacoes de rede.
3. Definir credenciais de query:
   - variaveis do servidor (host, query_port, user, pass, virtual_server_id/port).

## Fase 1 - Base backend e dados do servidor

1. Expor `egg_id` no payload do servidor:
   - ajustar `app/Transformers/Api/Client/ServerTransformer.php`;
   - ajustar tipagem/mapeamento em `resources/scripts/api/server/getServer.ts`.
2. Criar helper de dominio TS3:
   - ex.: `app/Support/ServerType.php` com `isTs3(Server $server): bool`.
3. Criar servico de query:
   - ex.: `app/Services/Ts3/Ts3QueryService.php`;
   - responsabilidades: conectar, autenticar, selecionar virtual server e executar comandos permitidos.
4. Padronizar tratamento de erro:
   - timeout;
   - credenciais invalidas;
   - permissao negada;
   - indisponibilidade do servidor TS3.

## Fase 2 - Seguranca e bloqueios obrigatorios

1. Criar middleware para bloquear arquivos em TS3:
   - ex.: `DenyFilesForTs3`;
   - aplicar no grupo `/files` em `routes/api-client.php`.
2. Criar middleware para bloquear backups tradicionais em TS3:
   - ex.: `DenyBackupsForTs3`;
   - aplicar no grupo `/backups` em `routes/api-client.php`.
3. Garantir bloqueio no backend independente da UI:
   - mesmo com acesso direto por URL, token, script ou API client.
4. Registrar auditoria para operacoes sensiveis:
   - criar/restaurar/deletar snapshot;
   - gerenciamento de bans/tokens (quando implementados).

## Fase 3 - API TS3 (controle completo via Query)

Criar controllers dedicados para modulos TS3, por exemplo:

- `app/Http/Controllers/Api/Client/Servers/Ts3OverviewController.php`
- `app/Http/Controllers/Api/Client/Servers/Ts3SnapshotController.php`
- `app/Http/Controllers/Api/Client/Servers/Ts3BanController.php`
- `app/Http/Controllers/Api/Client/Servers/Ts3TokenController.php`
- `app/Http/Controllers/Api/Client/Servers/Ts3LogController.php`
- `app/Http/Controllers/Api/Client/Servers/Ts3HtmlViewerController.php`

Endpoints sugeridos:

- `GET /api/client/servers/{server}/ts3/overview` - status e informacoes do servidor;
- `POST /api/client/servers/{server}/ts3/actions/start` - iniciar;
- `POST /api/client/servers/{server}/ts3/actions/stop` - parar;
- `POST /api/client/servers/{server}/ts3/actions/restart` - reiniciar;
- `POST /api/client/servers/{server}/ts3/actions/reinstall` - reinstalar;
- `GET /api/client/servers/{server}/ts3/snapshots` - listar snapshots;
- `POST /api/client/servers/{server}/ts3/snapshots` - criar snapshot;
- `POST /api/client/servers/{server}/ts3/snapshots/{id}/restore` - restaurar snapshot;
- `DELETE /api/client/servers/{server}/ts3/snapshots/{id}` - deletar snapshot;
- `GET /api/client/servers/{server}/ts3/bans` - listar bans;
- `POST /api/client/servers/{server}/ts3/bans` - adicionar ban;
- `DELETE /api/client/servers/{server}/ts3/bans/{id}` - remover ban;
- `GET /api/client/servers/{server}/ts3/tokens` - listar tokens;
- `POST /api/client/servers/{server}/ts3/tokens` - gerar token;
- `DELETE /api/client/servers/{server}/ts3/tokens/{id}` - revogar token;
- `GET /api/client/servers/{server}/ts3/logs` - consultar logs do TS3;
- `GET /api/client/servers/{server}/ts3/html-viewer` - conteudo/URL do html viewer.

Regras:

- validar que servidor e TS3 (`egg_id = 12`);
- validar permissao por acao;
- aplicar rate limit para restore/delete;
- respostas padronizadas para erros operacionais.
- manter whitelist de comandos permitidos no `Ts3QueryService` (sem comando livre);
- obrigatorio: tokens, bans, html viewer e logs usarem `Ts3QueryService` como unica via de comunicacao com TS3 Query.

## Fase 4 - Frontend (menu e rotas TS3)

1. Atualizar rotas em `resources/scripts/routers/routes.ts`:
   - adicionar `'/ts3'`, `'/ts3/snapshots'`, `'/ts3/bans'`, `'/ts3/tokens'`, `'/ts3/logs'`, `'/ts3/html-viewer'`;
   - para TS3, ocultar `'/files'` e `'/backups'`.
2. Ajustar sidebar em `resources/scripts/components/Sidebar.tsx`:
   - renderizacao condicional por `eggId`;
   - novos itens `TS3`, `Snapshots`, `Bans`, `Tokens`, `Logs`, `HTML Viewer`.
3. Ajustar roteamento em `resources/scripts/routers/ServerRouter.tsx`:
   - redirecionar acesso direto a `/files` e `/backups` para `/ts3` em servidores TS3.
4. Criar containers iniciais:
   - `Ts3OverviewContainer`;
   - `Ts3SnapshotsContainer`;
   - `Ts3BansContainer`;
   - `Ts3TokensContainer`;
   - `Ts3LogsContainer`;
   - `Ts3HtmlViewerContainer`.
5. Implementar layout semelhante a referencia da imagem:
   - tabela de informacoes do servidor (status, IP, porta, versao, uptime, clients/channels online, TS DNS);
   - blocos de acao com botoes para gerenciamento de servico, bans, tokens, snapshots, logs e html viewer.

## Fase 5 - Permissoes

Opcao 1 (MVP rapido):

- reutilizar `backup.*` para snapshots;
- manter permissao existente para controle basico.

Opcao 2 (ideal):

- criar grupo `ts3.*` em `app/Models/Permission.php`, com:
  - `ts3.read`;
  - `ts3.snapshot.create`;
  - `ts3.snapshot.restore`;
  - `ts3.snapshot.delete`;
  - `ts3.ban.read/create/delete`;
  - `ts3.token.read/create/delete`.

## Fase 6 - Testes

1. Backend (feature tests):
   - servidor TS3 recebe 403 em `/files/*` e `/backups/*`;
   - endpoints TS3 de snapshots, bans, tokens, logs e html viewer funcionam e validam permissao;
   - validar que os modulos citados chamam o `Ts3QueryService`.
2. Frontend:
   - `Files` e `Backups` nao aparecem em TS3;
   - redirecionamento ao acessar URL bloqueada;
   - telas TS3 renderizam com tabela de informacoes e blocos de controle;
   - fluxos de tokens, bans, snapshots, logs e html viewer respondem conforme API.
3. Regressao:
   - servidores nao-TS3 permanecem sem alteracoes de comportamento.

## Fase 7 - Rollout

1. Habilitar por feature flag (recomendado):
   - ex.: `TS3_PANEL_ENABLED=true`.
2. Liberar primeiro em homologacao.
3. Monitorar:
   - erros de conexao Query;
   - latencia de chamadas;
   - taxa de falha em restore de snapshots.
4. Liberar gradualmente em producao.

---

## Checklist objetivo (resumo)

- [x] Expor `egg_id` no payload client/server;
- [x] Criar helper `isTs3()` central;
- [x] Bloquear backend `/files/*` para TS3;
- [x] Bloquear backend `/backups/*` para TS3;
- [x] Criar API TS3 completa (`overview`, `actions`, `snapshots`, `bans`, `tokens`, `logs`, `html-viewer`);
- [x] Garantir que `tokens`, `bans`, `logs`, `html-viewer` e `snapshots` usem TS3 Query via backend;
- [x] Criar menu e rotas TS3 no frontend;
- [x] Ocultar `Files` e `Backups` para TS3;
- [x] Redirecionar URLs bloqueadas para `/ts3`;
- [x] Implementar tabela de informacoes do servidor TS3 no frontend (layout inspirado na referencia);
- [ ] Implementar testes backend/frontend;
- [ ] Fazer rollout com monitoramento.

## Definicao de pronto (DoD)

- Servidor TS3 (`egg_id = 12`) nao possui acesso ao `Files` pela UI nem pela API;
- Backups tradicionais ficam desativados para TS3;
- Controles TS3 funcionam via Query (snapshots, bans, tokens, logs e html viewer);
- Painel TS3 exibe tabela de informacoes do servidor e acoes de gerenciamento;
- Servidores nao-TS3 continuam com comportamento atual;
- Auditoria registrada para operacoes criticas TS3.
