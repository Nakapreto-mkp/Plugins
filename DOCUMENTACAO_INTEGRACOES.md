# Documentação de Integrações - MKP Multisite WooCommerce Integrator

## 🔗 Visão Geral das Integrações

O plugin MKP Multisite WooCommerce Integrator funciona como um hub central que conecta diferentes sistemas WordPress para automatizar a criação e gerenciamento de sites baseados em assinaturas. Abaixo está a documentação completa de todas as integrações.

## 🛒 1. Integração com WooCommerce

### 1.1 WooCommerce Core

**Objetivo:** Detectar produtos e pedidos relacionados a assinaturas

**Hooks Utilizados:**
```php
// Detecta quando um pedido é concluído
add_action('woocommerce_order_status_completed', 'mkp_order_completed');

// Detecta mudanças no status do pedido
add_action('woocommerce_order_status_changed', 'mkp_order_status_changed');

// Integra com checkout para capturar metadados
add_action('woocommerce_checkout_order_processed', 'mkp_process_checkout');
```

**Funcionalidades:**
- Detecta produtos que geram sites
- Captura metadados de configuração
- Processa informações de cliente
- Integra com sistema de pagamentos

**Metadados de Produto Suportados:**
```php
// Configurações específicas por produto
_mkp_creates_site = 'yes'           // Se produto cria site
_mkp_page_limit = 50               // Limite de páginas
_mkp_storage_limit = 2048          // Limite em MB
_mkp_features = array(             // Recursos inclusos
    'custom_themes',
    'advanced_analytics',
    'premium_support'
)
_mkp_template_id = 'business'      // Template inicial
_mkp_subdomain_prefix = 'biz'      // Prefixo do subdomínio
```

### 1.2 WooCommerce Subscriptions

**Objetivo:** Gerenciar ciclo de vida das assinaturas e sites

**Versões Suportadas:**
- ✅ WooCommerce Subscriptions 7.0.0 - 7.5.x
- ✅ WooCommerce Subscriptions 7.6.0+ (com correções específicas)

**Hooks Principais:**
```php
// Assinatura criada
add_action('woocommerce_subscription_status_active', 'mkp_subscription_activated');

// Assinatura suspensa
add_action('woocommerce_subscription_status_on-hold', 'mkp_subscription_suspended');

// Assinatura cancelada
add_action('woocommerce_subscription_status_cancelled', 'mkp_subscription_cancelled');

// Renovação processada
add_action('woocommerce_subscription_renewal_payment_complete', 'mkp_renewal_complete');

// Falha no pagamento
add_action('woocommerce_subscription_renewal_payment_failed', 'mkp_payment_failed');
```

**Correção de Compatibilidade 7.6.0+:**
```php
// Problema original
$subscriptions = wcs_get_subscriptions(); // ❌ Erro ArgumentCountError

// Solução implementada
private function get_subscriptions_safe($args = array()) {
    try {
        if (version_compare(WC_Subscriptions::$version, '7.6.0', '>=')) {
            return wcs_get_subscriptions($args); // ✅ Com argumentos
        } else {
            return empty($args) ? wcs_get_subscriptions() : wcs_get_subscriptions($args);
        }
    } catch (ArgumentCountError $e) {
        return wcs_get_subscriptions(array()); // ✅ Fallback
    }
}
```

## 🌐 2. Integração com WordPress Multisite

### 2.1 Criação de Sites

**Objetivo:** Criar automaticamente novos sites na rede multisite

**Funções Utilizadas:**
```php
// Criar novo site
$site_id = wp_insert_site([
    'domain' => $domain,
    'path' => '/',
    'title' => $site_title,
    'user_id' => $user_id,
    'meta' => [
        '_mkp_subscription_id' => $subscription_id,
        '_mkp_plan_type' => $plan_type,
        '_mkp_limits' => $limits
    ]
]);

// Configurar site após criação
switch_to_blog($site_id);
// Aplicar configurações específicas
restore_current_blog();
```

**Processo de Criação:**
1. **Gerar subdomínio único** baseado no nome/produto
2. **Criar site WordPress** com configurações padrão
3. **Aplicar tema** específico do plano
4. **Instalar plugins** necessários
5. **Configurar usuários** e permissões
6. **Aplicar limites** do plano contratado

### 2.2 Gerenciamento de Sites

**Hooks para Sincronização:**
```php
// Site criado
add_action('wp_initialize_site', 'mkp_initialize_new_site');

// Site deletado
add_action('wp_delete_site', 'mkp_before_site_deletion');

// Usuário adicionado ao site
add_action('add_user_to_blog', 'mkp_user_added_to_site');
```

### 2.3 Configuração de Domínios

**Suporte a Subdomínios:**
```php
// Configuração automática de subdomínios
define('SUBDOMAIN_INSTALL', true);
define('DOMAIN_CURRENT_SITE', 'exemplo.com.br');
define('PATH_CURRENT_SITE', '/');

// Geração automática de subdomínios
function mkp_generate_subdomain($base_name, $subscription_id) {
    $subdomain = sanitize_title($base_name);
    $counter = 1;
    
    while (mkp_subdomain_exists($subdomain)) {
        $subdomain = sanitize_title($base_name) . '-' . $counter;
        $counter++;
    }
    
    return $subdomain;
}
```

## 🔒 3. Integração com Limiter MKP Pro

### 3.1 Sistema de Limites

**Objetivo:** Aplicar restrições baseadas no plano de assinatura

**Tipos de Limites Suportados:**
```php
$limits = [
    'pages' => [
        'max_count' => 50,
        'current_count' => 0,
        'post_types' => ['page', 'post', 'produto']
    ],
    'storage' => [
        'max_size' => 2048, // MB
        'current_size' => 0,
        'includes' => ['uploads', 'themes', 'plugins']
    ],
    'users' => [
        'max_users' => 10,
        'current_users' => 1,
        'roles_allowed' => ['subscriber', 'contributor']
    ],
    'features' => [
        'custom_themes' => true,
        'plugin_install' => false,
        'export_data' => true,
        'advanced_settings' => false
    ]
];
```

**Aplicação de Limites:**
```php
// Hooks para enforcement de limites
add_action('wp_insert_post', 'mkp_check_page_limit');
add_action('wp_handle_upload_prefilter', 'mkp_check_storage_limit');
add_action('add_user_to_blog', 'mkp_check_user_limit');
add_filter('user_can_richedit', 'mkp_limit_rich_editor');
```

### 3.2 Monitoramento de Uso

**Tracking Contínuo:**
```php
// Atualizar estatísticas de uso
function mkp_update_site_usage($site_id) {
    $stats = [
        'pages_count' => mkp_count_pages($site_id),
        'storage_used' => mkp_calculate_storage($site_id),
        'users_count' => count_users()['total_users'],
        'last_updated' => current_time('mysql')
    ];
    
    update_site_meta($site_id, '_mkp_usage_stats', $stats);
}

// Executar via cron
add_action('mkp_daily_usage_check', 'mkp_update_all_sites_usage');
```

## 📧 4. Integração com Sistema de Emails

### 4.1 Templates de Email

**Emails Automáticos Configurados:**

**Boas-vindas para novo site:**
```php
$template_welcome = [
    'subject' => 'Seu novo site {SITE_NAME} está pronto!',
    'body' => '
        Olá {CUSTOMER_NAME},
        
        Seu site foi criado com sucesso!
        
        Detalhes do seu site:
        - URL: {SITE_URL}
        - Login: {SITE_URL}/wp-admin
        - Usuário: {LOGIN_USER}
        - Senha: {LOGIN_PASS}
        
        Plano contratado: {PLAN_NAME}
        - Páginas incluídas: {PAGE_LIMIT}
        - Armazenamento: {STORAGE_LIMIT}MB
        
        Acesse agora: {SITE_URL}
        
        Suporte: suporte@exemplo.com.br
    ',
    'headers' => ['Content-Type: text/html; charset=UTF-8']
];
```

**Aviso de pagamento pendente:**
```php
$template_payment_due = [
    'subject' => 'Pagamento pendente - {SITE_NAME}',
    'body' => '
        Olá {CUSTOMER_NAME},
        
        Sua assinatura do site {SITE_NAME} tem um pagamento pendente.
        
        Detalhes:
        - Valor: {AMOUNT_DUE}
        - Vencimento: {DUE_DATE}
        - Status: {SUBSCRIPTION_STATUS}
        
        Para manter seu site ativo, efetue o pagamento:
        {PAYMENT_URL}
        
        Após 7 dias sem pagamento, seu site será suspenso.
        
        Dúvidas? Entre em contato conosco.
    '
];
```

### 4.2 Hooks de Email

```php
// Personalizações de email
add_filter('mkp_email_template_welcome', 'custom_welcome_template');
add_filter('mkp_email_subject_payment_due', 'custom_payment_subject');
add_action('mkp_before_send_email', 'log_email_sending');
add_action('mkp_after_send_email', 'track_email_delivery');
```

## 🔄 5. Integração com Sistema de Backup

### 5.1 Backup Automático

**Configuração de Backups:**
```php
$backup_config = [
    'frequency' => 'daily',           // diário, semanal, mensal
    'retention' => 30,                // dias para manter
    'include_files' => true,          // incluir arquivos
    'include_database' => true,       // incluir banco
    'compress' => true,               // comprimir arquivos
    'remote_storage' => [
        'enabled' => true,
        'provider' => 's3',           // s3, ftp, google_drive
        'credentials' => 'encrypted'
    ]
];
```

**Processo de Backup:**
```php
// Backup automático por site
function mkp_backup_site($site_id) {
    switch_to_blog($site_id);
    
    // Backup do banco de dados específico do site
    $db_backup = mkp_backup_database($site_id);
    
    // Backup dos arquivos do site
    $files_backup = mkp_backup_files($site_id);
    
    // Comprimir e armazenar
    $backup_file = mkp_create_backup_archive($site_id, $db_backup, $files_backup);
    
    // Upload para armazenamento remoto
    mkp_upload_backup($backup_file);
    
    restore_current_blog();
    
    return $backup_file;
}
```

### 5.2 Restauração

**Processo de Restore:**
```php
// Restaurar site completo
function mkp_restore_site($site_id, $backup_date) {
    $backup_file = mkp_get_backup_file($site_id, $backup_date);
    
    // Extrair backup
    $extracted = mkp_extract_backup($backup_file);
    
    // Restaurar banco de dados
    mkp_restore_database($site_id, $extracted['database']);
    
    // Restaurar arquivos
    mkp_restore_files($site_id, $extracted['files']);
    
    // Verificar integridade
    return mkp_verify_restoration($site_id);
}
```

## 📊 6. Integração com Sistema de Logs

### 6.1 Logging Avançado

**Tipos de Logs:**
```php
// Log de atividades por categoria
$log_categories = [
    'site_creation',      // Criação de sites
    'subscription_sync',  // Sincronização de assinaturas
    'payment_events',     // Eventos de pagamento
    'limit_enforcement',  // Aplicação de limites
    'backup_operations',  // Operações de backup
    'error_tracking',     // Rastreamento de erros
    'performance_stats'   // Estatísticas de performance
];
```

**Estrutura de Log:**
```php
$log_entry = [
    'timestamp' => current_time('mysql'),
    'site_id' => $site_id,
    'subscription_id' => $subscription_id,
    'user_id' => $user_id,
    'category' => 'site_creation',
    'action' => 'create_site_success',
    'details' => [
        'subdomain' => 'meusite.exemplo.com',
        'plan_type' => 'business',
        'processing_time' => '2.5s'
    ],
    'severity' => 'info',     // info, warning, error, critical
    'context' => [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'referrer' => $_SERVER['HTTP_REFERER']
    ]
];
```

### 6.2 Análise de Logs

**Dashboard de Análise:**
```php
// Estatísticas baseadas em logs
function mkp_get_log_statistics($period = '30_days') {
    return [
        'sites_created' => mkp_count_log_entries('site_creation', $period),
        'errors_occurred' => mkp_count_log_entries('error_tracking', $period),
        'backups_completed' => mkp_count_log_entries('backup_operations', $period),
        'performance_avg' => mkp_calculate_avg_processing_time($period),
        'top_errors' => mkp_get_top_errors($period),
        'busiest_hours' => mkp_analyze_activity_patterns($period)
    ];
}
```

## 🔌 7. API REST Personalizada

### 7.1 Endpoints Disponíveis

**Gerenciamento de Sites:**
```php
// Listar sites do usuário
GET /wp-json/mkp/v1/sites
Authorization: Bearer {token}

// Criar novo site
POST /wp-json/mkp/v1/sites
{
    "subscription_id": 123,
    "site_name": "Meu Novo Site",
    "plan": "business"
}

// Obter estatísticas de um site
GET /wp-json/mkp/v1/sites/{site_id}/stats

// Suspender/ativar site
PUT /wp-json/mkp/v1/sites/{site_id}/status
{
    "status": "suspended|active"
}
```

**Integração com Assinaturas:**
```php
// Sincronizar assinatura
POST /wp-json/mkp/v1/sync-subscription
{
    "subscription_id": 123
}

// Webhook para WooCommerce
POST /wp-json/mkp/v1/webhook/subscription-updated
{
    "subscription_id": 123,
    "status": "active",
    "plan_changes": {...}
}
```

### 7.2 Autenticação API

**Métodos Suportados:**
```php
// JWT Token
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...

// API Key (para integrações)
X-API-Key: mkp_sk_live_abc123...

// OAuth 2.0 (para aplicações terceiras)
Authorization: Bearer oauth_token_here
```

## 🔧 8. Hooks e Filtros para Desenvolvedores

### 8.1 Hooks de Ação

```php
// Hooks para personalização
do_action('mkp_before_site_creation', $subscription_id, $user_id, $plan_data);
do_action('mkp_after_site_creation', $site_id, $subscription_id, $user_id);
do_action('mkp_site_suspended', $site_id, $reason);
do_action('mkp_site_activated', $site_id);
do_action('mkp_limits_updated', $site_id, $old_limits, $new_limits);
do_action('mkp_backup_completed', $site_id, $backup_file_path);
do_action('mkp_subscription_synced', $subscription_id, $sync_data);
```

### 8.2 Filtros para Customização

```php
// Filtros para modificar comportamento
$subdomain = apply_filters('mkp_generate_subdomain', $subdomain, $subscription_id);
$limits = apply_filters('mkp_calculate_site_limits', $limits, $plan_type);
$email_template = apply_filters('mkp_email_template', $template, $email_type);
$backup_config = apply_filters('mkp_backup_configuration', $config, $site_id);
$site_creation_data = apply_filters('mkp_site_creation_data', $data, $subscription);
```

## 🚀 9. Integração com CDN e Performance

### 9.1 Otimização de Performance

**Cache Integration:**
```php
// Integração com plugins de cache
add_action('mkp_after_site_creation', 'mkp_configure_cache');
add_action('mkp_limits_updated', 'mkp_purge_site_cache');

function mkp_configure_cache($site_id) {
    switch_to_blog($site_id);
    
    // Configurar W3 Total Cache
    if (class_exists('W3_Config')) {
        $config = w3_instance('W3_Config');
        $config->set('pgcache.enabled', true);
        $config->save();
    }
    
    // Configurar WP Super Cache
    if (function_exists('wp_cache_setting')) {
        wp_cache_setting('wp_cache_enabled', 1);
    }
    
    restore_current_blog();
}
```

### 9.2 CDN Configuration

**Configuração Automática de CDN:**
```php
// Configurar CDN por site
function mkp_setup_cdn($site_id, $plan_type) {
    $cdn_config = [
        'basic' => [
            'enabled' => false
        ],
        'business' => [
            'enabled' => true,
            'provider' => 'cloudflare',
            'zones' => ['css', 'js', 'images']
        ],
        'enterprise' => [
            'enabled' => true,
            'provider' => 'aws_cloudfront',
            'zones' => ['all'],
            'custom_domain' => true
        ]
    ];
    
    return $cdn_config[$plan_type] ?? $cdn_config['basic'];
}
```

## 📱 10. Integração com Aplicações Móveis

### 10.1 API Mobile-First

**Endpoints Específicos para Mobile:**
```php
// Dashboard mobile
GET /wp-json/mkp/v1/mobile/dashboard
Response: {
    "site_stats": {...},
    "quick_actions": [...],
    "notifications": [...],
    "usage_summary": {...}
}

// Notificações push
POST /wp-json/mkp/v1/mobile/register-device
{
    "device_token": "fcm_token_here",
    "platform": "android|ios",
    "user_id": 123
}
```

### 10.2 Notificações Push

**Configuração de Push Notifications:**
```php
// Enviar notificação para dispositivos móveis
function mkp_send_push_notification($user_id, $message, $data = []) {
    $devices = mkp_get_user_devices($user_id);
    
    foreach ($devices as $device) {
        $payload = [
            'to' => $device->token,
            'notification' => [
                'title' => 'MKP Multisite',
                'body' => $message,
                'icon' => 'mkp_icon'
            ],
            'data' => $data
        ];
        
        mkp_send_fcm_notification($payload);
    }
}
```

---

## 📞 Suporte Técnico para Integrações

### Solução de Problemas Comuns

**Problemas de Sincronização:**
1. Verificar se webhooks estão configurados
2. Conferir permissões de API
3. Validar tokens de autenticação
4. Checar logs de erro específicos

**Problemas de Performance:**
1. Revisar configuração de cache
2. Otimizar queries do banco de dados
3. Verificar limitações de servidor
4. Analisar logs de performance

**Problemas de Email:**
1. Confirmar configuração SMTP
2. Verificar templates de email
3. Checar blacklists de spam
4. Validar permissões de envio

### Contato para Desenvolvedores

- **GitHub Issues:** Para bugs e solicitações de recursos
- **Documentação Técnica:** Wiki do repositório
- **Exemplos de Código:** Pasta `/examples` no repositório
- **API Reference:** Documentação completa da API REST

---

**Versão da Documentação:** 1.0.1  
**Última Atualização:** 30 de Junho de 2025  
**Compatibilidade:** WordPress 6.0+, WooCommerce 8.0+, WCS 7.6.0+
