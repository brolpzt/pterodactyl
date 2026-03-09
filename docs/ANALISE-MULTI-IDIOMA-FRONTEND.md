# Análise: Multi-idioma no frontend do Pterodactyl

## Estado atual

### 1. Infraestrutura já existente

- **i18n no frontend** (`resources/scripts/i18n.ts`):
  - Usa **i18next** + **react-i18next**.
  - Idioma inicial: `lng: 'en'`.
  - As traduções são carregadas via HTTP do **backend Laravel**, não de ficheiros estáticos no frontend.

- **Backend** (`app/Http/Controllers/Base/LocaleController.php`):
  - Rota: `GET /locales/locale.json?locale={{lng}}&namespace={{ns}}`.
  - Parâmetros:
    - `locale`: 2 letras minúsculas (ex.: `en`, `pt`, `es`) — validado por `LocaleRequest`.
    - `namespace`: nome do “grupo” de traduções (ex.: `activity`, `strings`).
  - O controller usa o **Loader de traduções do Laravel**: `$this->loader->load($locale, $namespace)`.
  - Ou seja, as chaves vêm dos ficheiros em **`resources/lang/{locale}/{namespace}.php`** (estrutura Laravel).

- **Ficheiros de idioma no projeto**:
  - Existe apenas a pasta **`resources/lang/en/`** (inglês).
  - Exemplos de “namespaces” (ficheiros): `activity.php`, `strings.php`, `auth.php`, `validation.php`, `dashboard/account.php`, etc.

- **Uso no código**:
  - O único uso de tradução no frontend está em **Activity Log**:  
    `resources/scripts/components/elements/activity/ActivityLogEntry.tsx`  
    usa `<Translate ns={'activity'} ... />` com chaves do tipo `server.power.start`, `auth.fail`, etc.
  - Todo o resto da interface está em texto fixo (PT/EN misturado), sem `useTranslation` nem `t()`.

- **Botão de idioma** (`resources/scripts/components/NavigationBar.tsx`):
  - Há um dropdown com línguas (US, BR, ES) e estado local `language` com `{ code, flag }`.
  - Ao escolher uma língua só se faz `setLanguage(lang)` — **não** se chama `i18n.changeLanguage()`.
  - Conclusão: o botão **não está ligado ao i18n**; mudar de língua não altera as traduções.

---

## O que é preciso para “gerar as palavras nos outros idiomas”

Não há geração automática: as “palavras” nos outros idiomas são **ficheiros de tradução que vocês criam e mantêm**, no backend Laravel, e o frontend passa a pedir o locale correto.

### Passo 1: Criar ficheiros de tradução no backend (Laravel)

Para cada idioma (ex.: português, espanhol):

1. Criar pastas em **`resources/lang/`** com código de 2 letras minúsculas, por exemplo:
   - `resources/lang/pt/`  (português)
   - `resources/lang/es/`  (espanhol)

2. Para cada **namespace** que o frontend usar, criar um ficheiro com a **mesma estrutura de chaves** que em `en/`, mas com os textos no novo idioma.

   Exemplo para o namespace **activity** (o único que o frontend usa atualmente):

   - Copiar **`resources/lang/en/activity.php`** para **`resources/lang/pt/activity.php`**.
   - Substituir os valores em inglês pelos equivalentes em português (mantendo as chaves e placeholders como `:name`, `:count`, etc.; o backend converte `:x` para `{{x}}` para o i18next).

   O mesmo para **`resources/lang/es/activity.php`**, etc.

3. Quando mais partes do frontend forem migradas para i18n (por exemplo um namespace `strings` ou `dashboard`), será preciso:
   - Ter esse namespace em **`resources/lang/en/`** (ex.: `strings.php`).
   - Criar os mesmos ficheiros em **`resources/lang/pt/`**, **`resources/lang/es/`**, etc., com as mesmas chaves e os textos traduzidos.

Resumo: **“gerar as palavras nos outros idiomas” = criar e editar manualmente (ou com ferramentas de tradução) os ficheiros em `resources/lang/{pt,es,...}/*.php`**, espelhando a estrutura de `resources/lang/en/`.

### Passo 2: Ligar o botão de idioma ao i18n

O backend espera `locale` em **2 letras minúsculas** (`en`, `pt`, `es`). O dropdown usa códigos como `BR`, `US`, `ES`.

- Mapear no frontend:
  - `US` → `en`
  - `BR` → `pt`
  - `ES` → `es`
- Ao selecionar uma língua no dropdown:
  1. Chamar **`i18n.changeLanguage(localeI18n)`** (ex.: `changeLanguage('pt')`).
  2. Opcional: guardar a preferência (ex.: `localStorage` ou API) e definir `i18n.language` na inicialização para manter a escolha entre sessões.

Assim, quando o utilizador escolher “Português (BR)”, o frontend passará a pedir `/locales/locale.json?locale=pt&namespace=activity` (e outros namespaces quando existirem), e o Laravel devolverá o conteúdo de **`resources/lang/pt/activity.php`** (após conversão para o formato JSON esperado pelo i18next).

### Passo 3: Alargar o uso de traduções no frontend

Para o resto da interface mudar de idioma:

1. Definir um ou mais namespaces (ex.: `strings`, `dashboard`, `server`).
2. Em **`resources/lang/en/`** (e depois em `pt/`, `es/`) criar os ficheiros correspondentes com chaves tipo:
   - `dashboard.title`, `account.settings`, `server.console`, etc.
3. Nos componentes:
   - Usar **`useTranslation('strings')`** (ou o namespace que criaram) e **`t('chave')`** em vez de texto fixo.
   - Para textos com variáveis: `t('chave', { name: '...' })` (placeholders `{{name}}` nos ficheiros PHP/JSON).

Assim, as “palavras” nos outros idiomas vêm dos ficheiros que vocês criarem em `resources/lang/{pt,es,...}/`; o frontend apenas pede o locale e o namespace e mostra o valor devolvido pelo backend.

---

## Resumo

| O que querem | O que fazer |
|--------------|-------------|
| Ter as palavras noutros idiomas | Criar ficheiros em `resources/lang/pt/`, `resources/lang/es/`, etc., com a mesma estrutura de chaves que em `resources/lang/en/`, com textos traduzidos. |
| O botão mudar de idioma ter efeito | Ligar o dropdown ao i18n: mapear BR→pt, US→en, ES→es e chamar `i18n.changeLanguage(locale)`. |
| Traduzir mais áreas da interface | Introduzir `useTranslation` + `t()` nos componentes e adicionar as chaves nos ficheiros de cada locale. |

Não existe no projeto um gerador automático de traduções; a abordagem é a clássica: ficheiros de chave–valor por locale e namespace, servidos pelo Laravel e carregados pelo i18next no frontend.
