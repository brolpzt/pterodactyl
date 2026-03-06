# Arquitetura de Faturamento Híbrido (Pré-pago com Wallet)

Este documento detalha a arquitetura do sistema de faturamento sugerido para a plataforma de Game Hosting integrada ao painel Pterodactyl. O modelo abandona a venda clássica de "Slots" em favor da venda de recursos computacionais (RAM, CPU, Disco) utilizando um sistema de créditos pré-pagos.

## 1. Visão Geral

O sistema baseia-se em um modelo onde o usuário adiciona fundos à sua conta (criando um saldo na sua "Wallet" ou Carteira). A partir desse saldo, o sistema desconta o valor do uso dos servidores de duas formas distintas, escolhidas pelo usuário no momento do checkout:
- **Cobrança por Hora (Hourly):** Pagamento flexível para uso temporário. A cobrança ocorre enquanto o servidor existir e estiver ativo — **mesmo com o servidor desligado (stopped)**. O servidor reserva espaço no node (RAM, disco, etc.), portanto a cobrança é por hora de reserva, não por hora de CPU em uso.
- **Cobrança por Período (Mensal, Trimestral, etc.):** Desconto integral antecipado na contratação, para uso prolongado e previsível.

## 2. A "Wallet" (Carteira de Créditos)

Toda a movimentação financeira ocorre na carteira do usuário. 
- **Adição de Fundos:** O usuário realiza um pagamento na plataforma (via PIX, Stripe, etc.) de um montante à escolha (ex: R$ 50,00). O valor constará como saldo adicionado ao `balance` da sua conta.
- Não existem faturas de cartão de crédito abertas/atrasadas. O usuário só consome o que já pagou (Zero Inadimplência).
- Quando o saldo chega a \`$0.00\`, os serviços atrelados a pagamentos da carteira são automaticamente suspensos através de uma API Call para o node/Pterodactyl (`Suspend Server`).

## 3. Estruturação dos Planos e Matemática de Preços

Ao invés de vender slots no jogo limitando o usuário via arquivo de configuração (ex: 32 slots), a plataforma vende limites computacionais. O preço base é calculado pela hora, mas incentiva-se a assinatura longa com descontos progressivos.

### Exemplo de Aplicação: Plano "Retro" (1GB RAM, 1 vCPU, 10GB NVMe)
- **Por Hora (Flexível):** \`$0.02 / hora\`.
  *Gastando parte da carteira hora a hora. É extremamente vantajoso para o jogador que pretende jogar num final de semana e então deletar o servidor logo após para cessar a cobrança.*
- **Mensal (1 Mês):** O preço base da hora cai para \`$0.015\` (~25% de desconto). Total: **$10.95 mensais**.
  *O sistema desconta o valor integral da carteira imeditamente e garante o acesso ao servidor pelos próximos 30 dias. Nenhuma fração por hora continuará a ser cobrada.*
- **Trimestral (3 Meses):** Desconto ainda maior. Consome \`$26.28\` integralmente da carteira na hora do checkout, blindando o servidor contra cobranças por 90 dias.

## 4. Arquitetura do Banco de Dados

Para viabilizar este controle, a tabela responsável pelos Serviços, Assinaturas ou Servidores no painel (Front-end/Faturamento) necessitará, ao mínimo, das seguintes colunas principais:

1. \`billing_type\` (Tipo de Contrato do Serviço)
   - Valores esperados: \`hourly\`, \`monthly\`, \`quarterly\`, \`semi_annually\`, \`annually\`.
   - \`semi_annually\`: cobrança semestral (6 meses). \`annually\`: cobrança anual (12 meses).
2. \`next_due_date\` (Data do Próximo Vencimento / Renovação)
   - Quando atrelado ao \`hourly\`: Recebe valor \`NULL\`, já que não há fim previsto além do esgotamento da carteira de forma gradativa.
   - Quando atrelado aos demais ciclos: Armazena a data exata do final do ciclo contratado (Data da Compra + X dias).
3. \`status\` (Estado do Serviço)
   - Valores esperados: \`active\`, \`suspended\`, \`deleted\`.

## 5. A Lógica dos Trabalhadores (Workers / Cronjobs)

O sistema de faturamento funcionará inteiramente em segundo plano monitorando a base de dados em busca de vencimentos e abatimentos.

### 5.1 O "Comedor de Horas" (Worker de Faturamento Horário)
- **Frequência Típica:** A cada 1 hora.
- **Importante:** A cobrança é por hora de **reserva** do servidor (existência no node), não por tempo ligado. Servidor desligado (stopped) continua sendo cobrado, pois ocupa recursos alocados.
- **Função:** 
  1. Busca no Banco de Dados todos os servidores em que \`status = 'active'\` e o \`billing_type = 'hourly'\`.
  2. Subtrai o custo por hora correspondente àquele servidor diretamente do saldo atual da *Wallet* atrelada.
  3. Verifica se, após a subtração, o saldo resultante final for menor ou igual a `0`.
  4. Caso não haja saldo suficiente, o script altera de imediato o status do servidor para \`suspended\` no banco e encaminha ao Daemon/Pterodactyl a instrução de suspensão.

### 5.2 O "Renovador de Períodos" (Worker de Faturamento Cíclico)
- **Frequência Típica:** 1 vez por dia (Ex: Meia-noite / 00:00).
- **Função:** 
  1. Busca no Banco de Dados todos os servidores ativos onde o dia corriqueiro seja igual (ou superior) ao \`next_due_date\` estipulado, e cujo \`billing_type\` seja diferente de \`hourly\`.
  2. Consulta a "Wallet" associada. Existe saldo que cubra integralmente o próximo ciclo do contrato vigente em questão? 
     - **SIM:** Desconta o valor cheio, renova o \`next_due_date\` para o próximo horizonte de tempo, e emite o recibo.
     - **NÃO:** Interfere no servidor alternando o status para \`suspended\`, enviando a suspensão ao Painel (Ptero) e encabeça o disparo de uma notificação requisitando de urgência a carga de créditos para a restauração.

## 6. Vantagens Diretas do Modelo

1. **Facilidade Financeira para Novos Clientes:** Usuários jovens esporádicos podem investir poucos Reais com micro-pagamentos cobrindo dias ociosos ao invés de barrar sua entrada.
2. **Captação de Saldo (Previsibilidade de Caixa):** Os planos fechados continuam gerando um grande apelo para a comunidade institucionalizada, entregando a receita do período antecipada aos cofres da empresa.
3. **Escalagem Comercial e Rentabilização Real:** Com base no isolamento de recursos sem considerar fatias de _Slots_, forçamos indiretamente a modernização do plano (Scale-Up) por conta da própria sede da comunidade, necessitando de mais processamento por trás, convertendo tickets mais robustos.
4. **Sinergia do Ecossistema:** Como todos se retroalimentam pelo saldo inicial da carteira, facilita-se eventuais campanhas promocionais e reembolsos parciais.
