<?php
/**
 * Gerenciador de subdomínios para MKP Multisite WooCommerce
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Subdomain_Manager {
    
    private $activity_logger;
    
    public function __construct($activity_logger) {
        $this->activity_logger = $activity_logger;
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('wp_insert_site', array($this, 'after_site_creation'), 10, 1);
        add_filter('mkp_generate_subdomain', array($this, 'filter_subdomain_generation'), 10, 3);
    }
    
    /**
     * Gerar subdomínio único baseado no nome e assinatura
     */
    public function generate_unique_subdomain($base_name, $subscription_id = null) {
        // Sanitizar nome base
        $subdomain = $this->sanitize_subdomain($base_name);
        
        // Aplicar filtros personalizados
        $subdomain = apply_filters('mkp_generate_subdomain', $subdomain, $base_name, $subscription_id);
        
        // Garantir unicidade
        $unique_subdomain = $this->ensure_unique_subdomain($subdomain);
        
        // Log da geração
        if ($this->activity_logger) {
            $this->activity_logger->log(
                0, 
                $subscription_id, 
                0, 
                'subdomain_generated', 
                "Subdomínio gerado: {$unique_subdomain} (base: {$base_name})"
            );
        }
        
        return $unique_subdomain;
    }
    
    /**
     * Sanitizar nome para subdomínio
     */
    private function sanitize_subdomain($name) {
        // Converter para minúsculas
        $subdomain = strtolower($name);
        
        // Remover acentos
        $subdomain = remove_accents($subdomain);
        
        // Manter apenas letras, números e hífens
        $subdomain = preg_replace('/[^a-z0-9\-]/', '', $subdomain);
        
        // Remover hífens consecutivos
        $subdomain = preg_replace('/\-+/', '-', $subdomain);
        
        // Remover hífens do início e fim
        $subdomain = trim($subdomain, '-');
        
        // Garantir comprimento mínimo e máximo
        if (strlen($subdomain) < 3) {
            $subdomain = 'site-' . $subdomain;
        }
        
        if (strlen($subdomain) > 50) {
            $subdomain = substr($subdomain, 0, 50);
            $subdomain = rtrim($subdomain, '-');
        }
        
        return $subdomain;
    }
    
    /**
     * Garantir que o subdomínio é único
     */
    private function ensure_unique_subdomain($subdomain) {
        $original_subdomain = $subdomain;
        $counter = 1;
        
        while ($this->subdomain_exists($subdomain)) {
            $subdomain = $original_subdomain . '-' . $counter;
            $counter++;
            
            // Prevenir loop infinito
            if ($counter > 9999) {
                $subdomain = $original_subdomain . '-' . time();
                break;
            }
        }
        
        return $subdomain;
    }
    
    /**
     * Verificar se subdomínio já existe
     */
    private function subdomain_exists($subdomain) {
        global $wpdb;
        
        if (!is_multisite()) {
            return false;
        }
        
        // Verificar na tabela de sites
        $domain = $subdomain . '.' . DOMAIN_CURRENT_SITE;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s",
            $domain
        ));
        
        return !empty($exists);
    }
    
    /**
     * Obter informações do subdomínio por ID do site
     */
    public function get_subdomain_info($site_id) {
        $site_details = get_blog_details($site_id);
        
        if (!$site_details) {
            return false;
        }
        
        $domain_parts = explode('.', $site_details->domain);
        $subdomain = $domain_parts[0];
        
        return array(
            'site_id' => $site_id,
            'subdomain' => $subdomain,
            'full_domain' => $site_details->domain,
            'path' => $site_details->path,
            'registered' => $site_details->registered,
            'status' => $this->get_subdomain_status($site_id)
        );
    }
    
    /**
     * Obter status do subdomínio
     */
    private function get_subdomain_status($site_id) {
        $mkp_status = get_site_meta($site_id, '_mkp_status', true);
        
        if ($mkp_status) {
            return $mkp_status;
        }
        
        // Verificar se o site está ativo no WordPress
        $site_details = get_blog_details($site_id);
        
        if ($site_details && $site_details->deleted == '0') {
            return 'active';
        }
        
        return 'inactive';
    }
    
    /**
     * Configurar redirecionamento para site suspenso
     */
    public function setup_suspension_redirect($site_id, $reason = 'payment_due') {
        $redirect_config = array(
            'enabled' => true,
            'reason' => $reason,
            'redirect_url' => $this->get_suspension_page_url($reason),
            'created_at' => current_time('mysql')
        );
        
        update_site_meta($site_id, '_mkp_suspension_redirect', $redirect_config);
        
        // Hook para ações personalizadas
        do_action('mkp_suspension_redirect_setup', $site_id, $reason, $redirect_config);
        
        return $redirect_config;
    }
    
    /**
     * Remover redirecionamento de suspensão
     */
    public function remove_suspension_redirect($site_id) {
        delete_site_meta($site_id, '_mkp_suspension_redirect');
        
        do_action('mkp_suspension_redirect_removed', $site_id);
    }
    
    /**
     * Obter URL da página de suspensão
     */
    private function get_suspension_page_url($reason) {
        $base_url = network_home_url('/suspensao/');
        
        $urls = array(
            'payment_due' => $base_url . 'pagamento-pendente/',
            'subscription_expired' => $base_url . 'assinatura-expirada/',
            'policy_violation' => $base_url . 'violacao-politica/',
            'maintenance' => $base_url . 'manutencao/'
        );
        
        return isset($urls[$reason]) ? $urls[$reason] : $urls['payment_due'];
    }
    
    /**
     * Listar todos os subdomínios gerenciados
     */
    public function list_managed_subdomains($args = array()) {
        $defaults = array(
            'status' => 'all',
            'limit' => 100,
            'offset' => 0,
            'search' => '',
            'orderby' => 'registered',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sites_args = array(
            'meta_key' => '_mkp_subscription_id',
            'number' => $args['limit'],
            'offset' => $args['offset'],
            'orderby' => $args['orderby'],
            'order' => $args['order']
        );
        
        // Filtrar por status se especificado
        if ($args['status'] !== 'all') {
            $sites_args['meta_query'] = array(
                array(
                    'key' => '_mkp_status',
                    'value' => $args['status'],
                    'compare' => '='
                )
            );
        }
        
        // Filtrar por pesquisa se especificado
        if (!empty($args['search'])) {
            $sites_args['search'] = $args['search'];
        }
        
        $sites = get_sites($sites_args);
        $subdomains = array();
        
        foreach ($sites as $site) {
            $subdomain_info = $this->get_subdomain_info($site->blog_id);
            
            if ($subdomain_info) {
                // Adicionar informações extras
                $subdomain_info['subscription_id'] = get_site_meta($site->blog_id, '_mkp_subscription_id', true);
                $subdomain_info['plan_details'] = get_site_meta($site->blog_id, '_mkp_plan_details', true);
                $subdomain_info['usage_stats'] = get_site_meta($site->blog_id, '_mkp_usage_stats', true);
                
                $subdomains[] = $subdomain_info;
            }
        }
        
        return $subdomains;
    }
    
    /**
     * Validar configuração de subdomínio
     */
    public function validate_subdomain_config() {
        $issues = array();
        
        // Verificar se multisite está habilitado
        if (!is_multisite()) {
            $issues[] = array(
                'type' => 'error',
                'message' => 'WordPress Multisite não está habilitado'
            );
        }
        
        // Verificar configuração de subdomínios
        if (!defined('SUBDOMAIN_INSTALL') || !SUBDOMAIN_INSTALL) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'Multisite não está configurado para subdomínios'
            );
        }
        
        // Verificar domínio principal
        if (!defined('DOMAIN_CURRENT_SITE') || empty(DOMAIN_CURRENT_SITE)) {
            $issues[] = array(
                'type' => 'error',
                'message' => 'DOMAIN_CURRENT_SITE não está definido'
            );
        }
        
        // Verificar configuração DNS wildcard
        $test_subdomain = 'test-' . time();
        $test_domain = $test_subdomain . '.' . DOMAIN_CURRENT_SITE;
        
        if (!$this->test_wildcard_dns($test_domain)) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'DNS wildcard pode não estar configurado corretamente'
            );
        }
        
        return $issues;
    }
    
    /**
     * Testar configuração DNS wildcard
     */
    private function test_wildcard_dns($test_domain) {
        // Teste básico de resolução DNS
        $ip = gethostbyname($test_domain);
        $main_ip = gethostbyname(DOMAIN_CURRENT_SITE);
        
        // Se os IPs são iguais, provavelmente o wildcard está funcionando
        return $ip === $main_ip;
    }
    
    /**
     * Gerar relatório de subdomínios
     */
    public function generate_subdomains_report() {
        $all_subdomains = $this->list_managed_subdomains(array('limit' => 0));
        
        $report = array(
            'total_subdomains' => count($all_subdomains),
            'active_subdomains' => 0,
            'suspended_subdomains' => 0,
            'inactive_subdomains' => 0,
            'storage_usage' => 0,
            'most_used_prefixes' => array(),
            'configuration_issues' => $this->validate_subdomain_config()
        );
        
        $prefixes = array();
        
        foreach ($all_subdomains as $subdomain) {
            // Contar por status
            switch ($subdomain['status']) {
                case 'active':
                    $report['active_subdomains']++;
                    break;
                case 'suspended':
                    $report['suspended_subdomains']++;
                    break;
                default:
                    $report['inactive_subdomains']++;
                    break;
            }
            
            // Calcular uso de armazenamento
            if (isset($subdomain['usage_stats']['storage_used'])) {
                $report['storage_usage'] += $subdomain['usage_stats']['storage_used'];
            }
            
            // Analisar prefixos
            $prefix = explode('-', $subdomain['subdomain'])[0];
            if (!isset($prefixes[$prefix])) {
                $prefixes[$prefix] = 0;
            }
            $prefixes[$prefix]++;
        }
        
        // Ordenar prefixos mais usados
        arsort($prefixes);
        $report['most_used_prefixes'] = array_slice($prefixes, 0, 10, true);
        
        return $report;
    }
    
    /**
     * Migrar subdomínio (alterar nome)
     */
    public function migrate_subdomain($site_id, $new_subdomain) {
        $old_info = $this->get_subdomain_info($site_id);
        
        if (!$old_info) {
            return new WP_Error('site_not_found', 'Site não encontrado');
        }
        
        // Verificar se novo subdomínio está disponível
        if ($this->subdomain_exists($new_subdomain)) {
            return new WP_Error('subdomain_exists', 'Subdomínio já existe');
        }
        
        // Sanitizar novo subdomínio
        $new_subdomain = $this->sanitize_subdomain($new_subdomain);
        
        if (empty($new_subdomain)) {
            return new WP_Error('invalid_subdomain', 'Subdomínio inválido');
        }
        
        // Atualizar domínio no banco
        global $wpdb;
        
        $new_domain = $new_subdomain . '.' . DOMAIN_CURRENT_SITE;
        
        $result = $wpdb->update(
            $wpdb->blogs,
            array('domain' => $new_domain),
            array('blog_id' => $site_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('migration_failed', 'Falha ao atualizar banco de dados');
        }
        
        // Limpar caches
        wp_cache_delete($site_id, 'sites');
        wp_cache_delete($site_id, 'site-details');
        
        // Log da migração
        if ($this->activity_logger) {
            $subscription_id = get_site_meta($site_id, '_mkp_subscription_id', true);
            
            $this->activity_logger->log(
                $site_id, 
                $subscription_id, 
                0, 
                'subdomain_migrated', 
                "Subdomínio migrado de {$old_info['subdomain']} para {$new_subdomain}"
            );
        }
        
        // Hook para ações pós-migração
        do_action('mkp_subdomain_migrated', $site_id, $old_info['subdomain'], $new_subdomain);
        
        return array(
            'old_subdomain' => $old_info['subdomain'],
            'new_subdomain' => $new_subdomain,
            'new_domain' => $new_domain
        );
    }
    
    /**
     * Hook após criação de site
     */
    public function after_site_creation($new_site) {
        $site_id = $new_site->blog_id;
        $subscription_id = get_site_meta($site_id, '_mkp_subscription_id', true);
        
        if ($subscription_id) {
            // Registrar subdomínio na tabela personalizada
            $this->register_subdomain($site_id, $subscription_id);
        }
    }
    
    /**
     * Registrar subdomínio na tabela personalizada
     */
    private function register_subdomain($site_id, $subscription_id) {
        global $wpdb;
        
        $site_details = get_blog_details($site_id);
        
        if (!$site_details) {
            return false;
        }
        
        $domain_parts = explode('.', $site_details->domain);
        $subdomain = $domain_parts[0];
        
        $table_name = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'site_id' => $site_id,
                'subscription_id' => $subscription_id,
                'subdomain' => $subdomain,
                'status' => 'active',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Filtro para personalizar geração de subdomínio
     */
    public function filter_subdomain_generation($subdomain, $base_name, $subscription_id) {
        // Permitir personalização via filtros
        return $subdomain;
    }
}