# Plano de Implementação - Wallet e Integração de Pagamentos

> **Nota sobre cobrança horária:** A cobrança por hora ocorre enquanto o servidor existir e estiver ativo — **mesmo com o servidor desligado (stopped)**. O servidor reserva espaço no node (RAM, disco, etc.), portanto a cobrança é por hora de reserva, não por hora de CPU em uso. Ver `billing_architecture.md` para detalhes.

Este documento descreve o plano de implementação do sistema de Wallet (carteira de créditos) e o padrão de integração de pagamentos, iniciando com o método **Manual** como exemplo.

---

## 1. Padrão de Integração de Pagamentos (Strategy Pattern)

### 1.1 Arquitetura Proposta

```
┌─────────────────────────────────────────────────────────────────┐
│                    PaymentGatewayInterface                       │
│  + createPayment(amount, metadata): PaymentIntent               │
│  + confirmPayment(intentId): PaymentResult                       │
│  + getStatus(intentId): PaymentStatus                            │
└─────────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ ManualGateway   │  │ StripeGateway   │  │ PixGateway       │
│ (implementação  │  │ (futuro)         │  │ (futuro)         │
│  de exemplo)    │  │                  │  │                  │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

### 1.2 Fluxo Genérico

1. **Frontend** → chama API `POST /api/client/account/billing/deposit` com `{ amount, method }`
2. **Backend** → resolve o gateway pelo `method` (manual, stripe, pix)
3. **Gateway** → processa conforme sua lógica:
   - **Manual**: aprova imediatamente (para testes/admin)
   - **Stripe**: cria PaymentIntent, retorna client_secret
   - **Pix**: gera QR Code / copia-e-cola, aguarda webhook
4. **Backend** → credita na Wallet quando pagamento confirmado

---

## 2. Fase 1: Estrutura Base (Wallet + Manual)

### 2.1 Migrations

**Migration 1: `wallets`**
```php
Schema::create('wallets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->decimal('balance', 12, 2)->default(0);
    $table->timestamps();
    $table->unique('user_id');
});
```

**Migration 2: `wallet_transactions`**
```php
Schema::create('wallet_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['deposit', 'withdrawal', 'charge']);
    $table->decimal('amount', 12, 2);
    $table->decimal('balance_after', 12, 2)->nullable();
    $table->string('description')->nullable();
    $table->string('reference_type')->nullable(); // ex: 'server_charge', 'manual_deposit'
    $table->unsignedBigInteger('reference_id')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

**Migration 3: `payment_intents`** (para rastrear intenções de pagamento)
```php
Schema::create('payment_intents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('gateway'); // manual, stripe, pix
    $table->string('external_id')->nullable(); // id do Stripe/Pix
    $table->decimal('amount', 12, 2);
    $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
    $table->json('metadata')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

### 2.2 Models

- `Wallet` (belongsTo User, hasMany WalletTransaction)
- `WalletTransaction` (belongsTo Wallet)
- `PaymentIntent` (belongsTo User)

### 2.3 Serviços

- `WalletService`: `getBalance()`, `deposit()`, `withdraw()`, `charge()`
- `PaymentGatewayResolver`: retorna o gateway correto pelo método
- `ManualPaymentGateway`: implementa a interface, aprova na hora

### 2.4 Rotas API (api-client.php)

```php
Route::prefix('/account/billing')->group(function () {
    Route::get('/', [BillingController::class, 'index']);           // saldo + transações
    Route::post('/deposit', [BillingController::class, 'deposit']);     // iniciar depósito
});
```

### 2.5 Fluxo Manual

1. Usuário escolhe valor + método "Manual" no frontend
2. `POST /api/client/account/billing/deposit` → `{ amount: 10, method: 'manual' }`
3. `ManualPaymentGateway::createPayment()` → cria PaymentIntent com status `completed` imediatamente
4. `WalletService::deposit()` → credita na wallet, cria transação
5. Retorna sucesso com novo saldo

**Nota**: O método Manual pode ser restrito a admins ou habilitado em ambiente de desenvolvimento.

---

## 3. Fase 2: Integração Frontend

### 3.1 API Client (resources/scripts/api/)

- `getBillingInfo()` → GET balance + recent transactions
- `createDeposit(amount, method)` → POST deposit

### 3.2 Alterações no BillingContainer

- Buscar saldo real via API (substituir $14.50 hardcoded)
- Ao clicar "Pagar" no PaymentModal → chamar `createDeposit()`
- Exibir transações reais na tabela "Recent Transactions"

### 3.3 PaymentModal

- Enviar `method: 'manual'` ou `'stripe'` ou `'pix'` conforme seleção
- Tratar resposta: sucesso (fechar modal, atualizar saldo) ou erro (exibir flash)

---

## 4. Fase 3: Stripe (Futuro)

### 4.1 StripeGateway

- `createPayment()` → Stripe API cria PaymentIntent, retorna `client_secret`
- Frontend usa Stripe.js / Elements para coletar cartão
- Webhook `/webhooks/stripe` → confirma pagamento → credita wallet

### 4.2 Fluxo

1. Frontend chama `deposit` com `method: 'stripe'`
2. Backend retorna `{ client_secret, intent_id }`
3. Frontend renderiza Stripe Elements, usuário paga
4. Webhook recebe `payment_intent.succeeded` → credita wallet

---

## 5. Fase 4: Pix (Futuro)

### 5.1 PixGateway

- Integração com provedor (ex: Mercado Pago, Asaas, ou API Pix do banco)
- `createPayment()` → gera QR Code + copia-e-cola
- Webhook ou polling → confirma pagamento → credita wallet

### 5.2 Fluxo

1. Frontend chama `deposit` com `method: 'pix'`
2. Backend retorna `{ qr_code, copy_paste, expires_at }`
3. Frontend exibe QR/copia-e-cola
4. Webhook ou job de polling detecta pagamento → credita wallet

---

## 6. Ordem de Implementação Sugerida

| # | Tarefa | Prioridade |
|---|--------|------------|
| 1 | Migrations (wallets, wallet_transactions, payment_intents) | Alta |
| 2 | Models (Wallet, WalletTransaction, PaymentIntent) | Alta |
| 3 | WalletService | Alta |
| 4 | PaymentGatewayInterface + ManualPaymentGateway | Alta |
| 5 | BillingController + rotas API | Alta |
| 6 | API client no frontend (getBillingInfo, createDeposit) | Alta |
| 7 | Conectar BillingContainer ao backend real | Alta |
| 8 | Conectar PaymentModal ao createDeposit | Alta |
| 9 | Adicionar método "Manual" no PaymentModal (se não existir) | Média |
| 10 | StripeGateway + webhook | Futuro |
| 11 | PixGateway + webhook | Futuro |

---

## 7. Considerações de Segurança

- **Manual**: considerar `config('billing.manual_enabled')` e/ou restrição a `root_admin`
- **Idempotência**: PaymentIntent com `external_id` único evita duplicação
- **Webhooks**: validar assinatura (Stripe) ou token (Pix)
- **Transações DB**: usar `DB::transaction()` em deposit/charge

---

## 8. Configuração (config/billing.php)

```php
return [
    'manual_enabled' => env('BILLING_MANUAL_ENABLED', false),
    'stripe_enabled' => env('BILLING_STRIPE_ENABLED', false),
    'pix_enabled' => env('BILLING_PIX_ENABLED', false),
    'currency' => 'USD',
];
```
