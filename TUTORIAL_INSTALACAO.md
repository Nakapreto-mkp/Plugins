# Tutorial de Instalação - MKP Multisite WooCommerce Integrator

## 📋 Pré-requisitos

### Requisitos Obrigatórios
- **WordPress**: 6.0 ou superior
- **WordPress Multisite**: Deve estar habilitado
- **PHP**: 8.0 ou superior
- **WooCommerce**: Plugin instalado e ativado
- **WooCommerce Subscriptions**: 7.0.0 ou superior (recomendado 7.6.0+)

### Verificação de Requisitos

1. **Verificar se WordPress Multisite está ativo:**
   - Acesse o painel administrativo do WordPress
   - Vá em **Ferramentas > Configuração da Rede**
   - Se não aparecer, o Multisite não está habilitado

2. **Verificar versão do PHP:**
   - Acesse **Ferramentas > Saúde do Site**
   - Procure por "Versão do PHP"
   - Certifique-se que é 8.0 ou superior

3. **Verificar plugins necessários:**
   - Acesse **Plugins > Plugins Instalados**
   - Confirme que WooCommerce e WooCommerce Subscriptions estão ativos

## 🚀 Instalação Passo a Passo

### Método 1: Instalação Manual (Recomendado)

#### Passo 1: Download do Plugin
1. Baixe todos os arquivos do plugin do repositório GitHub
2. Crie uma pasta chamada `mkp-multisite-woo-integrator`
3. Coloque todos os arquivos dentro desta pasta

#### Passo 2: Upload para o WordPress
1. Acesse seu servidor via FTP ou cPanel
2. Navegue até `/wp-content/plugins/`
3. Faça upload da pasta `mkp-multisite-woo-integrator`
4. Certifique-se que a estrutura de arquivos está correta:

```
wp-content/plugins/mkp-multisite-woo-integrator/
├── admin/
│   ├── css/
│   ├── js/
│   ├── notices/
│   └── class-admin-panel.php
├── api/
├── backup/
├── includes/
│   ├── class-compatibility-checker.php
│   ├── class-subscription-manager.php
│   └── ... outros arquivos
├── logs/
├── templates/
├── mkp-multisite-woo-integrator.php
└── changelog.md
```

#### Passo 3: Ativação
1. Acesse **Rede Admin > Plugins** (não o admin normal)
2. Encontre "MKP Multisite WooCommerce Integrator"
3. Clique em **Ativar para a Rede**

### Método 2: Instalação via ZIP

#### Passo 1: Criar arquivo ZIP
1. Comprima todos os arquivos do plugin em um arquivo ZIP
2. Nomeie como `mkp-multisite-woo-integrator.zip`

#### Passo 2: Upload via WordPress
1. Acesse **Rede Admin > Plugins > Adicionar Novo**
2. Clique em **Enviar Plugin**
3. Escolha o arquivo ZIP
4. Clique em **Instalar Agora**
5. Após instalação, clique em **Ativar Plugin**

## ⚙️ Configuração Inicial

### Verificação de Compatibilidade

1. **Após ativação**, verifique se aparecem avisos no topo da página
2. Se aparecer aviso de compatibilidade, clique em **"Ver detalhes da compatibilidade"**
3. Verifique se todos os itens estão marcados como "Success" ou "Info"
4. Se houver erros "Error", corrija antes de continuar

### Configuração Básica

1. **Acesse as configurações:**
   - Vá em **Rede Admin > MKP Multisite WooCommerce**
   - Ou clique no link "Configurações" na lista de plugins

2. **Configure as opções básicas:**
   - Defina limites padrão de páginas por site
   - Configure limites de armazenamento
   - Defina templates de email
   - Configure opções de backup

## 🔧 Configuração Avançada

### Configuração de Produtos WooCommerce

1. **Criar produtos de assinatura:**
   - Acesse **Produtos > Adicionar Novo**
   - Defina como "Assinatura" no tipo de produto
   - Configure preço e período de cobrança

2. **Configurar metadados do produto:**
   ```php
   // Limite de páginas por site
   _mkp_page_limit = 50
   
   // Limite de armazenamento (MB)
   _mkp_storage_limit = 2048
   
   // Recursos adicionais
   _mkp_features = array('custom_themes', 'advanced_analytics')
   ```

### Configuração de Multisite

1. **Configurar wildcard DNS:**
   - Configure *.seudominio.com para apontar para seu servidor
   - Ou configure subdomínios manualmente

2. **Configurar WordPress Multisite:**
   - Edite `wp-config.php` para permitir novos sites
   - Configure `SUBDOMAIN_INSTALL` como `true`

## 📊 Monitoramento e Logs

### Visualizar Logs de Atividade

1. **Acesse a área de logs:**
   - Vá em **Rede Admin > MKP Multisite WooCommerce > Logs**

2. **Filtrar logs:**
   - Por data
   - Por tipo de ação
   - Por usuário ou site

### Verificar Compatibilidade

1. **Relatório de compatibilidade:**
   - Acesse **Rede Admin > Ferramentas > Saúde do Site**
   - Procure por "MKP Multisite WooCommerce Integrator"

2. **Informações detalhadas:**
   - Versão do plugin
   - Versão do WooCommerce Subscriptions
   - Status de compatibilidade
   - Problemas detectados

## 🔍 Solução de Problemas

### Erro "ArgumentCountError"

**Sintoma:** Plugin não ativa ou apresenta erro fatal

**Solução:**
1. Verifique se está usando a versão corrigida (1.0.1+)
2. Confirme que WooCommerce Subscriptions está atualizado
3. Verifique logs em `/wp-content/debug.log`

### Problemas de Compatibilidade

**Sintoma:** Avisos de compatibilidade no admin

**Solução:**
1. Atualize todas as dependências
2. Verifique a versão do PHP
3. Confirme que WordPress Multisite está ativo

### Sites não são criados automaticamente

**Sintoma:** Assinaturas não geram novos sites

**Solução:**
1. Verifique configurações de Multisite
2. Confirme permissões de banco de dados
3. Verifique logs de erro

## 🆘 Suporte e Manutenção

### Backup Regular

1. **Backup automático:**
   - Configure backup diário dos dados
   - Inclua banco de dados e arquivos

2. **Backup manual:**
   - Acesse **MKP Multisite WooCommerce > Backup**
   - Faça download dos logs e configurações

### Monitoramento Contínuo

1. **Verificações diárias:**
   - Status das assinaturas
   - Funcionamento dos sites
   - Logs de erro

2. **Verificações semanais:**
   - Compatibilidade após atualizações
   - Performance geral
   - Limpeza de logs antigos

## 📞 Contato e Suporte

- **GitHub Issues:** Para reportar bugs
- **Documentação:** Consulte este arquivo e `changelog.md`
- **Logs:** Sempre inclua logs ao reportar problemas

---

**Versão do Tutorial:** 1.0.1  
**Última Atualização:** 30 de Junho de 2025  
**Compatível com:** WordPress 6.0+, WooCommerce Subscriptions 7.6.0+
