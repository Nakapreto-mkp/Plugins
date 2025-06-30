# MKP Multisite WooCommerce Integrator

## Visão Geral

O MKP Multisite WooCommerce Integrator é um plugin WordPress que automatiza a criação e gerenciamento de sites em uma rede multisite baseado em assinaturas do WooCommerce. Cada assinatura ativa pode gerar automaticamente um novo site com configurações específicas.

## ✅ Correção Implementada

### Problema Crítico Resolvido
- **WooCommerce Subscriptions 7.6.0+ Compatibility**: Corrigido erro fatal `ArgumentCountError` na função `wcs_get_subscriptions()`
- **Localização**: `includes/class-subscription-manager.php` linha 147
- **Solução**: Implementado wrapper seguro `get_subscriptions_safe()` com detecção automática de versão

## 🚀 Funcionalidades Principais

### ✅ Gerenciamento de Sites
- Criação automática de sites baseada em assinaturas
- Geração de subdomínios únicos
- Configuração automática de temas e plugins
- Sistema de limites por plano (páginas, armazenamento, usuários)

### ✅ Compatibilidade Robusta
- Verificador automático de compatibilidade
- Suporte a WooCommerce Subscriptions 7.0.0+
- Correção específica para versão 7.6.0+
- Relatórios detalhados de compatibilidade

### ✅ Interface Administrativa
- Dashboard completo com estatísticas
- Gerenciamento visual de sites
- Ações em massa
- Sistema de logs avançado
- Exportação de dados

### ✅ Integrações Avançadas
- API REST completa para aplicações móveis
- Sistema de notificações por email
- Backups automáticos
- Redirecionamentos para sites suspensos
- Webhooks do WooCommerce

## 📁 Estrutura do Plugin

```
mkp-multisite-woo-integrator/
├── admin/                          # Interface administrativa
│   ├── css/admin.css               # Estilos do admin
│   ├── js/admin.js                 # JavaScript do admin
│   ├── notices/                    # Templates de avisos
│   └── class-admin-panel.php       # Painel principal
├── api/                            # API REST
│   └── class-rest-api.php          # Endpoints da API
├── backup/                         # Sistema de backup
│   └── class-backup-manager.php    # Gerenciador de backups
├── includes/                       # Classes principais
│   ├── class-compatibility-checker.php    # Verificador de compatibilidade
│   ├── class-email-notifications.php      # Sistema de emails
│   ├── class-limiter-integration.php      # Sistema de limites
│   ├── class-mkp-multisite-woo-integrator.php # Classe principal
│   ├── class-redirect-handler.php         # Redirecionamentos
│   ├── class-subdomain-manager.php        # Gerenciador de subdomínios
│   └── class-subscription-manager.php     # Gerenciador de assinaturas (CORRIGIDO)
├── logs/                           # Sistema de logs
│   └── class-activity-logger.php   # Logger de atividades
├── templates/                      # Templates
├── mkp-multisite-woo-integrator.php # Arquivo principal
├── changelog.md                    # Histórico de mudanças
├── TUTORIAL_INSTALACAO.md         # Tutorial de instalação
├── MANUAL_USO.md                   # Manual de uso
├── DOCUMENTACAO_INTEGRACOES.md    # Documentação técnica
└── README.md                       # Este arquivo
```

## 🔧 Instalação

### Pré-requisitos
- WordPress 6.0+ com Multisite habilitado
- PHP 8.0+
- WooCommerce instalado e ativado
- WooCommerce Subscriptions 7.0.0+ (recomendado 7.6.0+)

### Instalação Manual
1. Baixe todos os arquivos do plugin
2. Faça upload para `/wp-content/plugins/mkp-multisite-woo-integrator/`
3. Ative no **Rede Admin > Plugins**
4. Configure as opções em **Rede Admin > MKP Multisite**

### Verificação de Compatibilidade
O plugin inclui um verificador automático de compatibilidade que:
- Testa todas as dependências necessárias
- Verifica versões do WooCommerce Subscriptions
- Detecta problemas de configuração
- Exibe avisos detalhados no admin

## 🎯 Como Usar

### Para Administradores
1. **Dashboard**: Visualize estatísticas gerais e atividades recentes
2. **Gerenciar Sites**: Controle individual de cada site criado
3. **Assinaturas**: Monitore sincronização com WooCommerce
4. **Logs**: Acompanhe todas as atividades do sistema
5. **Configurações**: Ajuste limites padrão e configurações globais

### Para Clientes
1. Cliente faz assinatura no WooCommerce
2. Site é criado automaticamente
3. Cliente recebe email com dados de acesso
4. Site funciona com limites baseados no plano contratado

## 🔗 API REST

### Endpoints Principais
- `GET /wp-json/mkp/v1/sites` - Listar sites do usuário
- `POST /wp-json/mkp/v1/sites` - Criar novo site
- `GET /wp-json/mkp/v1/sites/{id}/stats` - Estatísticas do site
- `PUT /wp-json/mkp/v1/sites/{id}/status` - Atualizar status
- `POST /wp-json/mkp/v1/sync-subscription` - Sincronizar assinatura

### Aplicações Móveis
- `GET /wp-json/mkp/v1/mobile/dashboard` - Dashboard móvel
- `POST /wp-json/mkp/v1/mobile/register-device` - Registrar dispositivo

## 📊 Recursos Técnicos

### Sistema de Logs
- Log detalhado de todas as atividades
- Severidades: info, warning, error, critical
- Exportação para CSV
- Limpeza automática de logs antigos

### Sistema de Backup
- Backup automático diário de todos os sites
- Backup antes da exclusão de sites
- Compressão em ZIP
- Retenção configurável

### Sistema de Limites
- Limites por páginas/posts
- Limites de armazenamento
- Limites de usuários
- Recursos por plano (temas, plugins)

### Notificações por Email
- Email de boas-vindas para novos sites
- Lembretes de pagamento
- Avisos de suspensão
- Confirmação de reativação

## 🔄 Sincronização Automática

O plugin monitora continuamente:
- Status das assinaturas WooCommerce
- Pagamentos em atraso
- Cancelamentos e renovações
- Aplicação automática de ações nos sites

## 🚨 Solução de Problemas

### Erro ArgumentCountError (RESOLVIDO)
- **Problema**: WooCommerce Subscriptions 7.6.0+ mudou assinatura da função
- **Solução**: Implementado wrapper automático com detecção de versão
- **Status**: ✅ Corrigido na versão 1.0.1

### Verificação de Compatibilidade
1. Acesse **Rede Admin > MKP Multisite > Configurações**
2. Clique na aba "Compatibilidade"
3. Verifique se todos os itens estão marcados como "OK"

### Debug
- Ative `WP_DEBUG` no wp-config.php
- Verifique logs em **MKP Multisite > Logs**
- Consulte `/wp-content/debug.log`

## 📞 Suporte

- **GitHub Issues**: Para reportar bugs
- **Documentação**: Consulte os arquivos `.md` inclusos
- **Logs**: Sempre inclua logs ao reportar problemas

## 📋 Requisitos do Sistema

### Mínimo
- WordPress 6.0+ (Multisite)
- PHP 8.0+
- WooCommerce 8.0+
- WooCommerce Subscriptions 7.0.0+

### Recomendado
- WordPress 6.4+
- PHP 8.1+
- WooCommerce 8.5+
- WooCommerce Subscriptions 7.6.0+
- Servidor com suporte a ZIP

## 🔐 Segurança

- Validação de nonces em todas as operações AJAX
- Sanitização de dados de entrada
- Verificação de permissões por usuário/site
- Proteção de diretórios de backup
- Logs de todas as ações administrativas

## 📈 Performance

- Cache de estatísticas de uso
- Compressão de backups
- Queries otimizadas do banco
- Limpeza automática de dados antigos
- Paginação em listas grandes

---

**Versão**: 1.0.1 (Compatibilidade com WCS 7.6.0+)  
**Última Atualização**: 30 de Junho de 2025  
**Autor**: MKP Team  
**Licença**: GPL v2 ou superior
