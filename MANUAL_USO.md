# Manual de Uso - MKP Multisite WooCommerce Integrator

## 📖 Visão Geral

O MKP Multisite WooCommerce Integrator é um plugin que automatiza a criação e gerenciamento de sites WordPress dentro de uma rede multisite, baseado em assinaturas do WooCommerce. Cada assinatura ativa pode gerar automaticamente um novo site com configurações específicas.

## 🎯 Como Funciona

### Fluxo Principal

1. **Cliente faz assinatura** → Sistema detecta nova assinatura
2. **Plugin verifica plano** → Identifica limites e recursos
3. **Site é criado** → Novo subdomínio é gerado automaticamente
4. **Configurações aplicadas** → Limites de páginas e recursos definidos
5. **Cliente recebe acesso** → Pode gerenciar seu site individual

### Integrações Automáticas

- **WooCommerce Subscriptions** → Detecta novas/canceladas assinaturas
- **WordPress Multisite** → Cria/gerencia sites individuais
- **Limiter MKP Pro** → Aplica limites por plano de assinatura
- **Sistema de Email** → Notifica clientes sobre status

## 🚀 Usando o Plugin

### Para Administradores da Rede

#### 1. Dashboard Principal

**Localização:** Rede Admin > MKP Multisite WooCommerce

**Funcionalidades:**
- Visão geral de todas as assinaturas
- Estatísticas de sites ativos/suspensos
- Ações em massa para gerenciar sites
- Monitor de atividades em tempo real

**Informações Exibidas:**
```
📊 Estatísticas Gerais
- Total de assinaturas: 150
- Sites ativos: 142
- Sites suspensos: 8
- Assinaturas com sites: 145

🏢 Sites por Status
- Ativos: 85%
- Suspensos: 5%
- Cancelados: 10%
```

#### 2. Gerenciar Sites Individuais

**Ações Disponíveis:**
- ✅ **Ativar site** - Reativa site suspenso
- ⏸️ **Suspender site** - Suspende temporariamente
- 🗑️ **Deletar site** - Remove permanentemente
- 📊 **Ver estatísticas** - Uso de páginas e armazenamento
- 🔄 **Sincronizar** - Atualiza dados com assinatura

**Como usar:**
1. Acesse lista de sites
2. Escolha o site desejado
3. Use botões de ação ao lado de cada site
4. Confirme a ação quando solicitado

#### 3. Configurações Globais

**Localização:** MKP Multisite WooCommerce > Configurações

**Opções Disponíveis:**
```
🎛️ Configurações Gerais
- Limite padrão de páginas: 10
- Limite padrão de armazenamento: 1GB
- Template de email para novos sites
- Prefixo para subdomínios: "site-"

🔐 Configurações de Segurança  
- Validação de domínios
- Permissões por plano
- Restrições de conteúdo

⚙️ Configurações Técnicas
- Tempo de cache: 5 minutos
- Logs de atividade: 30 dias
- Backup automático: Diário
```

### Para Clientes (Proprietários de Sites)

#### 1. Acessando Seu Site

Após fazer uma assinatura, o cliente recebe:
- **Email de boas-vindas** com link do site
- **Credenciais de acesso** para o painel administrativo
- **Informações do plano** contratado

**Exemplo de acesso:**
```
Seu novo site: https://meusite.exemplo.com.br
Login: https://meusite.exemplo.com.br/wp-admin
Usuário: [seu-email]
Senha: [senha-gerada]
```

#### 2. Limitações por Plano

**Plano Básico:**
- Páginas: 10 máximo
- Armazenamento: 1GB
- Temas: Básicos inclusos
- Plugins: Limitados

**Plano Profissional:**
- Páginas: 50 máximo  
- Armazenamento: 5GB
- Temas: Premium inclusos
- Plugins: Avançados disponíveis

**Plano Enterprise:**
- Páginas: Ilimitadas
- Armazenamento: 20GB
- Temas: Todos inclusos
- Plugins: Completo acesso

#### 3. Monitoramento de Uso

Clientes podem ver seu uso atual:
- **Páginas utilizadas:** 8/10
- **Armazenamento usado:** 340MB/1GB
- **Status da assinatura:** Ativa
- **Próximo pagamento:** 15/07/2025

## 🔧 Funcionalidades Detalhadas

### 1. Criação Automática de Sites

**Quando um site é criado:**
1. Nova assinatura é detectada
2. Subdomínio único é gerado
3. WordPress é instalado automaticamente
4. Tema padrão é aplicado
5. Plugins necessários são ativados
6. Limites são configurados
7. Cliente recebe email de boas-vindas

**Personalização do processo:**
```php
// Hooks disponíveis para desenvolvedores
do_action('mkp_before_site_creation', $subscription_id, $user_id);
do_action('mkp_after_site_creation', $site_id, $subscription_id);
do_action('mkp_site_limits_applied', $site_id, $limits);
```

### 2. Sincronização com Assinaturas

**Monitoramento contínuo:**
- ✅ **Assinatura ativa** → Site permanece ativo
- ⏸️ **Assinatura suspensa** → Site é suspenso automaticamente
- ❌ **Assinatura cancelada** → Site é desativado
- 💰 **Pagamento em atraso** → Avisos são enviados

**Ações automáticas:**
```
Status WooCommerce → Ação no Site
Ativo             → Site ativo e funcional
Suspenso          → Site inacessível (página de pagamento)
Cancelado         → Site desativado (dados preservados)
Expirado          → Site removido após 30 dias
```

### 3. Sistema de Limites

**Como funciona:**
1. Cada produto WooCommerce define limites específicos
2. Limites são aplicados automaticamente ao site
3. Plugin monitora uso contínuo
4. Avisos são enviados quando próximo do limite
5. Acesso é restringido quando limite é excedido

**Tipos de limites:**
- **Páginas:** Número máximo de páginas/posts
- **Armazenamento:** Espaço em disco utilizado
- **Usuários:** Número de usuários no site
- **Plugins:** Quais plugins podem ser instalados
- **Temas:** Quais temas estão disponíveis

### 4. Notificações e Emails

**Emails automáticos enviados:**

**Para novos clientes:**
```
Assunto: Seu novo site está pronto!
Conteúdo:
- Link do site
- Credenciais de acesso  
- Informações do plano
- Links úteis e suporte
```

**Para pagamentos em atraso:**
```
Assunto: Pagamento pendente - Ação necessária
Conteúdo:
- Status da assinatura
- Valor em atraso
- Link para pagamento
- Data limite para regularização
```

**Para administradores:**
```
Assunto: Resumo diário - MKP Multisite
Conteúdo:
- Novos sites criados
- Sites suspensos
- Problemas detectados
- Estatísticas gerais
```

### 5. Backups e Recuperação

**Backup automático:**
- Executado diariamente
- Inclui banco de dados e arquivos
- Armazenado por 30 dias
- Recuperação com um clique

**Backup manual:**
- Disponível no painel admin
- Pode ser agendado
- Download direto disponível
- Restauração seletiva

## 📊 Relatórios e Estatísticas

### Dashboard de Administrador

**Widgets disponíveis:**
- 📈 Crescimento de assinaturas
- 💰 Receita por plano
- 🌐 Sites mais ativos
- ⚠️ Alertas de sistema
- 📋 Tarefas pendentes

### Relatórios Detalhados

**Relatório de assinaturas:**
- Lista completa de todas as assinaturas
- Status atual de cada site
- Uso de recursos por site
- Histórico de pagamentos

**Relatório de performance:**
- Sites com maior tráfego
- Uso de armazenamento por site
- Sites próximos dos limites
- Problemas de performance

## 🛠️ Manutenção e Otimização

### Tarefas Diárias Automáticas

1. **Verificar status das assinaturas**
2. **Aplicar limites atualizados**
3. **Enviar notificações pendentes**
4. **Executar backups**
5. **Limpar logs antigos**
6. **Verificar integridade dos sites**

### Otimizações Recomendadas

**Para melhor performance:**
- Configure cache apropriado
- Use CDN para arquivos estáticos
- Monitore uso de banco de dados
- Mantenha plugins atualizados
- Faça limpeza regular de logs

**Para segurança:**
- Mantenha WordPress atualizado
- Use senhas fortes
- Configure SSL para todos os sites
- Monitore tentativas de login
- Faça backup regular

---

**Versão do Manual:** 1.0.1  
**Última Atualização:** 30 de Junho de 2025  
**Compatível com:** WordPress 6.0+, WooCommerce Subscriptions 7.6.0+
